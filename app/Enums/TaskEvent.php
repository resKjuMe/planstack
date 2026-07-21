<?php

namespace App\Enums;

/**
 * Fortschritts-Events, die der /planstack-Skill (und der "Sync"-Button) während
 * der Abarbeitung an Planstack sendet (siehe docs/event-api.md). Ein Event ist
 * eine reine Meldung — was es bewirkt (Statuswechsel, Feld-Befüllung), legt jede
 * Organisation je Event selbst fest (App\Models\OrgEventAutomation). Ohne
 * Konfiguration ist ein Event ein No-op (nur ins Log geschrieben).
 */
enum TaskEvent: string
{
    // Task-Abarbeitung
    case CLAIMING = 'CLAIMING';
    case CLAIMED = 'CLAIMED';
    case ANALYZING = 'ANALYZING';
    case ANALYZED = 'ANALYZED';
    case PROCESSING = 'PROCESSING';
    case PROCESSED = 'PROCESSED';
    case PUBLISHING = 'PUBLISHING';
    case PUBLISHED = 'PUBLISHED';
    case POLISHING = 'POLISHING';
    case POLISHED = 'POLISHED';
    case CONCERNED = 'CONCERNED';
    case UNCLAIMING = 'UNCLAIMING';
    case UNCLAIMED = 'UNCLAIMED';

    // Review
    case REVIEWING = 'REVIEWING';
    case REVIEWED = 'REVIEWED';
    case APPROVED = 'APPROVED';
    case CHANGES_REQUESTED = 'CHANGES_REQUESTED';

    // Merge / Deploy ("Sync")
    case MERGING_QUEUED = 'MERGING_QUEUED';
    case MERGING_FAILED = 'MERGING_FAILED';
    case MERGED = 'MERGED';
    case DEPLOYED = 'DEPLOYED';

    /**
     * Thematische Gruppe für die Gliederung in der Organisations-Verwaltung.
     */
    public function group(): string
    {
        return match ($this) {
            self::CLAIMING, self::CLAIMED, self::ANALYZING, self::ANALYZED,
            self::PROCESSING, self::PROCESSED, self::PUBLISHING, self::PUBLISHED,
            self::POLISHING, self::POLISHED, self::CONCERNED,
            self::UNCLAIMING, self::UNCLAIMED => 'processing',
            self::REVIEWING, self::REVIEWED, self::APPROVED, self::CHANGES_REQUESTED => 'review',
            self::MERGING_QUEUED, self::MERGING_FAILED, self::MERGED, self::DEPLOYED => 'sync',
        };
    }

    /**
     * Kurze, lokalisierte Bezeichnung (Fallback: der Enum-Wert).
     */
    public function label(): string
    {
        return __('events.'.$this->value);
    }

    /**
     * Lokalisierte Beschreibung (Fallback: leer).
     */
    public function description(): string
    {
        $key = 'events.'.$this->value.'_desc';
        $text = __($key);

        return $text === $key ? '' : $text;
    }

    /**
     * @return array<int, self>
     */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $e) => $e->group() === $group));
    }

    /**
     * Reihenfolge der Gruppen in der UI.
     *
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return ['processing', 'review', 'sync'];
    }
}
