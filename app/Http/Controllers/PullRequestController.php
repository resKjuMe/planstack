<?php

namespace App\Http\Controllers;

use App\Models\PullRequest;
use App\Models\PullRequestReview;
use App\Models\ReviewFinding;
use Illuminate\Database\Eloquent\Builder;
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

    private const ROLES = ['reviewed', 'authored'];

    private const LIMIT = 500;

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $state = in_array($request->query('state'), self::STATES, true) ? $request->query('state') : 'open';
        $role = in_array($request->query('role'), self::ROLES, true) ? $request->query('role') : 'reviewed';

        $pullRequests = PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->when($role === 'reviewed', fn (Builder $q) => $q->whereHas('reviews', fn (Builder $r) => $r->where('reviewer_id', $user->id)))
            ->when($role === 'authored', fn (Builder $q) => $q->where('author_id', $user->id))
            ->when($state !== 'all', fn (Builder $q) => $q->where('state', strtoupper($state)))
            ->with(['author', 'latestReview', 'runningReview.reviewer'])
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
                'review' => $pr->latestReview ? $this->reviewRow($pr->latestReview, $pr) : null,
                'running' => $pr->runningReview ? [
                    'startedAt' => $pr->runningReview->review_started_at?->toIso8601String(),
                    'reviewer' => $pr->runningReview->reviewer?->name,
                ] : null,
            ])->values(),
            'strings' => __('pull_requests'),
        ]);
    }

    public function show(Request $request, PullRequest $pullRequest): InertiaResponse
    {
        abort_unless($pullRequest->organization_id === $request->user()->organization_id, 404);

        $pullRequest->load(['author', 'reviews' => fn ($q) => $q->latest('review_started_at')->latest('id'), 'reviews.reviewer', 'reviews.findingItems']);

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
