<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectCalibrationController extends Controller
{
    /**
     * Schätzung vs. tatsächlicher Aufwand für gemergte Tasks. Dateien sind das
     * einzige Feld mit einem echten Plan/Ist-Paar (affected_files vs. die
     * changed_files-Summe aller PRs einer Task); Story Points werden nur
     * indirekt über die Dauer-je-SP-Kennzahl und die SP-Gruppierung kalibriert.
     */
    public function __invoke(Project $project): View
    {
        $this->authorize('view', $project);

        $rows = $project->tasks()
            ->where('status', TaskStatus::MERGED->value)
            ->with('pullRequests')
            ->get()
            ->filter(fn (Task $task) => $task->pullRequests->isNotEmpty())
            ->map(fn (Task $task) => $this->calibrate($task))
            ->sortByDesc('mergedAt')
            ->values();

        return view('status.calibration', [
            'project' => $project,
            'active' => 'calibration',
            'rows' => $rows,
            'kpis' => $this->kpis($rows),
            'groups' => $this->groupByStoryPoints($rows),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calibrate(Task $task): array
    {
        $prs = $task->pullRequests;

        $filesActual = (int) $prs->sum('changed_files');
        $filesEstimated = $task->affected_files !== null ? (int) $task->affected_files : null;
        $deviationPct = $filesEstimated !== null && $filesEstimated > 0
            ? (int) round((($filesActual - $filesEstimated) / $filesEstimated) * 100)
            : null;

        $openedAt = $prs->pluck('opened_at')->filter()->min();
        $mergedAt = $prs->pluck('merged_at')->filter()->max();
        $durationDays = $openedAt && $mergedAt ? $openedAt->floatDiffInDays($mergedAt) : null;

        return [
            'task' => $task,
            'name' => $task->name,
            'storyPoints' => $task->effort_story_points,
            'mergedAt' => $mergedAt,
            'filesEstimated' => $filesEstimated,
            'filesActual' => $filesActual,
            'deviationPct' => $deviationPct,
            'deviationLabel' => $this->deviationLabel($deviationPct),
            'deviationClass' => $this->deviationClass($deviationPct),
            'durationDays' => $durationDays,
            'durationLabel' => $this->formatDuration($durationDays),
            'additions' => (int) $prs->sum('additions'),
            'deletions' => (int) $prs->sum('deletions'),
            'commits' => (int) $prs->sum('commits'),
            'comments' => (int) $prs->sum('comments'),
            'reviewComments' => (int) $prs->sum('review_comments'),
        ];
    }

    /**
     * Grün innerhalb ±25 %, Amber bis ±50 %, Rot darüber — Unter- und
     * Überschätzung zählen gleichermaßen als Abweichung.
     */
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

    /**
     * "+125", "±0", "-40" — sign always shown, "±0" for the exact-hit case.
     */
    private function deviationLabel(?int $pct): ?string
    {
        return match (true) {
            $pct === null => null,
            $pct === 0 => '±0 %',
            $pct > 0 => "+{$pct} %",
            default => "{$pct} %",
        };
    }

    private function formatDuration(?float $days): ?string
    {
        if ($days === null) {
            return null;
        }

        $minutes = (int) round($days * 24 * 60);
        $wholeDays = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return $wholeDays > 0 ? "{$wholeDays}d {$hours}h" : "{$hours}h";
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function kpis(Collection $rows): array
    {
        $withDeviation = $rows->filter(fn (array $r) => $r['deviationPct'] !== null);
        $withSp = $rows->filter(fn (array $r) => $r['storyPoints'] && $r['durationDays'] !== null);

        $avgDeviation = $withDeviation->isNotEmpty()
            ? (int) round($withDeviation->avg('deviationPct'))
            : null;

        $avgDurationPerSp = $withSp->isNotEmpty()
            ? $withSp->avg(fn (array $r) => $r['durationDays'] / $r['storyPoints'])
            : null;

        return [
            'total' => $rows->count(),
            'avgDeviation' => $avgDeviation,
            'avgDeviationLabel' => $this->deviationLabel($avgDeviation),
            'avgDeviationClass' => $this->deviationClass($avgDeviation),
            'avgDeviationHint' => match (true) {
                $avgDeviation !== null => match (true) {
                    $avgDeviation > 5 => 'wir schätzen zu klein',
                    $avgDeviation < -5 => 'wir schätzen zu groß',
                    default => 'im Rahmen',
                },
                $rows->isEmpty() => 'keine gemergten Tasks mit PR-Daten',
                default => 'keine Dateischätzungen bei gemergten Tasks mit PR-Daten',
            },
            'avgDurationPerSp' => $avgDurationPerSp,
            'hits' => $withDeviation->filter(fn (array $r) => abs($r['deviationPct']) <= 25)->count(),
            'hitsTotal' => $withDeviation->count(),
        ];
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
}
