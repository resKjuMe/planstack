<?php

namespace App\Http\Controllers;

use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use App\Models\PullRequestReview;
use App\Models\ReviewFinding;
use App\Models\User;
use App\Support\PullRequestApprovals;
use App\Support\PullRequestStateSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Pull Requests aus ps-review: die eigenen (reviewt oder geschrieben), per
 * Default nur offene, sortiert nach Handlungsbedarf (clientseitig).
 */
class PullRequestController extends Controller
{
    private const STATES = ['open', 'merged', 'closed', 'all'];

    private const ROLES = ['reviewed', 'authored', 'shared'];

    private const LIMIT = 500;

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        [$state, $role] = $this->filters($request);

        $pullRequests = $this->listQuery($user, $role, $state)
            ->with(['author', 'latestReview', 'runningReview.reviewer', 'approvals.user'])
            ->withCount('reviews')
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get();

        return Inertia::render('PullRequestsIndex', [
            'filters' => ['state' => $state, 'role' => $role],
            'filterUrls' => [
                'state' => collect(self::STATES)->mapWithKeys(fn ($s) => [$s => route('pull-requests.index', ['state' => $s, 'role' => $role])]),
                'role' => collect(self::ROLES)->mapWithKeys(fn ($r) => [$r => route('pull-requests.index', ['state' => $state, 'role' => $r])]),
            ],
            'pullRequests' => $pullRequests->map(fn (PullRequest $pr) => [
                ...$this->pullRequestRow($pr),
                'reviewsCount' => $pr->reviews_count,
                'approvals' => $this->approvals($pr),
                'mine' => $this->viewerState($pr, $user),
                'review' => $pr->latestReview ? $this->reviewRow($pr->latestReview, $pr) : null,
                'running' => $pr->runningReview ? [
                    'startedAt' => $pr->runningReview->review_started_at?->toIso8601String(),
                    'reviewer' => $pr->runningReview->reviewer?->name,
                ] : null,
            ])->values(),
            'syncUrl' => route('pull-requests.sync', ['state' => $state, 'role' => $role]),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'strings' => __('pull_requests'),
        ]);
    }

    /**
     * Gleicht nur offene PRs mit GitHub ab: „reviewt“ die der aktuellen Liste,
     * „geschrieben“ alle eigenen, auch die Planstack noch nicht kennt.
     */
    public function sync(Request $request, PullRequestStateSync $sync): RedirectResponse
    {
        $user = $request->user();
        [$state, $role] = $this->filters($request);
        $back = redirect()->route('pull-requests.index', ['state' => $state, 'role' => $role]);

        if ($role === 'authored') {
            $result = $sync->syncAuthored($user);
        } else {
            $open = $this->listQuery($user, $role, $state)->where('state', 'OPEN')->limit(self::LIMIT)->get();
            if ($open->isEmpty()) {
                return $back->with('status', __('flash.pull_request_sync_none'));
            }
            $result = $sync->sync($open);
        }

        if ($result['tokenMissing']) {
            return $back->with('error', __('flash.pr_sync_no_token'));
        }

        $message = __('flash.pull_request_sync_summary', ['updated' => $result['prs'], 'created' => $result['created']]);
        if ($result['errors'] > 0) {
            return $back->with('error', $message.' · '.__('flash.pr_sync_errors', ['count' => $result['errors']]).': '.implode('; ', $result['failures']));
        }

        return $back->with('status', $message);
    }

    /**
     * Markiert einen eigenen PR als „Shared Review“ (für die ganze Organisation
     * sichtbar) bzw. hebt die Markierung auf.
     */
    public function toggleShare(Request $request, PullRequest $pullRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($pullRequest->organization_id === $user->organization_id, 404);
        abort_unless($this->viewerState($pullRequest, $user)['isAuthor'], 403);

        $shared = $pullRequest->shared_at === null;
        $pullRequest->update([
            'shared_at' => $shared ? now() : null,
            'shared_by_id' => $shared ? $user->id : null,
        ]);

        return back();
    }

    public function show(Request $request, PullRequest $pullRequest): InertiaResponse
    {
        abort_unless($pullRequest->organization_id === $request->user()->organization_id, 404);

        $pullRequest->load(['author', 'approvals.user', 'reviews' => fn ($q) => $q->latest('review_started_at')->latest('id'), 'reviews.reviewer', 'reviews.findingItems']);

        return Inertia::render('PullRequestShow', [
            'pullRequest' => [
                ...$this->pullRequestRow($pullRequest),
                'descriptionHtml' => $this->md((string) $pullRequest->description),
                'commits' => $pullRequest->commits,
                'comments' => $pullRequest->comments,
                'reviewComments' => $pullRequest->review_comments,
                'lastCommit' => $pullRequest->last_commit,
                'mergedAt' => $pullRequest->merged_at?->toIso8601String(),
                'closedAt' => $pullRequest->closed_at?->toIso8601String(),
                'approvals' => $this->approvals($pullRequest),
            ],
            'reviews' => $pullRequest->reviews->map(fn (PullRequestReview $review) => [
                ...$this->reviewRow($review, $pullRequest),
                'reviewer' => $review->reviewer?->name,
                'summaryHtml' => $this->md((string) $review->summary),
                'commitSha' => $review->commit_sha,
                'strictness' => $review->strictness,
                'thoroughness' => $review->thoroughness,
                'model' => $review->model,
                'findings' => $review->findingItems
                    ->sortBy(fn (ReviewFinding $f) => [array_search($f->level->value, ['CRITICAL', 'IMPORTANT', 'HINT'], true), $f->file, $f->line])
                    ->map(fn (ReviewFinding $f) => [
                        'id' => $f->id,
                        'level' => $f->level->value,
                        'type' => $f->type->value,
                        'requiresPoDecision' => $f->requires_po_decision,
                        'file' => $f->file,
                        'line' => $f->line,
                        'fileUrl' => $f->file
                            ? "https://github.com/{$pullRequest->repository}/blob/".($review->commit_sha ?: $pullRequest->branch)."/{$f->file}".($f->line ? "#L{$f->line}" : '')
                            : null,
                        'title' => $f->title,
                        'messageHtml' => $this->md($f->message),
                    ])->values(),
            ])->values(),
            'backUrl' => route('pull-requests.index'),
            'strings' => __('pull_requests'),
        ]);
    }

    /**
     * @return array{0: string, 1: string} Status, Rolle
     */
    private function filters(Request $request): array
    {
        return [
            in_array($request->input('state'), self::STATES, true) ? $request->input('state') : 'open',
            in_array($request->input('role'), self::ROLES, true) ? $request->input('role') : 'reviewed',
        ];
    }

    private function listQuery(User $user, string $role, string $state): Builder
    {
        return PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->when($role === 'reviewed', fn (Builder $q) => $q->whereHas('reviews', fn (Builder $r) => $r->where('reviewer_id', $user->id)))
            ->when($role === 'authored', fn (Builder $q) => $q->where('author_id', $user->id))
            ->when($role === 'shared', fn (Builder $q) => $q->whereNotNull('shared_at'))
            ->when($state !== 'all', fn (Builder $q) => $q->where('state', strtoupper($state)));
    }

    private function pullRequestRow(PullRequest $pr): array
    {
        return [
            'id' => $pr->id,
            'repository' => $pr->repository,
            'number' => $pr->pull_request_id,
            'title' => $pr->title,
            'githubUrl' => $pr->url(),
            'showUrl' => route('pull-requests.show', $pr),
            'author' => $pr->author?->name ?? $pr->author_login,
            'branch' => $pr->branch,
            'targetBranch' => $pr->target_branch,
            'state' => $pr->state->value,
            'changedFiles' => $pr->changed_files,
            'additions' => $pr->additions,
            'deletions' => $pr->deletions,
            'openedAt' => $pr->opened_at?->toIso8601String(),
            'updatedAt' => $pr->updated_at?->toIso8601String(),
            'shared' => $pr->shared_at !== null,
            'shareUrl' => route('pull-requests.share', $pr),
        ];
    }

    /**
     * Eigene GitHub-Entscheidung zum PR und ob man selbst der Autor ist (dann
     * lässt GitHub kein Approve zu).
     */
    private function viewerState(PullRequest $pr, User $user): array
    {
        $login = PullRequest::loginFor($user);

        return [
            'githubDecision' => $pr->approvals
                ->first(fn (PullRequestApproval $a) => $a->user_id === $user->id || strcasecmp($a->login, $login) === 0)
                ?->state,
            'isAuthor' => $pr->author_id === $user->id || strcasecmp((string) $pr->author_login, $login) === 0,
        ];
    }

    private function approvals(PullRequest $pr): array
    {
        return [
            ...PullRequestApprovals::status($pr, $pr->approvals),
            'reviewers' => $pr->approvals
                ->sortBy(fn (PullRequestApproval $a) => [$a->state, $a->submitted_at])
                ->map(fn (PullRequestApproval $a) => [
                    'name' => $a->user?->name ?? $a->login,
                    'state' => $a->state,
                    'submittedAt' => $a->submitted_at?->toIso8601String(),
                    // Approve auf einem älteren Commit als dem aktuellen.
                    'outdated' => $a->commit_sha !== null && $pr->last_commit !== null && $a->commit_sha !== $pr->last_commit,
                ])->values(),
        ];
    }

    private function reviewRow(PullRequestReview $review, PullRequest $pr): array
    {
        return [
            'id' => $review->id,
            'status' => $review->isFinished() ? 'finished' : ($review->isAbandoned() ? 'abandoned' : 'running'),
            'startedAt' => $review->review_started_at?->toIso8601String(),
            'finishedAt' => $review->review_finished_at?->toIso8601String(),
            'decision' => $review->decision?->value,
            'findings' => $review->findings,
            'critical' => $review->findings_critical,
            'important' => $review->findings_important,
            'hint' => $review->findings_hint,
            'criticality' => $review->criticality,
            'criticalityReason' => $review->criticality_reason,
            'aiReviewability' => $review->ai_reviewability,
            'aiReviewabilityReason' => $review->ai_reviewability_reason,
            'needScore' => $review->needScore(),
            'reviewedAt' => ($review->review_finished_at ?? $review->review_started_at)?->toIso8601String(),
            // Seit dem Review gepusht: der geprüfte Stand ist nicht mehr der aktuelle.
            'stale' => $review->commit_sha !== null && $pr->last_commit !== null
                && ! Str::startsWith($pr->last_commit, $review->commit_sha)
                && ! Str::startsWith($review->commit_sha, $pr->last_commit),
        ];
    }

    private function md(string $content): string
    {
        return Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
    }
}
