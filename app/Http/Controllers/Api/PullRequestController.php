<?php

namespace App\Http\Controllers\Api;

use App\Enums\PullRequestState;
use App\Enums\ReviewFindingLevel;
use App\Enums\ReviewFindingType;
use App\Enums\ReviewRecommendation;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * PR-Reviews aus dem Skill ps-review: erst der PR (UPSERT über Repo + Nummer),
 * dann je Review ein Datensatz samt Findings. Alles hängt an der Organisation
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
        ]);

        $data['author_id'] = PullRequest::resolveAuthor($organizationId, $data['author_login'] ?? null)?->id;

        $pullRequest = PullRequest::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'repository' => $data['repository'],
                'pull_request_id' => $data['pull_request_id'],
            ],
            $data,
        );

        return response()->json(
            ['data' => $this->pullRequestPayload($pullRequest)],
            $pullRequest->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * POST /api/pull-requests/{pullRequest}/reviews — ein Review mit Findings.
     * Die Zähler leiten sich aus den Findings ab.
     */
    public function storeReview(Request $request, PullRequest $pullRequest): JsonResponse
    {
        abort_unless($pullRequest->organization_id === $this->organizationId($request), 404);

        $data = $request->validate([
            'decision' => ['required', Rule::enum(ReviewRecommendation::class)],
            'criticality' => ['required', 'integer', 'between:0,100'],
            'criticality_reason' => ['nullable', 'string', 'max:500'],
            'ai_reviewability' => ['required', 'integer', 'between:0,100'],
            'ai_reviewability_reason' => ['nullable', 'string', 'max:500'],
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

        $review = DB::transaction(function () use ($request, $pullRequest, $data, $findings, $count) {
            $review = $pullRequest->reviews()->create([
                ...collect($data)->except('findings')->all(),
                'reviewer_id' => $request->user()->id,
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

            return $review;
        });

        return response()->json(['data' => $this->reviewPayload($review, $pullRequest)], 201);
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
            'url' => route('pull-requests.show', $pullRequest),
        ];
    }

    private function reviewPayload(PullRequestReview $review, PullRequest $pullRequest): array
    {
        return [
            'id' => $review->id,
            'pull_request_id' => $pullRequest->id,
            'decision' => $review->decision->value,
            'findings' => $review->findings,
            'findings_critical' => $review->findings_critical,
            'findings_important' => $review->findings_important,
            'findings_hint' => $review->findings_hint,
            'criticality' => $review->criticality,
            'ai_reviewability' => $review->ai_reviewability,
            'url' => route('pull-requests.show', $pullRequest),
        ];
    }
}
