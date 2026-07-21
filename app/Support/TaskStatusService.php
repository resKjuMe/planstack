<?php

namespace App\Support;

use App\Enums\StatusRole;
use App\Models\OrgStatus;
use App\Models\Task;
use App\Models\User;

/**
 * Central, config-driven status changes for a task. Resolves the target status
 * from the organisation's configuration by ROLE (so renamed/recolored statuses
 * keep working), sets status_id as the authority, mirrors the legacy enum for
 * canonical keys, and applies the target status's configurable on-enter effects
 * (automatic assignments / field population).
 *
 * Shared by the REST API, the MCP server and the web controllers so the wired
 * actions (claim/release/analyze/…/merge/concern/split) behave identically and
 * honour each organisation's automation config. For a default-seeded org the
 * result equals the former hard-coded behaviour.
 *
 * When an organisation drives status from progress events (see
 * Organization::hasEventDrivenStatus), direct progress-status calls
 * (analyze/in_progress/in_review/done) are suppressed here so the event
 * automation stays the single source of truth — the enforcement is server-side
 * and therefore independent of whether a client picked up the config change.
 */
class TaskStatusService
{
    /**
     * The "progress" roles a direct status call may target (POST .../status:
     * analyze/in_progress/in_review/done). These — and only these — are
     * suppressed server-side when the organisation drives status from progress
     * events; claim/release/merge/concern/split keep working as direct actions.
     *
     * @var array<int, StatusRole>
     */
    private const PROGRESS_ROLES = [
        StatusRole::ANALYZING,
        StatusRole::IN_PROGRESS,
        StatusRole::IN_REVIEW,
    ];

    /**
     * Whether a direct move into $role must be suppressed because the org drives
     * status from progress events. The event automation is the sole authority
     * for progress status; a direct progress-status call would otherwise
     * override the event-assigned status. Enforced HERE (server-side) so it holds
     * no matter what a — possibly months-stale — client still sends.
     */
    public function isEventDriven(Task $task, StatusRole $role): bool
    {
        if (! in_array($role, self::PROGRESS_ROLES, true)) {
            return false;
        }

        return (bool) $task->project?->organization?->hasEventDrivenStatus();
    }

    /**
     * Whether moving the task into the status carrying $targetRole is an allowed
     * transition per the organisation's workflow. Returns true when there is
     * nothing to enforce (no org, or the current/target status is unresolved).
     * Same status is always allowed.
     */
    public function allowsTransition(Task $task, StatusRole $targetRole): bool
    {
        $organization = $task->project?->organization;
        if ($organization === null) {
            return true;
        }

        // Event-driven progress status is a server-side no-op (see applyRole);
        // never reject it as an illegal transition — the call changes no status.
        if ($this->isEventDriven($task, $targetRole)) {
            return true;
        }

        $current = $task->orgStatus;
        $target = $organization->statusForRole($targetRole);
        if ($current === null || $target === null) {
            return true;
        }

        return OrgBoardWorkflow::forOrganization($organization)->canTransition($current->key, $target->key);
    }

    /**
     * Move the task into the org status carrying $role and apply its on-enter
     * effects. $extra attributes win over the effects. Falls back to the legacy
     * enum when the org has no status for the role (unseeded).
     *
     * @param  array<string, mixed>  $extra
     */
    public function applyRole(Task $task, StatusRole $role, ?User $actor = null, array $extra = []): void
    {
        // Server-side authority: when the org drives status from progress events,
        // a direct progress-status call must NOT override the event-assigned
        // status. Only the status change is suppressed — any explicit $extra
        // field updates still apply. Holds regardless of what the client sends,
        // so a config change is honoured even by long-lived / stale clients.
        if ($this->isEventDriven($task, $role)) {
            if ($extra !== []) {
                $task->update($extra);
            }

            return;
        }

        $status = $task->project?->organization?->statusForRole($role);

        if ($status === null) {
            // Unseeded org: no status for this role — only apply the extras.
            if ($extra !== []) {
                $task->update($extra);
            }

            return;
        }

        $task->update($this->attributesFor($task, $status, $actor, $extra));
    }

    /**
     * The attribute array for moving a task into $status (status_id authority +
     * enum mirror + on-enter effects), without persisting. Useful for atomic
     * query-builder updates that bypass model events (claim-next).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function attributesFor(Task $task, OrgStatus $status, ?User $actor = null, array $extra = []): array
    {
        return array_merge(
            ['status_id' => $status->id],
            StatusEffects::resolve($task, $status, $actor),
            $extra,
        );
    }
}
