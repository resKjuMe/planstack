<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\Task;
use App\Models\User;

/**
 * Resolves a status's configurable "on enter" effects (automatic assignments +
 * field population) into a task attribute array. Tokens: @actor (current user),
 * @now (timestamp), @clear (null); anything else is a literal. Fields are
 * restricted to a safe allow-list.
 */
class StatusEffects
{
    /**
     * Task fields an automation may set — the allow-list behind both effect
     * dropdowns (status on-enter effects and per-event effects) and their
     * validation.
     *
     * Deliberately excluded:
     *  - status / status_id — a status change triggering status changes loops;
     *  - id, project_id, created_by_id, name, created_at, updated_at — identity
     *    and audit columns;
     *  - claim_session_label, claim_seen_at — derived from claimed_by_id by
     *    {@see ClaimSession::stamp()}, so setting them by hand would be undone;
     *  - custom_fields — a JSON map, not a scalar an effect could carry.
     *
     * Note: the pr_* fields are also written by the GitHub status sync, which
     * wins on its next run.
     */
    public const ALLOWED_FIELDS = [
        // Zuordnung / Einordnung
        'claimed_by_id', 'claimed_at', 'merged_at', 'criticality', 'phase_id',
        // Beschreibungstexte
        'summary', 'description', 'description_acceptance_criteria',
        'description_target_actual', 'description_test_cases',
        // Aufwand
        'effort_man_days', 'effort_story_points', 'effort_tokens', 'affected_files',
        // Review
        'reviewed_by', 'last_reviewed_at', 'last_review_recommendation',
        'last_review_summary',
        // Fix-Lease
        'fix_leased_by', 'fix_lease_expires_at',
        // Pull Request
        'pr_number', 'pr_node_id', 'pr_title', 'pr_ci_status', 'pr_ci_failed',
        'pr_ci_running', 'pr_ci_success', 'pr_ci_waiting', 'pr_in_merge_queue',
        'pr_merge_queue_state', 'pr_mergeable', 'pr_unresolved_threads',
        'pr_review_decision', 'pr_last_commit_at', 'pr_status_synced_at',
    ];

    /**
     * @return array<string, mixed> attributes to merge into the task update
     */
    public static function resolve(Task $task, OrgStatus $status, ?User $actor): array
    {
        return self::resolveEffects($task, $status->on_enter_effects ?? [], $actor);
    }

    /**
     * Resolve an arbitrary effects list (same shape as on_enter_effects) into a
     * task attribute array. Shared by the status on-enter effects and the
     * per-event automation effects (see docs/event-api.md). Tokens: @actor,
     * @now, @clear; anything else is a literal. Fields outside the allow-list are
     * skipped.
     *
     * @param  iterable<int, array<string, mixed>>  $effects
     * @return array<string, mixed>
     */
    public static function resolveEffects(Task $task, iterable $effects, ?User $actor): array
    {
        $attrs = [];

        foreach ($effects as $effect) {
            $field = $effect['field'] ?? null;
            if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                continue;
            }
            if (($effect['only_if_empty'] ?? false) && $task->{$field} !== null) {
                continue;
            }

            $value = $effect['value'] ?? null;
            $attrs[$field] = match ($value) {
                '@actor' => $actor?->id,
                '@now' => now(),
                '@clear' => null,
                default => $value,
            };
        }

        // Das Session-Lease hängt am Claim und wird deshalb HIER mitgezogen — an
        // der Stelle, an der Claim-Änderungen entstehen, nicht in den Endpunkten.
        // Alle Wege laufen hier durch: Status-Wechsel (TaskStatusService),
        // Event-Automationen (TaskEventService) und der Board-Move per DnD. Ohne
        // das würde ein Zug auf „Pickable" den Assignee räumen, aber Label und
        // Heartbeat stehen lassen — die Karte behauptete weiter eine Session.
        // Effekte, die claimed_by_id nicht anfassen, bleiben unberührt.
        return app(ClaimSession::class)->stamp($attrs);
    }
}
