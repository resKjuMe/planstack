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
 */
class TaskStatusService
{
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
