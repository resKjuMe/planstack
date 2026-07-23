<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\StatusSegments;
use App\Support\TaskBoardService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectSummaryController extends Controller
{
    public function __construct(
        private readonly TaskBoardService $board,
        private readonly StatusSegments $segments,
    ) {}

    /**
     * KPI tiles, per-phase progress and pickable-PR cards.
     *
     * Vollständig als React-Inertia-Seite (ProjectSummary): Kopfzeile, Tabs,
     * Seitenkopf und die drei Blöcke (KPI-Kacheln, Phasen-Fortschritt, pickbare
     * PRs) sind React. Alle Daten kommen als bereits aufbereitete Props — inkl.
     * Route-URLs und fertig interpolierter i18n-Strings, da es in React weder
     * route() noch __() gibt.
     */
    public function __invoke(Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $tasks = $this->board->decorate($project);
        $byId = $tasks->keyBy('id');
        $this->board->attachUnlocks($tasks);
        // Attach per-task display status (label + badge) from the org's
        // configurable statuses so custom statuses show through everywhere here
        // (open-PR badges below and the phase progress bars).
        $this->segments->annotate($project, $tasks);

        $user = Auth::user();
        $project->load('phases');

        $pickable = $tasks->filter(fn ($t) => $t->x_pickable)->sortByDesc('x_unlocks')->values();

        return Inertia::render('ProjectSummary', [
            'project' => [
                'alias' => $project->alias,
                'name' => $project->name,
                'showUrl' => route('projects.show', $project),
                'editUrl' => route('projects.edit', $project),
                'syncUrl' => route('projects.sync-prs', $project),
                'taskCreateUrl' => route('projects.tasks.create', $project),
            ],
            'can' => [
                'update' => $user->can('update', $project),
                'contribute' => $user->can('contribute', $project),
            ],
            'tabs' => $this->tabs($project),
            'kpis' => $this->kpis($tasks),
            'rows' => $this->phaseRows($project, $tasks, $byId),
            'pickable' => $this->pickableCards($project, $pickable),
            'flash' => [
                'status' => session('status'),
                'error' => session('error'),
            ],
            'strings' => [
                'title' => __('common.summary'),
                'showHideExplanation' => __('common.show_hide_explanation'),
                'helpBullets' => [
                    ['strong' => __('common.summary'), 'text' => __('status.overview_of_the_project_s_progress')],
                    ['text' => __('status.kpi_tiles_progress_completed_prs_story')],
                    ['strong' => __('common.phases'), 'text' => __('status.colored_status_bars_hover_shows_count')],
                    ['strong' => __('status.pickable_prs'), 'text' => __('status.tasks_you_can_start_right_now_the_first')],
                ],
                'sync' => __('components.sync'),
                'settings' => __('components.settings'),
                'task' => __('components.task'),
                'syncConfirm' => __('components.fetch_the_merge_status_of_all_open_prs'),
                'phasesTitle' => __('common.phases'),
                'showDetails' => __('status.show_details'),
                'hideDetails' => __('status.hide_details'),
                'rem' => __('status.rem'),
                'planned' => __('status.planned'),
                'files' => __('status.files'),
                'tokens' => __('status.tokens'),
                'pickableTitle' => __('status.pickable_prs_count', ['count' => $pickable->count()]),
                'nothingPickable' => __('status.nothing_pickable_right_now'),
                'bestPick' => __('status.best_pick'),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tabs(Project $project): array
    {
        return collect([
            'diagram' => ['label' => __('common.diagram'), 'route' => 'projects.diagram'],
            'pr-sequence' => ['label' => __('common.pr_sequence'), 'route' => 'projects.pr-sequence'],
            'summary' => ['label' => __('common.summary'), 'route' => 'projects.summary'],
            'board' => ['label' => __('common.board'), 'route' => 'projects.show'],
            'changelog' => ['label' => __('common.changelog'), 'route' => 'projects.changelog'],
            'calibration' => ['label' => __('common.calibration'), 'route' => 'projects.calibration'],
        ])->map(fn (array $t, string $key) => [
            'key' => $key,
            'label' => $t['label'],
            'href' => route($t['route'], $project),
            'active' => $key === 'summary',
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(Collection $tasks): array
    {
        // Fortschritt zählt bewusst nur erledigte/gemergte Tasks (COMPLETED/MERGED)
        // — ein bloß offener PR gilt nicht als Fortschritt (deckungsgleich mit der
        // Projektübersicht). Für Gate-/Blocker-Logik gilt weiterhin isDelivered()
        // (ein offener PR schaltet Nachfolger frei), siehe phaseBlockers().
        $total = $tasks->count();
        $done = $tasks->filter(fn ($t) => $this->board->isDone($t));
        $remaining = $tasks->filter(fn ($t) => ! $this->board->isDone($t));

        $totalSp = (int) $tasks->sum('effort_story_points');
        $doneSp = (int) $done->sum('effort_story_points');
        $totalFiles = (int) $tasks->sum('affected_files');
        $doneFiles = (int) $done->sum('affected_files');
        $totalTokens = (int) $tasks->sum('effort_tokens');
        $doneTokens = (int) $done->sum('effort_tokens');

        $progressPct = $total ? (int) round($done->count() / $total * 100) : 0;
        $spPct = $totalSp ? (int) round($doneSp / $totalSp * 100) : 0;
        $filesPct = $totalFiles ? (int) round($doneFiles / $totalFiles * 100) : 0;
        $tokensPct = $totalTokens ? (int) round($doneTokens / $totalTokens * 100) : 0;

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
                'when' => $last->merged_at->locale(app()->getLocale())->diffForHumans(),
                'pr' => $last->pr_number ? '#'.$last->pr_number : $last->name,
            ];

            $weeks = max(1 / 7, $merged->first()->merged_at->floatDiffInDays(now()) / 7);
            $rate = $merged->sum('effort_story_points') / $weeks;

            if ($rate > 0) {
                $eta = now()->addWeeks((int) ceil($remaining->sum('effort_story_points') / $rate));
                $etaLabel = 'KW '.$eta->isoWeek().'/'.$eta->isoWeekYear();
                $velocity = [
                    'title' => __('status.velocity'),
                    'rate' => $this->deTrim(round($rate, 1)),
                    'unit' => __('status.sp_wk'),
                    'sub' => __('status.forecast_done_around_eta', ['eta' => $etaLabel]),
                ];
            }
        }

        return [
            'tiles' => [
                [
                    'title' => __('common.progress'),
                    'pct' => $progressPct,
                    'sub' => __('status.done_of_total_prs_done', ['done' => $done->count(), 'total' => $total]),
                ],
                [
                    'title' => __('common.story_points'),
                    'pct' => $spPct,
                    'sub' => __('status.done_of_total_sp_done', ['done' => $doneSp, 'total' => $totalSp]),
                ],
                [
                    'title' => __('status.files'),
                    'pct' => $filesPct,
                    'sub' => __('status.done_of_total_files_done', ['done' => $doneFiles, 'total' => $totalFiles]),
                ],
                [
                    'title' => __('status.tokens'),
                    'pct' => $tokensPct,
                    'sub' => __('status.done_of_total_tokens_done', [
                        'done' => $this->board->formatTokens($doneTokens),
                        'total' => $this->board->formatTokens($totalTokens),
                    ]),
                ],
            ],
            'velocity' => $velocity,
            'lastMerge' => $lastMerge ? array_merge($lastMerge, ['title' => __('status.last_merge')]) : null,
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
            // Fortschritt/„verbleibend" nur nach erledigt/gemergt (nicht offener PR).
            $remaining = $pt->filter(fn ($t) => ! $this->board->isDone($t));

            // Phases that hold unfinished prerequisites of this phase's tasks.
            $blockers = $this->phaseBlockers($phase, $pt, $byId);

            // One entry per status present in this phase: badge + bar segment
            // (SP-based), from the org's configurable statuses (incl. custom ones).
            $statuses = collect($this->segments->segments($project, $pt))
                ->map(fn (array $s) => array_merge($s, [
                    // German-formatted width for the hover readout (matches the
                    // former Blade number_format(..., ',', '')).
                    'pctLabel' => number_format((float) $s['width'], 1, ',', ''),
                ]))
                ->all();

            $doneCount = $pt->count() - $remaining->count();

            $rows[] = [
                'phase' => $phase->name,
                'done' => $doneCount,
                'total' => $pt->count(),
                'pct' => $pt->count() ? (int) round($doneCount / $pt->count() * 100) : 0,
                'open_prs' => $remaining->values()->map(fn (Task $t) => [
                    'name' => $t->name,
                    'url' => route('projects.tasks.show', [$project, $t]),
                    'badge' => $t->x_status_badge,
                    'label' => $t->x_status_label,
                    'summary' => $t->summary,
                ])->all(),
                'open_prs_label' => __('status.open_prs_count', ['count' => $remaining->count()]),
                'statuses' => $statuses,
                'pt' => [
                    'remaining' => $this->deTrim((float) $remaining->sum('effort_man_days')),
                    'total' => $this->deTrim((float) $pt->sum('effort_man_days')),
                ],
                'files' => ['remaining' => (int) $remaining->sum('affected_files'), 'total' => (int) $pt->sum('affected_files')],
                'tokens' => [
                    'remaining' => $this->board->formatTokens((int) $remaining->sum('effort_tokens') ?: null),
                    'total' => $this->board->formatTokens((int) $pt->sum('effort_tokens') ?: null),
                ],
                'blocked_by' => array_map(
                    fn (string $blocker) => __('status.blocked_by_blocker', ['blocker' => $blocker]),
                    array_values($blockers),
                ),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Task>  $pickable
     * @return array<int, array<string, mixed>>
     */
    private function pickableCards(Project $project, Collection $pickable): array
    {
        return $pickable->values()->map(fn (Task $t, int $i) => [
            'name' => $t->name,
            'url' => route('projects.tasks.show', [$project, $t]),
            'sp' => $t->effort_story_points,
            'tokens' => $t->x_tokens,
            'files' => $t->affected_files ?? '—',
            'unlocks' => (int) $t->x_unlocks,
            'unlocksLabel' => $t->x_unlocks > 0
                ? trans_choice('status.unlocks_followup_prs', $t->x_unlocks, ['count' => $t->x_unlocks])
                : null,
            'summary' => $t->summary,
            'best' => $i === 0,
        ])->all();
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

    private function phaseShort(string $name): string
    {
        return explode(' ', $name)[0];
    }

    /**
     * German decimal with trailing zeros/commas trimmed (2.0 → "2", 2.5 → "2,5").
     */
    private function deTrim(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
    }
}
