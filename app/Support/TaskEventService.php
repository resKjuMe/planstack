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
     * Events, mit denen die Arbeitseinheit an der Aufgabe endet — danach ist der
     * Vermerk „arbeitet gerade daran" falsch und wird geräumt (siehe
     * {@see ClaimSession::finish()}).
     *
     * Bewusst NICHT dabei sind die Zwischen-Abschlüsse `ANALYZED`, `PROCESSED` und
     * `PUBLISHED`: nach ihnen läuft dieselbe Arbeitseinheit weiter (Umsetzung → PR →
     * Politur), die Session ist also noch da.
     */
    private const FINISHING_EVENTS = [
        TaskEvent::POLISHED,          // work/fix fertig → reviewbar
        TaskEvent::REVIEWED,          // Review erfasst
        TaskEvent::APPROVED,
        TaskEvent::CHANGES_REQUESTED,
        TaskEvent::CONCERNED,         // Concern gemeldet, Einheit endet
        TaskEvent::UNCLAIMED,         // freigegeben
        TaskEvent::MERGED,
    ];

    public function __construct(private readonly ClaimSession $session) {}

    /**
     * @param  ?string  $detail  Freitext zum Fortschritt ("4/9 Dateien: TaskController.php").
     * @param  ?int  $progress  Fortschritt in Prozent (0–100) innerhalb des Events.
     * @return array{configured: bool, status_changed: bool, applied_fields: array<int, string>, status: ?string}
     */
    public function record(
        Task $task,
        TaskEvent $event,
        ?User $actor = null,
        ?string $detail = null,
        ?int $progress = null,
    ): array {
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
        }

        // Fortschritts-Angabe denormalisiert auf die Aufgabe spiegeln, damit das
        // Board sie ohne Join auf jeder Karte zeigt. Nur setzen, wenn wirklich etwas
        // gemeldet wurde — ein Event ohne detail/progress soll den letzten Stand
        // nicht loeschen. `progress_at` traegt den Zeitpunkt, damit die Anzeige einen
        // veralteten Stand als solchen erkennen kann.
        //
        // Getrennt von $attrs gehalten: `applied_fields` in der Antwort meint die per
        // Automation angewendeten Feld-Effekte — die Fortschritts-Spiegelung ist keine
        // solche und wuerde den Vertrag verwaessern.
        $progressAttrs = [];

        if ($detail !== null || $progress !== null) {
            if ($detail !== null) {
                $progressAttrs['progress_detail'] = $detail;
            }
            if ($progress !== null) {
                $progressAttrs['progress_percent'] = $progress;
            }
            $progressAttrs['progress_at'] = now();
        }

        if ($attrs !== [] || $progressAttrs !== []) {
            $task->update([...$attrs, ...$progressAttrs]);
        }

        TaskEventLog::create([
            'task_id' => $task->id,
            'actor_id' => $actor?->id,
            'event' => $event->value,
            'detail' => $detail,
            'progress' => $progress,
        ]);

        // Schliesst dieses Event die Arbeitseinheit ab, den Vermerk „arbeitet gerade
        // daran" raeumen lassen — sonst stand er bis zum Ablauf der TTL weiter da.
        if (in_array($event, self::FINISHING_EVENTS, true)) {
            $this->session->finish();
        }

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
