<?php

namespace App\Support;

use App\Enums\ReviewRecommendation;
use App\Enums\StatusRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Entscheidet server-seitig die nächste sinnvolle Aktion für einen Worker und
 * reserviert den betroffenen Task atomar — die Grundlage für „/planstack auto":
 * der Aufrufer kennt {action, task} schon beim Start des Subagents (Name z. B.
 * „fix RPm-req") und muss die Entscheidung nicht selbst treffen.
 *
 * Priorität: fix → review → work (Blockiertes zuerst freiräumen).
 *  - fix:    offener PR mit rotem CI / offenen Review-Threads / angeforderten
 *            Änderungen (GitHub reviewDecision oder Planstack-Review). Reserviert
 *            per Lease (fix_leased_by/…_expires_at), da fix keine natürliche
 *            Reservierung hat. Eigene Tasks eingeschlossen.
 *  - review: Task im Review-Pool mit PR, noch ohne Reviewer, nicht der eigene —
 *            reserviert per reviewed_by (wie review-next).
 *  - work:   bester pickbarer Task (meiste unlocks) — reserviert per claim (wie
 *            claim-next), inkl. CLAIMED-Statuswechsel + Board-Broadcast.
 *
 * Alle Reservierungen sind bedingte UPDATEs → parallele Worker kollidieren nicht
 * (genau einer gewinnt, die anderen fallen auf den nächsten Kandidaten).
 */
class NextActionResolver
{
    /** Rote CI-Zustände des GitHub statusCheckRollup. */
    private const CI_RED = ['FAILURE', 'ERROR'];

    public function __construct(
        private readonly TaskBoardService $board,
        private readonly TaskStatusService $statuses,
    ) {}

    /**
     * @return array{action: 'fix'|'review'|'work'|'none', task: ?Task, reason: ?string}
     */
    public function resolve(Project $project, User $user): array
    {
        return $this->tryFix($project, $user)
            ?? $this->tryReview($project, $user)
            ?? $this->tryWork($project, $user)
            ?? ['action' => 'none', 'task' => null, 'reason' => null];
    }

    /**
     * @return array{action: 'fix', task: Task, reason: string}|null
     */
    private function tryFix(Project $project, User $user): ?array
    {
        $doneStatusIds = $this->doneStatusIds($project);
        $now = now();

        $candidates = $project->tasks()
            ->whereNotNull('pr_number')
            ->when($doneStatusIds !== [], fn ($q) => $q->whereNotIn('status_id', $doneStatusIds))
            ->where(fn ($q) => $q
                ->whereIn('pr_ci_status', self::CI_RED)
                ->orWhere('pr_unresolved_threads', '>', 0)
                ->orWhere('pr_review_decision', 'CHANGES_REQUESTED')
                ->orWhere('last_review_recommendation', ReviewRecommendation::REQUEST_CHANGES->value))
            // Lease frei oder abgelaufen.
            ->where(fn ($q) => $q->whereNull('fix_leased_by')->orWhere('fix_lease_expires_at', '<', $now))
            // Ältester Stand des letzten Commits zuerst; PRs ohne Commit-Datum ans Ende.
            ->orderByRaw('pr_last_commit_at is null, pr_last_commit_at asc')
            ->orderBy('id')
            ->get();

        $ttl = max(1, (int) config('planstack.fix_lease_minutes', 15));

        foreach ($candidates as $candidate) {
            // Atomares Lease: nur greifen, wenn frei/abgelaufen. Query-Builder-Update
            // umgeht Model-Events (das Lease ist kein Board-Status, kein Broadcast).
            $leased = Task::whereKey($candidate->id)
                ->where(fn ($q) => $q->whereNull('fix_leased_by')->orWhere('fix_lease_expires_at', '<', $now))
                ->update([
                    'fix_leased_by' => $user->id,
                    'fix_lease_expires_at' => $now->copy()->addMinutes($ttl),
                ]);

            if ($leased === 1) {
                $candidate->setRelation('project', $project);

                return ['action' => 'fix', 'task' => $candidate, 'reason' => $this->fixReason($candidate)];
            }
        }

        return null;
    }

    /**
     * @return array{action: 'review', task: Task, reason: null}|null
     */
    private function tryReview(Project $project, User $user): ?array
    {
        $poolIds = $project->organization?->reviewPoolStatusIds() ?? [];
        if ($poolIds === []) {
            return null;
        }

        $uid = $user->id;

        $candidates = $project->tasks()
            ->whereIn('status_id', $poolIds)
            ->whereNotNull('pr_number')
            ->whereNull('reviewed_by')
            // Eigene Tasks nicht zum Review picken.
            ->where(fn ($q) => $q->whereNull('claimed_by_id')->orWhere('claimed_by_id', '!=', $uid))
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            $claimed = Task::whereKey($candidate->id)
                ->whereNull('reviewed_by')
                ->update(['reviewed_by' => $uid]);

            if ($claimed === 1) {
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return ['action' => 'review', 'task' => $candidate, 'reason' => null];
            }
        }

        return null;
    }

    /**
     * @return array{action: 'work', task: Task, reason: null}|null
     */
    private function tryWork(Project $project, User $user): ?array
    {
        $candidates = $this->board->board($project)
            ->filter(fn ($t) => $t->x_pickable)
            ->sortByDesc('x_unlocks')
            ->values();

        $claimedStatus = $project->organization?->statusForRole(StatusRole::CLAIMED);

        foreach ($candidates as $candidate) {
            $attrs = $claimedStatus !== null
                ? $this->statuses->attributesFor($candidate, $claimedStatus, $user)
                : ['claimed_by_id' => $user->id, 'claimed_at' => now(), 'status' => TaskStatus::CLAIMED->value];

            $claimed = Task::whereKey($candidate->id)
                ->whereNull('claimed_by_id')
                ->whereNull('pr_number')
                ->update($attrs);

            if ($claimed === 1) {
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return ['action' => 'work', 'task' => $candidate, 'reason' => null];
            }
        }

        return null;
    }

    /**
     * Kurzbegründung, warum ein Task zum Fix ansteht (für Logs / Subagent-Name).
     */
    private function fixReason(Task $task): string
    {
        $reasons = [];

        if (in_array($task->pr_ci_status, self::CI_RED, true)) {
            $reasons[] = 'CI '.$task->pr_ci_status;
        }
        if (($task->pr_unresolved_threads ?? 0) > 0) {
            $reasons[] = $task->pr_unresolved_threads.' unresolved threads';
        }
        if ($task->pr_review_decision === 'CHANGES_REQUESTED') {
            $reasons[] = 'changes requested';
        } elseif ($task->last_review_recommendation === ReviewRecommendation::REQUEST_CHANGES) {
            $reasons[] = 'review: changes requested';
        }

        return implode(', ', $reasons) ?: 'needs fix';
    }

    /**
     * Status-IDs der Org, die als „erledigt" gelten (Rolle MERGED/COMPLETED) —
     * solche Tasks kommen nicht mehr für einen Fix in Frage.
     *
     * @return array<int, int>
     */
    private function doneStatusIds(Project $project): array
    {
        return $project->organization?->statuses()
            ->whereIn('role', [StatusRole::MERGED->value, StatusRole::COMPLETED->value])
            ->pluck('id')
            ->all() ?? [];
    }
}
