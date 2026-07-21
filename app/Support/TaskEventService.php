<?php

namespace App\Support;

use App\Enums\TaskEvent;
use App\Models\Task;
use App\Models\TaskEventLog;
use App\Models\User;

/**
 * Wendet ein gemeldetes Fortschritts-Event (siehe docs/event-api.md) auf eine
 * Aufgabe an: Ist für das Event in der Organisation eine Automation konfiguriert
 * (App\Models\OrgEventAutomation), wird — sofern der aktuelle Status überschrieben
 * werden darf — der Zielstatus gesetzt (inkl. dessen On-Enter-Effekte) und die im
 * Event zusätzlich hinterlegten Feld-Effekte angewendet. Jedes Event wird
 * unabhängig davon protokolliert (task_events).
 */
class TaskEventService
{
    /**
     * @return array{configured: bool, status_changed: bool, applied_fields: array<int, string>, status: ?string}
     */
    public function record(Task $task, TaskEvent $event, ?User $actor = null): array
    {
        $organization = $task->project?->organization;
        $config = $organization?->eventAutomationFor($event);

        $statusChanged = false;
        $attrs = [];

        if ($config !== null) {
            $target = $config->target_status_id !== null ? $config->targetStatus : null;

            if ($target !== null
                && $organization !== null
                && $target->organization_id === $organization->id
                && $this->mayOverride($task, $config->overridable_status_ids)
                && $task->status_id !== $target->id
            ) {
                // Zielstatus setzen + dessen On-Enter-Effekte (in der UI readonly
                // angezeigt) übernehmen.
                $attrs['status_id'] = $target->id;
                $attrs = array_merge($attrs, StatusEffects::resolve($task, $target, $actor));
                $statusChanged = true;
            }

            // Zusätzliche, im Event hinterlegte Feld-Effekte (überschreiben die
            // On-Enter-Effekte des Zielstatus bei Feld-Kollision).
            $attrs = array_merge($attrs, StatusEffects::resolveEffects($task, $config->effects ?? [], $actor));

            if ($attrs !== []) {
                $task->update($attrs);
            }
        }

        TaskEventLog::create([
            'task_id' => $task->id,
            'actor_id' => $actor?->id,
            'event' => $event->value,
        ]);

        return [
            'configured' => $config !== null,
            'status_changed' => $statusChanged,
            'applied_fields' => array_values(array_filter(array_keys($attrs), fn ($f) => $f !== 'status_id')),
            'status' => $task->orgStatus?->key,
        ];
    }

    /**
     * Ob der aktuelle Status der Aufgabe laut Konfiguration überschrieben werden
     * darf. Leere/fehlende Auswahl ⇒ keine Einschränkung (immer überschreiben).
     *
     * @param  array<int, int|string>|null  $overridableStatusIds
     */
    private function mayOverride(Task $task, ?array $overridableStatusIds): bool
    {
        if (empty($overridableStatusIds)) {
            return true;
        }

        return in_array($task->status_id, array_map('intval', $overridableStatusIds), true);
    }
}
