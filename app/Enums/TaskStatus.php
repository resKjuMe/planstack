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
            self::UNKNOWN => 'ausstehend',
            self::BLOCKED => 'blockiert',
            self::CONCERNED => 'kritisch',
            self::PICKABLE => 'pickbar',
            self::CLAIMED => 'beansprucht',
            self::ANALYZING => 'in Analyse',
            self::IN_PROGRESS => 'in Arbeit',
            self::IN_REVIEW => 'in Review',
            self::COMPLETED => 'erledigt',
            self::MERGED => 'gemerged',
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
            self::UNKNOWN => 'text-gray-500',
            self::BLOCKED => 'text-rose-500',
            self::CONCERNED => 'text-red-600',
            self::PICKABLE => 'text-indigo-500',
            self::CLAIMED => 'text-sky-600',
            self::ANALYZING => 'text-blue-500',
            self::IN_PROGRESS => 'text-blue-700',
            self::IN_REVIEW => 'text-purple-600',
            self::COMPLETED => 'text-green-600',
            self::MERGED => 'text-emerald-600',
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
     * Tailwind classes for a status badge. Single source of truth shared by the
     * Summary and PR-sequence views so their status colours never drift apart.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::UNKNOWN => 'bg-gray-100 text-gray-600',
            self::BLOCKED => 'bg-rose-100 text-rose-700',
            self::CONCERNED => 'bg-red-100 text-red-800',
            self::PICKABLE => 'bg-indigo-100 text-indigo-700',
            self::CLAIMED => 'bg-sky-100 text-sky-700',
            self::ANALYZING => 'bg-blue-100 text-blue-700',
            self::IN_PROGRESS => 'bg-blue-200 text-blue-900',
            self::IN_REVIEW => 'bg-purple-100 text-purple-700',
            self::COMPLETED => 'bg-green-100 text-green-700',
            self::MERGED => 'bg-emerald-100 text-emerald-800',
        };
    }
}
