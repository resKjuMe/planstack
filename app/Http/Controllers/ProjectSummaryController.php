<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Support\TaskBoardService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectSummaryController extends Controller
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * KPI tiles, per-phase progress and pickable-PR cards.
     */
    public function __invoke(Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->board->decorate($project);
        $byId = $tasks->keyBy('id');
        $this->board->attachUnlocks($tasks);

        return view('status.summary', [
            'project' => $project,
            'active' => 'summary',
            'kpis' => $this->kpis($tasks),
            'rows' => $this->phaseRows($project, $tasks, $byId),
            'pickable' => $tasks->filter(fn ($t) => $t->x_pickable)->sortByDesc('x_unlocks')->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(Collection $tasks): array
    {
        $total = $tasks->count();
        $done = $tasks->filter(fn ($t) => $this->board->isDelivered($t));
        $remaining = $tasks->filter(fn ($t) => ! $this->board->isDelivered($t));

        $totalSp = (int) $tasks->sum('effort_story_points');
        $doneSp = (int) $done->sum('effort_story_points');
        $totalFiles = (int) $tasks->sum('affected_files');
        $doneFiles = (int) $done->sum('affected_files');
        $totalTokens = (int) $tasks->sum('effort_tokens');
        $doneTokens = (int) $done->sum('effort_tokens');

        // Velocity + last merge require real merge timestamps.
        $merged = $tasks
            ->filter(fn ($t) => $t->status === TaskStatus::MERGED && $t->merged_at !== null)
            ->sortBy('merged_at')
            ->values();

        $velocity = null;
        $lastMerge = null;

        if ($merged->isNotEmpty()) {
            $last = $merged->last();
            $lastMerge = [
                'when' => $last->merged_at->locale('de')->diffForHumans(),
                'pr' => $last->pr_number ? '#'.$last->pr_number : $last->name,
            ];

            $weeks = max(1 / 7, $merged->first()->merged_at->floatDiffInDays(now()) / 7);
            $rate = $merged->sum('effort_story_points') / $weeks;

            if ($rate > 0) {
                $eta = now()->addWeeks((int) ceil($remaining->sum('effort_story_points') / $rate));
                $velocity = ['rate' => round($rate, 1), 'eta' => 'KW '.$eta->isoWeek().'/'.$eta->isoWeekYear()];
            }
        }

        return [
            'progress' => [
                'pct' => $total ? (int) round($done->count() / $total * 100) : 0,
                'done' => $done->count(),
                'total' => $total,
            ],
            'storyPoints' => [
                'pct' => $totalSp ? (int) round($doneSp / $totalSp * 100) : 0,
                'done' => $doneSp,
                'total' => $totalSp,
            ],
            'files' => [
                'pct' => $totalFiles ? (int) round($doneFiles / $totalFiles * 100) : 0,
                'done' => $doneFiles,
                'total' => $totalFiles,
            ],
            'tokens' => [
                'pct' => $totalTokens ? (int) round($doneTokens / $totalTokens * 100) : 0,
                'done' => $this->board->formatTokens($doneTokens),
                'total' => $this->board->formatTokens($totalTokens),
            ],
            'velocity' => $velocity,
            'lastMerge' => $lastMerge,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function phaseRows(Project $project, Collection $tasks, Collection $byId): array
    {
        $rows = [];

        foreach ($project->phases as $phase) {
            $pt = $tasks->where('phase_id', $phase->id);
            $sp = max(1, (int) $pt->sum('effort_story_points'));
            $remaining = $pt->filter(fn ($t) => ! $this->board->isDelivered($t));

            // Phases that hold unfinished prerequisites of this phase's tasks.
            $blockers = $this->phaseBlockers($phase, $pt, $byId);

            // One entry per status present in this phase: badge + bar segment (SP-based).
            $statuses = [];
            foreach (TaskStatus::displayOrder() as $status) {
                $inStatus = $pt->filter(fn ($t) => $t->x_display_status === $status);
                if ($inStatus->isEmpty()) {
                    continue;
                }
                $statuses[] = [
                    'label' => $status->label(),
                    'count' => $inStatus->count(),
                    'badge' => $status->badgeClasses(),
                    'bar' => $status->barClasses(),
                    'text' => $status->textClasses(),
                    'width' => round((int) $inStatus->sum('effort_story_points') / $sp * 100, 1),
                ];
            }

            $doneCount = $pt->count() - $remaining->count();

            $rows[] = [
                'phase' => $phase->name,
                'short' => $this->phaseShort($phase->name),
                'done' => $doneCount,
                'total' => $pt->count(),
                'pct' => $pt->count() ? (int) round($doneCount / $pt->count() * 100) : 0,
                'open_prs' => $remaining->values(),
                'statuses' => $statuses,
                'pt' => ['remaining' => (float) $remaining->sum('effort_man_days'), 'total' => (float) $pt->sum('effort_man_days')],
                'files' => ['remaining' => (int) $remaining->sum('affected_files'), 'total' => (int) $pt->sum('affected_files')],
                'tokens' => [
                    'remaining' => $this->board->formatTokens((int) $remaining->sum('effort_tokens') ?: null),
                    'total' => $this->board->formatTokens((int) $pt->sum('effort_tokens') ?: null),
                ],
                'blocked_by' => array_values($blockers),
            ];
        }

        return $rows;
    }

    private function phaseShort(string $name): string
    {
        return explode(' ', $name)[0];
    }

    /**
     * Short names of the phases that hold unfinished prerequisites of this
     * phase's remaining tasks (i.e. the phases blocking it).
     *
     * @param  Collection<int, \App\Models\Task>  $phaseTasks
     * @param  Collection<int, \App\Models\Task>  $byId
     * @return array<int, string>
     */
    private function phaseBlockers($phase, Collection $phaseTasks, Collection $byId): array
    {
        $remaining = $phaseTasks->filter(fn ($t) => ! $this->board->isDelivered($t));

        $blockers = [];
        foreach ($remaining as $task) {
            foreach ($task->prerequisites as $parent) {
                if ($this->board->isDelivered($parent)) {
                    continue;
                }
                $parentTask = $byId->get($parent->id);
                if ($parentTask && $parentTask->phase_id !== $phase->id) {
                    $blockers[$parentTask->phase->position] = $this->phaseShort($parentTask->phase->name);
                }
            }
        }
        ksort($blockers);

        return array_values($blockers);
    }
}
