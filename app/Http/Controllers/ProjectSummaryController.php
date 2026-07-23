<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectSummaryController extends Controller
{
    /**
     * Die Summary-Seite (KPI-Kacheln, Phasen-Fortschritt, pickbare PRs).
     *
     * Daten werden NICHT mehr serverseitig berechnet: Board und Summary leben aus
     * demselben geteilten React-Store (resources/js/data), der Tasks + Phasen +
     * Status-Konfiguration einmalig lädt und per Socket partiell nachlädt. Diese
     * Seite liefert nur noch die statischen Render-Props: Projekt-URLs, Rechte,
     * Tabs, Flash und die (teils als Roh-Template gelieferten) i18n-Strings. Die
     * eigentliche Aggregation passiert clientseitig in resources/js/summary/derive.js.
     */
    public function __invoke(Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $user = Auth::user();

        return Inertia::render('ProjectSummary', [
            'project' => [
                'alias' => $project->alias,
                'name' => $project->name,
                'showUrl' => route('projects.show', $project),
                'editUrl' => route('projects.edit', $project),
                'syncUrl' => route('projects.sync-prs', $project),
                'taskCreateUrl' => route('projects.tasks.create', $project),
                // URL-Template für Task-Detaillinks; der Client ersetzt __ID__
                // (in React gibt es weder route() noch __()).
                'taskUrlTemplate' => route('projects.tasks.show', [$project, '__ID__']),
            ],
            'can' => [
                'update' => $user->can('update', $project),
                'contribute' => $user->can('contribute', $project),
            ],
            'tabs' => $this->tabs($project),
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
                'nothingPickable' => __('status.nothing_pickable_right_now'),
                'bestPick' => __('status.best_pick'),
                'loading' => __('status.loading'),
                'loadError' => __('status.load_error'),
                // KPI-/Ableitungs-Labels (statisch)
                'progress' => __('common.progress'),
                'storyPoints' => __('common.story_points'),
                'velocityTitle' => __('status.velocity'),
                'spWk' => __('status.sp_wk'),
                'lastMergeTitle' => __('status.last_merge'),
                // Roh-Templates: __() ohne Ersetzung lässt die :platzhalter stehen,
                // die der Client (resources/js/summary/i18n.js) interpoliert.
                'doneOfTotalPrs' => __('status.done_of_total_prs_done'),
                'doneOfTotalSp' => __('status.done_of_total_sp_done'),
                'doneOfTotalFiles' => __('status.done_of_total_files_done'),
                'doneOfTotalTokens' => __('status.done_of_total_tokens_done'),
                'forecastEta' => __('status.forecast_done_around_eta'),
                'openPrsCount' => __('status.open_prs_count'),
                'blockedByBlocker' => __('status.blocked_by_blocker'),
                'unlocksFollowupPrs' => __('status.unlocks_followup_prs'),
                'pickablePrsCount' => __('status.pickable_prs_count'),
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
}
