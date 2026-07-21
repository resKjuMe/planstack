<?php

namespace App\Models;

use App\Enums\TaskEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konfiguration einer Organisation für ein einzelnes Fortschritts-Event (Tabelle
 * `task_event_automations`, siehe docs/event-api.md). `event` ist ein Wert aus
 * App\Enums\TaskEvent.
 *
 *  - target_status_id: Zielstatus, in den die Aufgabe geschoben wird. NULL ⇒ der
 *    Status bleibt unverändert.
 *  - overridable_status_ids: Liste der Status-IDs, deren aktueller Status
 *    überschrieben werden darf. Leer/NULL ⇒ keine Einschränkung.
 *  - effects: zusätzliche Feld-Befüllungen; gleiche Struktur/Token-Semantik wie
 *    task_statuses.on_enter_effects (App\Support\StatusEffects).
 */
class OrgEventAutomation extends Model
{
    protected $table = 'task_event_automations';

    protected $fillable = [
        'organization_id', 'event', 'target_status_id', 'overridable_status_ids', 'effects',
    ];

    protected function casts(): array
    {
        return [
            'event' => TaskEvent::class,
            'overridable_status_ids' => 'array',
            'effects' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function targetStatus(): BelongsTo
    {
        return $this->belongsTo(OrgStatus::class, 'target_status_id');
    }
}
