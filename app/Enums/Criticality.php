<?php

namespace App\Enums;

/**
 * Wie kritisch eine Task-Änderung ist (Risiko/Blast-Radius). Gespeichert werden
 * stabile englische Keys, angezeigt die deutschen Labels — wie bei TaskStatus.
 */
enum Criticality: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * Deutsches Label für die Anzeige.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => __('enums.criticality_low'),
            self::MEDIUM => __('enums.criticality_medium'),
            self::HIGH => __('enums.criticality_high'),
            self::CRITICAL => __('enums.criticality_critical'),
        };
    }

    /**
     * Tailwind-Klassen für ein Badge (grau → blau → amber → rot).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOW => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            self::MEDIUM => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            self::HIGH => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::CRITICAL => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        };
    }
}
