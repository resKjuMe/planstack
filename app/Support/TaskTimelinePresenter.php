<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Datenbasis der Zeitachsen-Unterseite: je Task die Status-Aufenthalte der letzten
 * N Tage, als Zeiträume auf der Kalenderachse.
 *
 * Bewusst NICHT aus dem geteilten Tasks-Store ableitbar: der Task kennt nur seinen
 * jetzigen Status, der Verlauf steckt im Änderungsprotokoll (siehe
 * {@see TaskStatusHistory}). Darum ein eigener, schlanker Endpunkt — er liefert nur
 * {Status-KEY, von, bis}, kein Styling und keine Labels: beides löst der Client
 * über die ohnehin geladene Status-Konfiguration auf.
 *
 * Aufenthalte werden NICHT auf das Fenster beschnitten, sondern nur gefiltert (was
 * vorher endete, fällt weg). Der Client zeichnet auf das Fenster zu und kann im
 * Tooltip trotzdem den echten Beginn nennen — „läuft seit 12 Tagen" ist die
 * eigentliche Information, nicht „seit dem linken Rand".
 */
class TaskTimelinePresenter
{
    /** Vorgabe-Fenster der Unterseite. */
    public const DEFAULT_DAYS = 30;

    private const MIN_DAYS = 7;

    private const MAX_DAYS = 120;

    public function __construct(private readonly TaskStatusHistory $history) {}

    /**
     * @return array{from: string, to: string, days: int, tasks: array<int, array<int, array{key: string, from: string, to: string, open: bool}>>}
     */
    public function payload(Project $project, int $days = self::DEFAULT_DAYS): array
    {
        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $days));

        // Das Fenster endet JETZT und beginnt am Tagesanfang vor `days - 1` Tagen —
        // so ist die letzte Spalte immer der heutige (laufende) Tag und die Achse
        // umfasst genau `days` Kalendertage.
        $now = CarbonImmutable::now();
        $from = $now->subDays($days - 1)->startOfDay();

        $tasks = $project->tasks()->get(['id', 'created_at', 'status_id', 'project_id']);

        /** @var Collection<int, OrgStatus> $statuses */
        $statuses = $project->organization->statuses()->get()->keyBy('id');

        $byTask = [];
        foreach ($this->history->stays($tasks, $from) as $stay) {
            $key = $statuses->get($stay['status_id'])?->key;

            // Status seit dem Aufenthalt gelöscht → nicht darstellbar (der Client
            // kennt den Key nicht) und deshalb ausgelassen.
            if ($key === null) {
                continue;
            }

            $byTask[$stay['task_id']][] = [
                'key' => $key,
                'from' => $stay['from']->toIso8601String(),
                'to' => $stay['to']->toIso8601String(),
                'open' => $stay['open'],
            ];
        }

        return [
            'from' => $from->toIso8601String(),
            'to' => $now->toIso8601String(),
            'days' => $days,
            'tasks' => $byTask,
        ];
    }
}
