<?php

namespace App\Http\Controllers\Api;

use App\Enums\PullRequestState;
use App\Enums\ReviewFindingLevel;
use App\Enums\ReviewFindingType;
use App\Enums\ReviewRecommendation;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use App\Support\PullRequestApprovals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * PR-Reviews aus dem Skill ps-review: erst der PR (UPSERT über Repo + Nummer),
 * dann je Review Start und Abschluss (mit Findings). Alles hängt an der Organisation
 * des Token-Users.
 */
class PullRequestController extends ApiController
{
    /**
     * PUT /api/pull-requests — legt den PR an oder aktualisiert ihn.
     */
    public function upsert(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $data = $request->validate([
            'repository' => ['required', 'string', 'max:200', 'regex:#^[\w.-]+/[\w.-]+$#'],
            'pull_request_id' => ['required', 'integer', 'min:1'],
            'author_login' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:500'],
            'branch' => ['required', 'string', 'max:255'],
            'target_branch' => ['required', 'string', 'max:255'],
            'last_commit' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string'],
            'state' => ['sometimes', Rule::enum(PullRequestState::class)],
            'changed_files' => ['sometimes', 'integer', 'min:0'],
            'additions' => ['sometimes', 'integer', 'min:0'],
            'deletions' => ['sometimes', 'integer', 'min:0'],
            'commits' => ['sometimes', 'integer', 'min:0'],
            'comments' => ['sometimes', 'integer', 'min:0'],
            'review_comments' => ['sometimes', 'integer', 'min:0'],
            'opened_at' => ['nullable', 'date'],
            'merged_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            // Rohdaten von GitHub; ausgewertet wird auf dem Server.
            'github_reviews' => ['sometimes', 'array'],
            'github_reviews.*.login' => ['required', 'string', 'max:100'],
            'github_reviews.*.state' => ['required', 'string', 'max:30'],
            'github_reviews.*.submitted_at' => ['nullable', 'date'],
            'github_reviews.*.commit_id' => ['nullable', 'string', 'max:40'],
            'approval_check_summary' => ['sometimes', 'nullable', 'string'],
        ]);

        $attributes = collect($data)->except(['github_reviews', 'approval_check_summary'])->all();
        $attributes['author_id'] = PullRequest::resolveAuthor($organizationId, $data['author_login'] ?? null)?->id;

        if (array_key_exists('approval_check_summary', $data)) {
            $attributes['review_requirement'] = PullRequestApprovals::parseRequirement($data['approval_check_summary']);
            $attributes['approval_groups'] = PullRequestApprovals::parseGroups($data['approval_check_summary']);
        }

        $pullRequest = PullRequest::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'repository' => $data['repository'],
                'pull_request_id' => $data['pull_request_id'],
            ],
            $attributes,
        );

        if (array_key_exists('github_reviews', $data)) {
            PullRequestApprovals::sync($pullRequest, collect($data['github_reviews'])->map(fn (array $r) => [
                'login' => $r['login'],
                'state' => $r['state'],
                'submitted_at' => $r['submitted_at'] ?? null,
                'commit_sha' => $r['commit_id'] ?? null,
            ]));
        }

        return response()->json(
            ['data' => $this->pullRequestPayload($pullRequest)],
            $pullRequest->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * POST /api/pull-requests/{pullRequest}/reviews — vermerkt den Start eines
     * Reviews; Bewertung und Findings folgen mit finishReview.
     */
    public function startReview(Request $request, PullRequest $pullRequest): JsonResponse
    {
        abort_unless($pullRequest->organization_id === $this->organizationId($request), 404);

        $data = $request->validate([
            'commit_sha' => ['nullable', 'string', 'max:40'],
            'strictness' => ['nullable', 'string', 'max:20'],
            'thoroughness' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        $review = $pullRequest->reviews()->create([
            ...$data,
            'reviewer_id' => $request->user()->id,
            'review_started_at' => now(),
        ]);

        return response()->json(['data' => $this->reviewPayload($review, $pullRequest)], 201);
    }

    /**
     * PUT /api/pull-requests/{pullRequest}/reviews/{review} — schließt das Review
     * mit Bewertung und Findings ab. Die Zähler leiten sich aus den Findings ab.
     */
    public function finishReview(Request $request, PullRequest $pullRequest, PullRequestReview $review): JsonResponse
    {
        abort_unless($pullRequest->organization_id === $this->organizationId($request), 404);
        abort_unless($review->pull_request_id === $pullRequest->id, 404);
        abort_unless($review->reviewer_id === $request->user()->id, 403);

        if ($review->isFinished()) {
            return response()->json(['message' => 'Das Review ist bereits abgeschlossen.'], 409);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::enum(ReviewRecommendation::class)],
            'criticality' => ['required', 'integer', 'between:0,100'],
            'criticality_reason' => ['nullable', 'string', 'max:500'],
            'ai_reviewability' => ['required', 'integer', 'between:0,100'],
            'ai_reviewability_reason' => ['nullable', 'string', 'max:500'],
            'po_review_likelihood' => ['nullable', 'integer', 'between:0,100'],
            'po_review_likelihood_reason' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string'],
            'commit_sha' => ['nullable', 'string', 'max:40'],
            'strictness' => ['nullable', 'string', 'max:20'],
            'thoroughness' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string', 'max:100'],
            'findings' => ['present', 'array'],
            'findings.*.level' => ['required', Rule::enum(ReviewFindingLevel::class)],
            'findings.*.type' => ['required', Rule::enum(ReviewFindingType::class)],
            'findings.*.requires_po_decision' => ['sometimes', 'boolean'],
            'findings.*.file' => ['nullable', 'required_if:findings.*.type,INLINE', 'string', 'max:500'],
            'findings.*.line' => ['nullable', 'required_if:findings.*.type,INLINE', 'integer', 'min:1'],
            'findings.*.title' => ['required', 'string', 'max:300'],
            'findings.*.message' => ['required', 'string'],
        ]);

        $findings = collect($data['findings']);
        $count = fn (ReviewFindingLevel $level) => $findings->where('level', $level->value)->count();

        DB::transaction(function () use ($review, $data, $findings, $count) {
            $review->update([
                ...collect($data)->except('findings')->all(),
                'review_finished_at' => now(),
                'findings' => $findings->count(),
                'findings_critical' => $count(ReviewFindingLevel::CRITICAL),
                'findings_important' => $count(ReviewFindingLevel::IMPORTANT),
                'findings_hint' => $count(ReviewFindingLevel::HINT),
            ]);

            $review->findingItems()->createMany($findings->map(fn (array $finding) => [
                'level' => $finding['level'],
                'type' => $finding['type'],
                'requires_po_decision' => (bool) ($finding['requires_po_decision'] ?? false),
                'file' => $finding['file'] ?? null,
                'line' => $finding['line'] ?? null,
                'title' => $finding['title'],
                'message' => $finding['message'],
            ])->all());
        });

        return response()->json(['data' => $this->reviewPayload($review, $pullRequest)]);
    }

    /**
     * GET /api/pull-requests/review-states?repository=owner/name&prs=1,2,3 — der
     * eigene Review-Stand je PR für Browser-Erweiterungen (z. B. Jira):
     * none · current · outdated (seit dem Review gepusht) · running.
     */
    public function reviewStates(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'repository' => ['required', 'string', 'max:200'],
            'prs' => ['required', 'string', 'max:2000', 'regex:/^\d+(,\d+)*$/'],
        ]);
        $numbers = array_slice(array_unique(array_map('intval', explode(',', $data['prs']))), 0, 200);
        $userId = $request->user()->id;

        $pullRequests = PullRequest::query()
            ->where('organization_id', $organizationId)
            ->where('repository', $data['repository'])
            ->whereIn('pull_request_id', $numbers)
            ->with(['reviews' => fn ($q) => $q->where('reviewer_id', $userId)])
            ->get()
            ->keyBy('pull_request_id');

        $states = [];
        foreach ($numbers as $number) {
            $states[(string) $number] = $this->reviewState($pullRequests->get($number));
        }

        return response()->json(['data' => $states]);
    }

    private function reviewState(?PullRequest $pullRequest): array
    {
        if ($pullRequest === null) {
            return ['state' => 'none'];
        }

        $finished = $pullRequest->reviews->filter->isFinished()->sortByDesc('review_finished_at')->first();
        $running = $pullRequest->reviews
            ->reject->isFinished()
            ->reject->isAbandoned()
            ->sortByDesc('review_started_at')
            ->first();

        $state = ['state' => 'none', 'url' => route('pull-requests.show', $pullRequest)];

        if ($finished !== null) {
            $stale = $finished->commit_sha !== null && $pullRequest->last_commit !== null
                && ! Str::startsWith($pullRequest->last_commit, $finished->commit_sha)
                && ! Str::startsWith($finished->commit_sha, $pullRequest->last_commit);

            $state = [
                ...$state,
                'state' => $stale ? 'outdated' : 'current',
                'date' => $finished->review_finished_at?->toIso8601String(),
                'findings' => $finished->findings,
                'critical' => $finished->findings_critical,
                'important' => $finished->findings_important,
                'hints' => $finished->findings_hint,
                'recommendation' => $finished->decision?->value,
            ];
        }

        if ($running !== null) {
            $state['state'] = 'running';
            $state['runningSince'] = $running->review_started_at?->toIso8601String();
        }

        return $state;
    }

    private function organizationId(Request $request): int
    {
        $organizationId = $request->user()->organization_id;
        abort_if($organizationId === null, 403, __('flash.organization_required'));

        return $organizationId;
    }

    private function pullRequestPayload(PullRequest $pullRequest): array
    {
        return [
            'id' => $pullRequest->id,
            'repository' => $pullRequest->repository,
            'pull_request_id' => $pullRequest->pull_request_id,
            'author_id' => $pullRequest->author_id,
            'author_login' => $pullRequest->author_login,
            'state' => $pullRequest->state->value,
            'last_commit' => $pullRequest->last_commit,
            'approvals' => PullRequestApprovals::status($pullRequest, $pullRequest->approvals()->get()),
            'url' => route('pull-requests.show', $pullRequest),
        ];
    }

    private function reviewPayload(PullRequestReview $review, PullRequest $pullRequest): array
    {
        return [
            'id' => $review->id,
            'pull_request_id' => $pullRequest->id,
            'review_started_at' => $review->review_started_at?->toIso8601String(),
            'review_finished_at' => $review->review_finished_at?->toIso8601String(),
            'decision' => $review->decision?->value,
            'findings' => $review->findings,
            'findings_critical' => $review->findings_critical,
            'findings_important' => $review->findings_important,
            'findings_hint' => $review->findings_hint,
            'criticality' => $review->criticality,
            'ai_reviewability' => $review->ai_reviewability,
            'po_review_likelihood' => $review->po_review_likelihood,
            'url' => route('pull-requests.show', $pullRequest),
        ];
    }
}
