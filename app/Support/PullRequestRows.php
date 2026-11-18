<?php

namespace App\Support;

use App\Enums\PullRequestState;
use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use App\Models\PullRequestReview;
use App\Models\ReviewFinding;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

/**
 * Zeilen der PR-Liste (/pull-requests): PR-Kopf, Freigaben und letztes Review.
 * Auch Daily nutzt sie, damit dort dieselben Spalten stehen.
 */
class PullRequestRows
{
    /** Relationen, die {@see full()} braucht. */
    public const RELATIONS = ['author', 'latestReview', 'approvals.user'];

    /**
     * Setzt am letzten Review die Zahl der Befunde mit PO-Entscheidung (eine Abfrage).
     *
     * @param  EloquentCollection<int, PullRequest>  $pullRequests  mit geladenem `latestReview`
     */
    public static function withPoCounts(EloquentCollection $pullRequests): void
    {
        $counts = ReviewFinding::query()
            ->whereIn('pull_request_review_id', $pullRequests->pluck('latestReview.id')->filter())
            ->where('requires_po_decision', true)
            ->groupBy('pull_request_review_id')
            ->selectRaw('pull_request_review_id, count(*) as aggregate')
            ->pluck('aggregate', 'pull_request_review_id');

        $pullRequests->each(fn (PullRequest $pr) => $pr->latestReview?->setAttribute('po_decisions_count', (int) ($counts[$pr->latestReview->id] ?? 0)));
    }

    /**
     * PR-Kopf, Freigaben und letztes Review in einem Eintrag.
     *
     * @return array<string, mixed>
     */
    public static function full(PullRequest $pr): array
    {
        return [
            ...self::row($pr),
            'approvals' => self::approvals($pr),
            'review' => $pr->latestReview ? self::review($pr->latestReview, $pr) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(PullRequest $pr): array
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
            'ciState' => $pr->ci_state,
            'ciFailedChecks' => $pr->ci_failed_checks ?? [],
            'conflicting' => $pr->mergeable === 'CONFLICTING',
            'inMergeQueue' => $pr->state === PullRequestState::OPEN && $pr->in_merge_queue,
            'unresolvedThreads' => (int) $pr->unresolved_threads,
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
     * @return array<string, mixed>
     */
    public static function approvals(PullRequest $pr): array
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

    /**
     * @return array<string, mixed>
     */
    public static function review(PullRequestReview $review, PullRequest $pr): array
    {
        return [
            'id' => $review->id,
            'status' => $review->isFinished() ? 'finished' : ($review->isAbandoned($pr->relationLoaded('reviews') ? $pr->reviews : null) ? 'abandoned' : 'running'),
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
            'poReviewLikelihood' => $review->po_review_likelihood,
            'poReviewLikelihoodReason' => $review->po_review_likelihood_reason,
            'needScore' => $review->needScore(),
            // Befunde, die eine fachliche Entscheidung des PO brauchen.
            'poDecisions' => $review->po_decisions_count
                ?? ($review->relationLoaded('findingItems') ? $review->findingItems->where('requires_po_decision', true)->count() : 0),
            'reviewedAt' => ($review->review_finished_at ?? $review->review_started_at)?->toIso8601String(),
            // Seit dem Review gepusht: der geprüfte Stand ist nicht mehr der aktuelle.
            'stale' => $review->commit_sha !== null && $pr->last_commit !== null
                && ! Str::startsWith($pr->last_commit, $review->commit_sha)
                && ! Str::startsWith($review->commit_sha, $pr->last_commit),
        ];
    }
}
