<?php

namespace App\Support;

use App\Models\Task;
use Carbon\CarbonImmutable;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Rekonstruiert die Status-AUFENTHALTE eines Tasks aus dem Änderungsprotokoll —
 * die gemeinsame Grundlage aller Verlaufs-Auswertungen.
 *
 * Der Task selbst kennt nur seinen JETZIGEN Status. Der Verlauf steckt in den
 * Audit-Zeilen: jede trägt `new_values.status_id` und ihren Zeitstempel; ein
 * Aufenthalt reicht von dieser Zeile bis zur nächsten Statusänderung (beim
 * laufenden Aufenthalt: bis jetzt).
 *
 * Zwei Auswertungen leben davon:
 *  - {@see TaskStatusDurations} interessiert nur die DAUER je Aufenthalt,
 *  - die Zeitachse der Projekt-Unterseite braucht die ZEITPUNKTE (von/bis), um
 *    den Balken auf der Kalenderachse zu platzieren.
 *
 * Rückläufer („in Review → Änderungen erbeten → … → in Review") ergeben mehrere
 * getrennte Aufenthalte desselben Status — bewusst, denn beide Durchgänge sind
 * echte Zeiträume.
 *
 * Grenzen: nur was protokolliert ist. Zeit vor der ersten Audit-Zeile eines Tasks
 * wird dem Status zugeschlagen, den `old_values.status_id` dieser Zeile nennt
 * (gerechnet ab `tasks.created_at`); fehlt auch das, beginnt die Zeitachse mit der
 * ersten Änderung. Statuswechsel per `saveQuietly()` erzeugen keine Audit-Zeile
 * und sind damit unsichtbar.
 */
class TaskStatusHistory
{
    /**
     * Alle Aufenthalte der übergebenen Tasks in EINER Abfrage, je Task in
     * Lebenszyklus-Reihenfolge.
     *
     * `$endedAfter` filtert (es wird NICHT beschnitten): nur Aufenthalte, die zu
     * diesem Zeitpunkt noch liefen oder danach begannen. Die Zeitachse braucht die
     * unbeschnittenen Grenzen, um im Tooltip den echten Beginn zeigen zu können —
     * das Zuschneiden auf das Fenster macht der Client beim Zeichnen.
     *
     * @param  Collection<int, Task>  $tasks  mit id, created_at, status_id
     * @return array<int, array{task_id: int, status_id: int, from: CarbonImmutable, to: CarbonImmutable, open: bool}>
     */
    public function stays(Collection $tasks, ?CarbonImmutable $endedAfter = null): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $log = EloquentAuditLog::forEntity(Task::class);

        if (! Schema::hasTable($log->getTable())) {
            return [];
        }

        // LIKE als portabler Vorfilter (MySQL wie SQLite); entscheidend ist danach
        // der dekodierte Wert.
        $rowsByTask = $log->newQuery()
            ->whereIn('entity_id', $tasks->pluck('id'))
            ->where('new_values', 'like', '%status_id%')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['entity_id', 'old_values', 'new_values', 'created_at'])
            ->filter(fn ($row) => ($row->new_values['status_id'] ?? null) !== null)
            ->groupBy(fn ($row) => (int) $row->entity_id);

        $now = CarbonImmutable::now();
        $stays = [];

        foreach ($tasks as $task) {
            $rows = $rowsByTask->get($task->id, collect())->values();

            foreach ($this->staysForTask($task, $rows, $now) as $stay) {
                if ($endedAfter !== null && $stay['to']->lessThanOrEqualTo($endedAfter)) {
                    continue;
                }

                $stays[] = $stay;
            }
        }

        return $stays;
    }

    /**
     * @param  Collection<int, object>  $rows  Audit-Zeilen des Tasks, nach Zeit sortiert
     * @return array<int, array{task_id: int, status_id: int, from: CarbonImmutable, to: CarbonImmutable, open: bool}>
     */
    private function staysForTask(Task $task, Collection $rows, CarbonImmutable $now): array
    {
        // Ohne protokollierte Änderung: der Task liegt seit seiner Anlage im
        // aktuellen Status — ein laufender Aufenthalt.
        if ($rows->isEmpty()) {
            if ($task->status_id === null || $task->created_at === null) {
                return [];
            }

            return [$this->stay($task->id, (int) $task->status_id, CarbonImmutable::parse($task->created_at), $now, true)];
        }

        $stays = [];

        // Zeit VOR der ersten protokollierten Änderung: sie gehört dem Status, aus
        // dem heraus gewechselt wurde (old_values), gerechnet ab Anlage.
        $first = $rows->first();
        $firstFrom = $task->created_at !== null ? CarbonImmutable::parse($task->created_at) : null;
        $initialStatus = $first->old_values['status_id'] ?? null;

        if ($initialStatus !== null && $firstFrom !== null) {
            $firstAt = CarbonImmutable::parse($first->created_at);
            if ($firstAt->greaterThan($firstFrom)) {
                $stays[] = $this->stay($task->id, (int) $initialStatus, $firstFrom, $firstAt, false);
            }
        }

        foreach ($rows as $index => $row) {
            $from = CarbonImmutable::parse($row->created_at);
            $next = $rows->get($index + 1);
            $to = $next !== null ? CarbonImmutable::parse($next->created_at) : $now;

            $stays[] = $this->stay($task->id, (int) $row->new_values['status_id'], $from, $to, $next === null);
        }

        return $stays;
    }

    /**
     * @return array{task_id: int, status_id: int, from: CarbonImmutable, to: CarbonImmutable, open: bool}
     */
    private function stay(int $taskId, int $statusId, CarbonImmutable $from, CarbonImmutable $to, bool $open): array
    {
        return [
            'task_id' => $taskId,
            'status_id' => $statusId,
            'from' => $from,
            // Ein negativer Aufenthalt wäre ein Datenfehler (Protokoll-Zeitstempel
            // vor der Anlage) — auf einen Punkt zusammenfallen lassen, statt einen
            // Balken nach hinten zu zeichnen.
            'to' => $to->lessThan($from) ? $from : $to,
            'open' => $open,
        ];
    }
}
