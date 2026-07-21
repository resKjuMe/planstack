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
     * Task fields an automation may set. Deliberately excludes status/status_id
     * (to avoid loops) and identity/audit columns.
     */
    public const ALLOWED_FIELDS = [
        'claimed_by_id', 'claimed_at', 'merged_at', 'reviewed_by', 'pr_number',
        'criticality', 'phase_id', 'effort_story_points', 'effort_man_days',
        'affected_files',
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

        return $attrs;
    }
}
