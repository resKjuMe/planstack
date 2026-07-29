<?php

namespace App\Support;

use App\Models\Task;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Support\Facades\Schema;

/**
 * Wie oft wurden für eine Task je „Änderungen erbeten"? — aus dem Audit-Log, nicht
 * aus dem aktuellen Zustand.
 *
 * Hintergrund: `tasks.last_review_recommendation` hält nur das LETZTE Review. Wird
 * nach einem REQUEST_CHANGES nachgearbeitet und dann freigegeben, steht dort
 * APPROVE — die Nacharbeit ist im Feld nicht mehr zu sehen. Die Rework-Quote ist
 * aber genau die interessante Kennzahl, und sie lässt sich rekonstruieren: das
 * Feld steht nicht auf der Audit-Ausschlussliste (config/audit-logger.php), jeder
 * Wechsel liegt also als Zeile in der Task-Audit-Tabelle.
 *
 * Grenze: nur, was auch protokolliert wurde. Reviews, die vor Einführung des
 * Audit-Logs liefen, fehlen; ebenso alles, was per `saveQuietly()` geschrieben
 * wurde (so schreibt z. B. der PR-Status-Sync — dessen CI-Felder haben deshalb
 * überhaupt keine Historie).
 */
class TaskReworkCounts
{
    /**
     * Zahl der protokollierten REQUEST_CHANGES je Task-ID. Tasks ohne Nacharbeit
     * fehlen im Ergebnis (der Aufrufer liest mit ?? 0).
     *
     * @param  iterable<int|string>  $taskIds
     * @return array<int, int>
     */
    public function forTaskIds(iterable $taskIds): array
    {
        $ids = collect($taskIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $log = EloquentAuditLog::forEntity(Task::class);

        // Frische Installation ohne Audit-Tabellen: keine Historie, kein Fehler.
        if (! Schema::hasTable($log->getTable())) {
            return [];
        }

        $counts = [];

        // LIKE ist nur der Vorfilter (portabel über MySQL und SQLite, im Gegensatz
        // zu JSON-Funktionen); entscheiden tut der dekodierte Wert — „REQUEST_CHANGES"
        // könnte auch in einem anderen Feld stehen.
        $log->newQuery()
            ->whereIn('entity_id', $ids)
            ->where('new_values', 'like', '%REQUEST_CHANGES%')
            ->select(['entity_id', 'new_values'])
            ->get()
            ->each(function ($row) use (&$counts) {
                $values = $row->new_values ?? [];

                if (($values['last_review_recommendation'] ?? null) !== 'REQUEST_CHANGES') {
                    return;
                }

                $id = (int) $row->entity_id;
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            });

        return $counts;
    }
}
