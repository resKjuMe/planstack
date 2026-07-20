<?php

namespace App\Support;

use App\Enums\TaskStatus;

/**
 * Central, server-side Kanban workflow definition — the single source of truth
 * shared by the React board (shipped to the client as JSON via toArray()) and
 * the server-side transition validation in the board move endpoint. Keeping it
 * here (not hard-coded in the React component) means columns, allowed
 * transitions, WIP limits and collapse groups never drift between client and
 * server.
 *
 * Scope note: the workflow is defined client-side-as-config per the task brief;
 * this PHP class is that config's canonical home so the server can also enforce
 * it. It does not touch the status/enum data model.
 */
class BoardWorkflow
{
    /**
     * The regular flow statuses rendered as board columns, left → right. BLOCKED
     * and CONCERNED are deliberately absent: they are exception states shown in a
     * dedicated left-hand lane (see EXCEPTION_STATUSES), not columns.
     *
     * @var array<int, TaskStatus>
     */
    public const COLUMN_ORDER = [
        TaskStatus::UNKNOWN,
        TaskStatus::PICKABLE,
        TaskStatus::CLAIMED,
        TaskStatus::ANALYZING,
        TaskStatus::IN_PROGRESS,
        TaskStatus::IN_REVIEW,
        // MERGED vor COMPLETED (bewusste Board-Reihenfolge, unabhaengig vom
        // Lifecycle in TaskStatus::displayOrder()).
        TaskStatus::MERGED,
        TaskStatus::COMPLETED,
    ];

    /**
     * Statuses handled off-flow, collected in the left-hand exception lane.
     *
     * @var array<int, TaskStatus>
     */
    public const EXCEPTION_STATUSES = [
        TaskStatus::BLOCKED,
        TaskStatus::CONCERNED,
    ];

    /**
     * Allowed status transitions (drag-and-drop is the primary status change).
     * from → [allowed targets]. Anything not listed is rejected both client-side
     * (target column is not a drop target) and server-side (422). Reverse moves
     * are intentionally allowed for corrections.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            TaskStatus::UNKNOWN->value => [TaskStatus::PICKABLE->value, TaskStatus::CLAIMED->value],
            TaskStatus::PICKABLE->value => [TaskStatus::CLAIMED->value, TaskStatus::UNKNOWN->value],
            TaskStatus::CLAIMED->value => [TaskStatus::ANALYZING->value, TaskStatus::IN_PROGRESS->value, TaskStatus::PICKABLE->value],
            TaskStatus::ANALYZING->value => [TaskStatus::IN_PROGRESS->value, TaskStatus::IN_REVIEW->value, TaskStatus::CLAIMED->value],
            TaskStatus::IN_PROGRESS->value => [TaskStatus::IN_REVIEW->value, TaskStatus::COMPLETED->value, TaskStatus::ANALYZING->value],
            TaskStatus::IN_REVIEW->value => [TaskStatus::COMPLETED->value, TaskStatus::IN_PROGRESS->value],
            TaskStatus::COMPLETED->value => [TaskStatus::MERGED->value, TaskStatus::IN_REVIEW->value],
            TaskStatus::MERGED->value => [TaskStatus::COMPLETED->value],
            // Exception states can always return to the regular flow.
            TaskStatus::BLOCKED->value => [TaskStatus::PICKABLE->value, TaskStatus::CLAIMED->value],
            TaskStatus::CONCERNED->value => [TaskStatus::PICKABLE->value, TaskStatus::CLAIMED->value],
        ];
    }

    /**
     * Optional per-column WIP limits (soft limits: exceeding them only warns
     * visually, never blocks a drop). Columns without an entry are unlimited.
     *
     * @return array<string, int>
     */
    public static function wipLimits(): array
    {
        return [
            TaskStatus::IN_REVIEW->value => 3,
            TaskStatus::IN_PROGRESS->value => 5,
        ];
    }

    /**
     * Collapse groups: consecutive columns that may be folded into a single
     * collapsed bar. Configurable here rather than hard-coded in the component.
     *
     * @return array<int, array{key: string, label: string, statuses: array<int, string>}>
     */
    public static function collapseGroups(): array
    {
        return [
            [
                'key' => 'backlog',
                'label' => __('board.group_backlog'),
                'statuses' => [TaskStatus::UNKNOWN->value, TaskStatus::PICKABLE->value],
            ],
            [
                'key' => 'in_work',
                'label' => __('board.group_in_work'),
                'statuses' => [TaskStatus::CLAIMED->value, TaskStatus::ANALYZING->value, TaskStatus::IN_PROGRESS->value],
            ],
        ];
    }

    /**
     * How many MERGED cards to show before the "show all" button, and after how
     * many days a merged card is hidden by default (with a header toggle).
     */
    public const MERGED_INITIAL_LIMIT = 5;

    public const MERGED_STALE_DAYS = 7;

    /**
     * Whether a transition from → to is permitted.
     */
    public static function canTransition(TaskStatus $from, TaskStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::transitions()[$from->value] ?? [], true);
    }

    /**
     * The full workflow definition as a plain array for the React board.
     *
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'columnOrder' => array_map(fn (TaskStatus $s) => $s->value, self::COLUMN_ORDER),
            'exceptionStatuses' => array_map(fn (TaskStatus $s) => $s->value, self::EXCEPTION_STATUSES),
            'transitions' => self::transitions(),
            'wipLimits' => self::wipLimits(),
            'collapseGroups' => self::collapseGroups(),
            'mergedInitialLimit' => self::MERGED_INITIAL_LIMIT,
            'mergedStaleDays' => self::MERGED_STALE_DAYS,
            'labels' => collect([...self::COLUMN_ORDER, ...self::EXCEPTION_STATUSES])
                ->mapWithKeys(fn (TaskStatus $s) => [$s->value => $s->label()])
                ->all(),
        ];
    }
}
