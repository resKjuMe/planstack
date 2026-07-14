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
            self::CONCERNED => 'problematisch',
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
