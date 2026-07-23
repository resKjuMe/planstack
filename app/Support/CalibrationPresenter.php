<?php

namespace App\Support;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Berechnet die Kalibrierungs-Daten (Schätzung vs. Ist für gemergte Tasks) als
 * JSON-sicheres Payload für den geteilten Client-Store. Ausgelagert aus dem
 * (früheren Blade-)Controller, damit die React-Kalibrierungsseite dieselben Zahlen
 * über einen gecachten API-Endpunkt bekommt (einmal laden, per entity-changed
 * aktualisieren) — die schwere Statistik bleibt serverseitig.
 */
class CalibrationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Project $project): array
    {
        $tasks = $project->tasks()
            ->whereHas('orgStatus', fn ($q) => $q->where('role', StatusRole::MERGED->value))
            ->with('pullRequests')
            ->get()
            ->filter(fn (Task $task) => $task->pullRequests->isNotEmpty());

        $lastSync = $tasks->flatMap(fn (Task $t) => $t->pullRequests)->pluck('updated_at')->filter()->max();

        $rows = $tasks
            ->map(fn (Task $task) => $this->calibrate($project, $task))
            ->sortByDesc('mergedAt')
            ->values();

        $withDeviation = $rows->filter(fn (array $r) => $r['deviationPct'] !== null)->values();
        $spAccuracy = $this->spAccuracy($withDeviation);

        return [
            'rowData' => $rows->map(fn (array $r) => $this->rowData($r))->all(),
            'kpis' => $this->kpis($rows, $withDeviation, $lastSync),
            'groups' => $this->groupData($this->groupByStoryPoints($rows)),
            'scatter' => $this->scatter($withDeviation),
            'spAccuracy' => $spAccuracy,
            'tip' => $this->accuracyTip($spAccuracy),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calibrate(Project $project, Task $task): array
    {
        $prs = $task->pullRequests;

        $filesActual = (int) $prs->sum('changed_files');
        $filesEstimated = $task->affected_files !== null ? (int) $task->affected_files : null;
        $deviationPct = $filesEstimated !== null && $filesEstimated > 0
            ? (int) round((($filesActual - $filesEstimated) / $filesEstimated) * 100)
            : null;

        $mergedAt = $prs->pluck('merged_at')->filter()->max();
        $claimedAt = $task->claimed_at;

        $cycleDays = $claimedAt && $mergedAt ? $claimedAt->floatDiffInDays($mergedAt) : null;
        $sp = $task->effort_story_points;
        $timePerSpDays = $cycleDays !== null && $sp ? $cycleDays / $sp : null;

        return [
            'name' => $task->name,
            'url' => route('projects.tasks.show', [$project, $task]),
            'storyPoints' => $sp,
            'claimedAt' => $claimedAt,
            'mergedAt' => $mergedAt,
            'dateShort' => $mergedAt ? $mergedAt->format('d.m.') : '—',
            'filesEstimated' => $filesEstimated,
            'filesActual' => $filesActual,
            'deviationPct' => $deviationPct,
            'deviationLabel' => $this->deviationLabel($deviationPct),
            'deviationClass' => $this->deviationClass($deviationPct),
            'timePerSpDays' => $timePerSpDays,
            'timePerSpLabel' => $timePerSpDays !== null ? $this->formatDurationSmart($timePerSpDays) : null,
            'additions' => (int) $prs->sum('additions'),
            'deletions' => (int) $prs->sum('deletions'),
            'commits' => (int) $prs->sum('commits'),
            'durationDays' => $cycleDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function rowData(array $r): array
    {
        $hasEstimate = $r['deviationPct'] !== null;
        $abs = $hasEstimate ? abs($r['deviationPct']) : null;

        return [
            'name' => $r['name'],
            'url' => $r['url'],
            'dateShort' => $r['dateShort'],
            'meta' => $r['commits'].' Commits · +'.$r['additions'].'/−'.$r['deletions'],
            'sp' => $r['storyPoints'],
            'filesEstimated' => $r['filesEstimated'] ?? null,
            'filesActual' => $r['filesActual'],
            'hasEstimate' => $hasEstimate,
            'deviationLabel' => $r['deviationLabel'],
            'pillClass' => $this->pillClass($r['deviationClass']),
            'barClass' => $this->barClass($r['deviationClass']),
            'barWidth' => $abs !== null ? min(100, $abs) : 0,
            'timePerSp' => $r['timePerSpLabel'] ?? '—',
            'isOutlier' => $hasEstimate && $abs > 50,
            'sortDev' => $abs ?? -1,
            'sortSp' => (int) ($r['storyPoints'] ?? -1),
            'sortDate' => $r['mergedAt'] ? $r['mergedAt']->timestamp : 0,
            'sortTime' => $r['timePerSpDays'] ?? -1,
        ];
    }

    private function pillClass(string $deviationClass): string
    {
        return match ($deviationClass) {
            'green' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'red' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        };
    }

    private function barClass(string $deviationClass): string
    {
        return match ($deviationClass) {
            'green' => 'bg-green-400',
            'amber' => 'bg-amber-400',
            'red' => 'bg-red-300',
            default => 'bg-gray-200',
        };
    }

    private function deviationClass(?int $pct): string
    {
        if ($pct === null) {
            return 'gray';
        }

        return match (true) {
            abs($pct) <= 25 => 'green',
            abs($pct) <= 50 => 'amber',
            default => 'red',
        };
    }

    private function deviationLabel(?int $pct): ?string
    {
        return match (true) {
            $pct === null => null,
            $pct === 0 => '±0 %',
            $pct > 0 => "+{$pct} %",
            default => "{$pct} %",
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, array<string, mixed>>  $withDeviation
     * @return array<string, mixed>
     */
    private function kpis(Collection $rows, Collection $withDeviation, $lastSync): array
    {
        $median = $this->median($withDeviation->pluck('deviationPct')->all());

        $completedSp = (int) $rows->sum(fn (array $r) => (int) $r['storyPoints']);
        $firstClaim = $rows->pluck('claimedAt')->filter()->min();
        $lastMerge = $rows->pluck('mergedAt')->filter()->max();
        $spanDays = $firstClaim && $lastMerge ? $firstClaim->floatDiffInDays($lastMerge) : null;

        $spPerDay = $spanDays !== null && $spanDays > 0 && $completedSp > 0 ? $completedSp / $spanDays : null;
        $daysPerSp = $spanDays !== null && $completedSp > 0 ? $spanDays / $completedSp : null;

        $withEstimate = $withDeviation->count();
        $total = $rows->count();

        return [
            'total' => $total,
            'lastSync' => $lastSync?->locale(app()->getLocale())->diffForHumans(),
            'median' => $median,
            'medianLabel' => $this->deviationLabel($median),
            'medianClass' => $this->deviationClass($median),
            'medianHint' => match (true) {
                $median === null => __('calibration.median_hint_no_estimates'),
                $median > 5 => __('calibration.median_hint_too_small'),
                $median < -5 => __('calibration.median_hint_too_large'),
                default => __('calibration.median_hint_ok'),
            },
            'spPerDay' => $spPerDay,
            'daysPerSpLabel' => $daysPerSp !== null ? $this->formatDurationSmart($daysPerSp) : null,
            'hits' => $withDeviation->filter(fn (array $r) => abs($r['deviationPct']) <= 25)->count(),
            'hitsTotal' => $withEstimate,
            'withEstimate' => $withEstimate,
            'noEstimate' => $total - $withEstimate,
        ];
    }

    /**
     * @param  array<int, int>  $values
     */
    private function median(array $values): ?int
    {
        if (empty($values)) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? (int) $values[$mid]
            : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $withDeviation
     * @return array<string, mixed>
     */
    private function scatter(Collection $withDeviation): array
    {
        $points = $withDeviation->map(fn (array $r) => [
            'x' => (int) $r['filesEstimated'],
            'y' => (int) $r['filesActual'],
            'hit' => abs($r['deviationPct']) <= 25,
            'name' => $r['name'],
        ])->values()->all();

        $maxVal = 0;
        foreach ($points as $p) {
            $maxVal = max($maxVal, $p['x'], $p['y']);
        }
        $axis = max(10, (int) ceil(max($maxVal, 10) / 10) * 10);
        if ($maxVal === $axis) {
            $axis += 10;
        }

        return ['points' => $points, 'axis' => $axis];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $withDeviation
     * @return array<int, array<string, mixed>>
     */
    private function spAccuracy(Collection $withDeviation): array
    {
        $bySp = [];
        foreach ($withDeviation as $r) {
            $sp = (int) $r['storyPoints'];
            if ($sp <= 0) {
                continue;
            }
            $bySp[$sp][] = $r;
        }
        if (empty($bySp)) {
            return [];
        }

        $spVals = array_keys($bySp);
        sort($spVals);

        $ranges = [];
        $start = $prev = $spVals[0];
        foreach (array_slice($spVals, 1) as $v) {
            if ($v === $prev + 1) {
                $prev = $v;

                continue;
            }
            $ranges[] = [$start, $prev];
            $start = $prev = $v;
        }
        $ranges[] = [$start, $prev];

        $out = [];
        foreach ($ranges as [$lo, $hi]) {
            $group = [];
            for ($s = $lo; $s <= $hi; $s++) {
                if (isset($bySp[$s])) {
                    $group = array_merge($group, $bySp[$s]);
                }
            }
            $total = count($group);
            $hits = count(array_filter($group, fn ($r) => abs($r['deviationPct']) <= 25));
            $out[] = [
                'label' => $lo === $hi ? "{$lo} SP" : "{$lo}–{$hi} SP",
                'lo' => $lo,
                'hi' => $hi,
                'hits' => $hits,
                'total' => $total,
                'pct' => $total ? (int) round($hits / $total * 100) : 0,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $spAccuracy
     */
    private function accuracyTip(array $spAccuracy): ?string
    {
        $bad = array_filter($spAccuracy, fn ($g) => $g['total'] >= 1 && $g['pct'] === 0 && $g['lo'] >= 5);
        if (empty($bad)) {
            return null;
        }
        usort($bad, fn ($a, $b) => $b['lo'] <=> $a['lo']);

        return __('calibration.accuracy_tip', ['lo' => $bad[0]['lo']]);
    }

    private function formatDurationSmart(float $days): string
    {
        $minutes = $days * 24 * 60;
        $sep = app()->getLocale() === 'de' ? ',' : '.';

        if ($minutes < 60) {
            return number_format(max(0, $minutes), 0, $sep, '').' '.__('calibration.unit_min');
        }

        $hours = $minutes / 60;
        if ($hours < 24) {
            return number_format($hours, 1, $sep, '').' '.__('calibration.unit_hours');
        }

        return number_format($days, 1, $sep, '').' '.__('calibration.unit_days');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function groupByStoryPoints(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $r) => $r['storyPoints'] && $r['durationDays'] !== null)
            ->groupBy('storyPoints')
            ->sortKeys()
            ->map(fn (Collection $group, $sp) => [
                'storyPoints' => $sp,
                'avgDuration' => $group->avg('durationDays'),
                'rows' => $group->sortByDesc('mergedAt')->values(),
            ])
            ->values();
    }

    /**
     * Gruppen JSON-sicher machen (Task-Zeilen → {name, url}).
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function groupData(Collection $groups): array
    {
        return $groups->map(fn (array $g) => [
            'storyPoints' => (int) $g['storyPoints'],
            'avgDuration' => round((float) $g['avgDuration'], 1),
            'rows' => collect($g['rows'])->map(fn (array $r) => [
                'name' => $r['name'],
                'url' => $r['url'],
            ])->all(),
        ])->all();
    }
}
