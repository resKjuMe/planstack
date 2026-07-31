<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Wie lange liegen Tasks in welchem Status? — rekonstruiert aus dem
 * Änderungsprotokoll, nicht aus dem aktuellen Zustand.
 *
 * Die Aufenthalte selbst kommen aus {@see TaskStatusHistory} (gemeinsame Basis mit
 * der Zeitachse der Projekt-Unterseite); hier interessieren nur ihre DAUERN.
 *
 * Wichtig bei Rückläufern („in Review → Änderungen erbeten → … → in Review"):
 * gezählt wird JEDER Aufenthalt getrennt und dann aufsummiert — der zweite
 * Review-Durchgang addiert sich auf den ersten, statt ihn zu ersetzen. Darum
 * werden `visits` (Aufenthalte) und `tasks` getrennt ausgewiesen: visits > tasks
 * heißt, dass Tasks in diesen Status zurückgefallen sind.
 */
class TaskStatusDurations
{
    public function __construct(private readonly TaskStatusHistory $history) {}

    /**
     * Status-Familien, die als Bearbeitungszeit gelten. Ausgeschlossen sind damit
     * „waiting" (pickbar — Wartezeit vor der Bearbeitung, nicht Bearbeitung),
     * „done" (gemergt/erledigt — die Zeit DANACH ist keine Verweildauer) und
     * „exception" (blockiert/problematisch — Sonderfälle, die die Verteilung der
     * eigentlichen Arbeit verzerren).
     *
     * @var array<int, string>
     */
    private const WORK_KINDS = ['active', 'review'];

    /**
     * Rohdaten je Task für CLIENTSEITIGE Auswertung: pro Task die Aufenthalte je
     * Status, mit Status-KEY statt Styling. Genutzt vom Board-Endpunkt, damit die
     * Projekt-Performance-Seite (die alles aus dem geteilten Store ableitet) die
     * Verweildauern je Mitarbeiter selbst bilden kann — Label, Farbe und
     * Reihenfolge löst der Client über die ohnehin geladene Status-Konfiguration
     * auf, was die Antwort klein hält.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, OrgStatus>  $statuses  nach id indiziert
     * @return array<int, array<int, array{key: string, days: float, visits: int, open: int}>>
     */
    public function perTaskStatusTotals(Collection $tasks, Collection $statuses): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $workStatusIds = $this->workStatusIds($statuses);
        $keyById = $statuses->map(fn (OrgStatus $s) => $s->key);

        $result = [];
        foreach ($this->segments($tasks) as $segment) {
            if (! in_array($segment['status_id'], $workStatusIds, true)) {
                continue;
            }
            $key = $keyById[$segment['status_id']] ?? null;
            if ($key === null) {
                continue;
            }

            $bucket = &$result[$segment['task_id']][$key];
            $bucket ??= ['key' => $key, 'days' => 0.0, 'visits' => 0, 'open' => 0];
            $bucket['days'] += $segment['seconds'] / 86400;
            $bucket['visits']++;
            $bucket['open'] += $segment['open'] ? 1 : 0;
            unset($bucket);
        }

        // Die Status-Reihenfolge stellt der Client her (er kennt die Positionen);
        // hier nur die Verschachtelung auf Listen glätten.
        return array_map('array_values', $result);
    }

    /**
     * @param  Collection<int, OrgStatus>  $statuses
     * @return array<int, int>
     */
    private function workStatusIds(Collection $statuses): array
    {
        return $statuses
            ->filter(fn (OrgStatus $s) => in_array($s->kind, self::WORK_KINDS, true))
            ->keys()
            ->all();
    }

    /**
     * Verweildauern über die übergebenen Tasks — in EINER Abfrage, weil dieselben
     * Aufenthalte drei Auswertungen speisen:
     *  - `statuses`: Aggregat je Status (Panel „Verweildauer je Status")
     *  - `perTask`:  Aufschlüsselung je Task (Balken in „Zuletzt geliefert")
     *  - `perProject`: dasselbe je Projekt (Balken in „Nach Projekt")
     *
     * @param  Collection<int, Task>  $tasks  mit id, created_at, status_id, project_id
     * @param  Collection<int, OrgStatus>  $statuses  nach id indiziert (Label + Styling)
     * @return array{statuses: array<int, array<string, mixed>>, perTask: array<int, array<string, mixed>>, perProject: array<int, array<string, mixed>>}
     */
    public function aggregate(Collection $tasks, Collection $statuses): array
    {
        $empty = ['statuses' => [], 'perTask' => [], 'perProject' => []];

        if ($tasks->isEmpty()) {
            return $empty;
        }

        // Nur Bearbeitungs-Status; siehe WORK_KINDS.
        $workStatusIds = $this->workStatusIds($statuses);

        $segments = array_values(array_filter(
            $this->segments($tasks),
            fn (array $s) => in_array($s['status_id'], $workStatusIds, true),
        ));

        if ($segments === []) {
            return $empty;
        }

        $projectByTask = $tasks->pluck('project_id', 'id');

        $perTask = $this->breakdown($segments, $statuses, fn (array $s) => $s['task_id']);
        $perProject = $this->breakdown($segments, $statuses, fn (array $s) => $projectByTask[$s['task_id']] ?? null);

        return [
            'statuses' => $this->statusRows($segments, $statuses),
            'perTask' => $perTask,
            'perProject' => $this->withTaskMedian($perProject, $perTask, $projectByTask),
        ];
    }

    /**
     * Ergänzt je Projekt den Median über die Gesamt-Bearbeitungszeiten seiner
     * TASKS — die „typische Task dieses Projekts".
     *
     * Nicht der Median über die Status-Zeilen: das wären unvergleichbare Größen
     * („die mittlere Dauer eines beliebigen Status") und beantwortet keine Frage.
     * Und nicht die Summe: die wächst einfach mit der Zahl der Tasks.
     *
     * @param  array<int|string, array<string, mixed>>  $perProject
     * @param  array<int|string, array<string, mixed>>  $perTask
     * @param  Collection<int, int|null>  $projectByTask
     * @return array<int|string, array<string, mixed>>
     */
    private function withTaskMedian(array $perProject, array $perTask, Collection $projectByTask): array
    {
        $secondsByProject = [];
        foreach ($perTask as $taskId => $breakdown) {
            $projectId = $projectByTask[$taskId] ?? null;
            if ($projectId === null) {
                continue;
            }
            // median() erwartet Sekunden und rechnet selbst in Tage um.
            $secondsByProject[$projectId][] = $breakdown['totalDays'] * 86400;
        }

        foreach ($perProject as $projectId => $row) {
            $totals = $secondsByProject[$projectId] ?? [];
            $perProject[$projectId]['medianTaskDays'] = $this->median($totals);
            $perProject[$projectId]['taskCount'] = count($totals);
        }

        return $perProject;
    }

    /**
     * Aggregat je Status, in Lebenszyklus-Reihenfolge.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @param  Collection<int, OrgStatus>  $statuses
     * @return array<int, array<string, mixed>>
     */
    private function statusRows(array $segments, Collection $statuses): array
    {
        // Aufenthalte je Status bündeln: Gesamtzeit, Zahl der Aufenthalte und —
        // getrennt davon — die Summe JE TASK (Basis für Ø und Median je Task).
        $byStatus = [];
        foreach ($segments as $segment) {
            $statusId = $segment['status_id'];
            $bucket = &$byStatus[$statusId];
            $bucket ??= ['seconds' => 0.0, 'visits' => 0, 'open' => 0, 'perTask' => []];

            $bucket['seconds'] += $segment['seconds'];
            $bucket['visits']++;
            $bucket['open'] += $segment['open'] ? 1 : 0;
            $bucket['perTask'][$segment['task_id']] = ($bucket['perTask'][$segment['task_id']] ?? 0.0) + $segment['seconds'];
            unset($bucket);
        }

        $rows = [];
        foreach ($byStatus as $statusId => $data) {
            /** @var OrgStatus|null $status */
            $status = $statuses->get($statusId);
            $perTask = array_values($data['perTask']);
            $taskCount = count($perTask);

            $rows[] = [
                'key' => $status?->key ?? (string) $statusId,
                'label' => $status !== null ? $this->label($status) : (string) $statusId,
                'position' => (int) ($status?->position ?? PHP_INT_MAX),
                'bar' => StatusPalette::bar($status?->color_token),
                'badge' => StatusPalette::badge($status?->color_token),
                'tasks' => $taskCount,
                'visits' => $data['visits'],
                'openVisits' => $data['open'],
                'totalDays' => $data['seconds'] / 86400,
                'avgPerTaskDays' => $taskCount > 0 ? ($data['seconds'] / $taskCount) / 86400 : null,
                'avgPerVisitDays' => $data['visits'] > 0 ? ($data['seconds'] / $data['visits']) / 86400 : null,
                'medianPerTaskDays' => $this->median($perTask),
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return $rows;
    }

    /**
     * Gestapelte Aufschlüsselung je Gruppe (Task oder Projekt): Segmente in
     * Lebenszyklus-Reihenfolge mit Dauer und Styling, plus Gesamtdauer. Grundlage
     * der Balken in den Tabellen — deshalb kommt das Label mit, damit der Client
     * den Tooltip ohne weitere Zuordnung bauen kann.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @param  Collection<int, OrgStatus>  $statuses
     * @param  callable(array<string, mixed>): (int|string|null)  $groupBy
     * @return array<int|string, array<string, mixed>>
     */
    private function breakdown(array $segments, Collection $statuses, callable $groupBy): array
    {
        $grouped = [];
        foreach ($segments as $segment) {
            $group = $groupBy($segment);
            if ($group === null) {
                continue;
            }
            $grouped[$group][$segment['status_id']] = ($grouped[$group][$segment['status_id']] ?? 0.0) + $segment['seconds'];
        }

        $result = [];
        foreach ($grouped as $group => $byStatus) {
            $parts = [];
            foreach ($byStatus as $statusId => $seconds) {
                /** @var OrgStatus|null $status */
                $status = $statuses->get($statusId);
                $parts[] = [
                    'key' => $status?->key ?? (string) $statusId,
                    'label' => $status !== null ? $this->label($status) : (string) $statusId,
                    'position' => (int) ($status?->position ?? PHP_INT_MAX),
                    'bar' => StatusPalette::bar($status?->color_token),
                    'days' => $seconds / 86400,
                ];
            }

            usort($parts, fn (array $a, array $b) => $a['position'] <=> $b['position']);

            $result[$group] = [
                'segments' => $parts,
                'totalDays' => array_sum(array_column($parts, 'days')),
            ];
        }

        return $result;
    }

    /**
     * Ein Aufenthalt je Eintrag: {task_id, status_id, seconds, open}. `open` = der
     * Aufenthalt läuft noch (Endzeit ist „jetzt"), was Durchschnitte nach unten
     * zieht und deshalb ausgewiesen wird.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function segments(Collection $tasks): array
    {
        return array_map(fn (array $stay) => [
            'task_id' => $stay['task_id'],
            'status_id' => $stay['status_id'],
            'seconds' => (float) ($stay['to']->getTimestamp() - $stay['from']->getTimestamp()),
            'open' => $stay['open'],
        ], $this->history->stays($tasks));
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        $seconds = $count % 2 === 1
            ? $values[$mid]
            : ($values[$mid - 1] + $values[$mid]) / 2;

        return $seconds / 86400;
    }

    private function label(OrgStatus $status): string
    {
        return Str::startsWith(app()->getLocale(), 'en') && $status->label_en
            ? $status->label_en
            : $status->label;
    }
}
