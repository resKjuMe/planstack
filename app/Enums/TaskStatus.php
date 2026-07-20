<?php

namespace App\Enums;

enum TaskStatus: string
{
    case UNKNOWN = 'UNKNOWN';
    case BLOCKED = 'BLOCKED';
    case CONCERNED = 'CONCERNED';
    case PICKABLE = 'PICKABLE';
    case CLAIMED = 'CLAIMED';
    case ANALYZING = 'ANALYZING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case IN_REVIEW = 'IN_REVIEW';
    case COMPLETED = 'COMPLETED';
    case MERGED = 'MERGED';

    /**
     * German label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN => __('enums.status_unknown'),
            self::BLOCKED => __('enums.status_blocked'),
            self::CONCERNED => __('enums.status_concerned'),
            self::PICKABLE => __('enums.status_pickable'),
            self::CLAIMED => __('enums.status_claimed'),
            self::ANALYZING => __('enums.status_analyzing'),
            self::IN_PROGRESS => __('enums.status_in_progress'),
            self::IN_REVIEW => __('enums.status_in_review'),
            self::COMPLETED => __('enums.status_completed'),
            self::MERGED => __('enums.status_merged'),
        };
    }

    /**
     * Tailwind fill class for a status bar segment. Single source of truth shared
     * by der Summary (Phasen-Balken) und der Projekte-Übersicht.
     */
    public function barClasses(): string
    {
        return match ($this) {
            self::UNKNOWN => 'bg-gray-300',
            self::BLOCKED => 'bg-rose-400',
            self::CONCERNED => 'bg-red-500',
            self::PICKABLE => 'bg-indigo-400',
            self::CLAIMED => 'bg-sky-400',
            self::ANALYZING => 'bg-blue-400',
            self::IN_PROGRESS => 'bg-blue-700',
            self::IN_REVIEW => 'bg-purple-500',
            self::COMPLETED => 'bg-green-500',
            self::MERGED => 'bg-emerald-500',
        };
    }

    /**
     * Tailwind-Textfarbe passend zur Segment-Farbe (barClasses) — für die
     * Fortschritts-Zahl beim Hover eines Balken-Segments.
     */
    public function textClasses(): string
    {
        return match ($this) {
            self::UNKNOWN => 'text-gray-500 dark:text-gray-400',
            self::BLOCKED => 'text-rose-500 dark:text-rose-400',
            self::CONCERNED => 'text-red-600 dark:text-red-400',
            self::PICKABLE => 'text-indigo-500 dark:text-indigo-400',
            self::CLAIMED => 'text-sky-600 dark:text-sky-400',
            self::ANALYZING => 'text-blue-500 dark:text-blue-400',
            self::IN_PROGRESS => 'text-blue-700 dark:text-blue-400',
            self::IN_REVIEW => 'text-purple-600 dark:text-purple-400',
            self::COMPLETED => 'text-green-600 dark:text-green-400',
            self::MERGED => 'text-emerald-600 dark:text-emerald-400',
        };
    }

    /**
     * Logischer Lifecycle: am weitesten fertig → am offensten. Reihenfolge der
     * Segmente im gestapelten Status-Balken.
     *
     * @return array<int, self>
     */
    public static function displayOrder(): array
    {
        return [
            self::MERGED,
            self::COMPLETED,
            self::IN_REVIEW,
            self::IN_PROGRESS,
            self::ANALYZING,
            self::CLAIMED,
            self::PICKABLE,
            self::CONCERNED,
            self::BLOCKED,
            self::UNKNOWN,
        ];
    }

    /**
     * The semantic family of a status. Bundles the meaning that was previously
     * scattered across in_array()/match checks so the later move to dynamic,
     * per-organization statuses only has to touch this one mapping. A dynamic
     * status will carry the same `kind` values.
     *
     * waiting   – not started yet (UNKNOWN/PICKABLE)
     * exception – off the regular flow (BLOCKED derived, CONCERNED explicit)
     * active    – claimed and being worked on (CLAIMED/ANALYZING/IN_PROGRESS)
     * review    – in review (IN_REVIEW)
     * done      – finished (COMPLETED/MERGED)
     */
    public function kind(): string
    {
        return match ($this) {
            self::UNKNOWN, self::PICKABLE => 'waiting',
            self::BLOCKED, self::CONCERNED => 'exception',
            self::CLAIMED, self::ANALYZING, self::IN_PROGRESS => 'active',
            self::IN_REVIEW => 'review',
            self::COMPLETED, self::MERGED => 'done',
        };
    }

    /**
     * Fully closed — COMPLETED or MERGED. Drives progress/done markers. (Gate
     * satisfaction additionally counts an open PR; see TaskBoardService.)
     */
    public function isDone(): bool
    {
        return $this->kind() === 'done';
    }

    /**
     * Off-flow exception state (BLOCKED or CONCERNED).
     */
    public function isException(): bool
    {
        return $this->kind() === 'exception';
    }

    /**
     * A waiting state (UNKNOWN/PICKABLE) whose effective board status is derived
     * from the gate rather than stored authoritatively.
     */
    public function isWaiting(): bool
    {
        return $this->kind() === 'waiting';
    }

    /**
     * An explicit lifecycle state that is kept as-is on the board (not re-derived
     * from the gate). Everything except the waiting states and the derived
     * BLOCKED — i.e. CONCERNED plus active/review/done.
     */
    public function isExplicit(): bool
    {
        return ! in_array($this, [self::UNKNOWN, self::PICKABLE, self::BLOCKED], true);
    }

    /**
     * Tailwind classes for a status badge. Single source of truth shared by the
     * Summary and PR-sequence views so their status colours never drift apart.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::UNKNOWN => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            self::BLOCKED => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            self::CONCERNED => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            self::PICKABLE => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
            self::CLAIMED => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
            self::ANALYZING => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            self::IN_PROGRESS => 'bg-blue-200 text-blue-900 dark:bg-blue-900/50 dark:text-blue-200',
            self::IN_REVIEW => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
            self::COMPLETED => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
            self::MERGED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        };
    }
}
