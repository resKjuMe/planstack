<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;

/**
 * Bündelt die statischen Render-Props der Projekt-Unterseiten (Board + Summary)
 * für die EINE Inertia-Seite `ProjectWorkspace`. Beide Web-Routen (Board, Summary)
 * rendern dieselbe Seite mit unterschiedlichem `activeTab`; der Tab-Wechsel
 * passiert danach rein clientseitig (0 Server-Calls, URL via History-API). Die
 * eigentlichen Daten (Tasks/Phasen) lädt der geteilte Store einmalig über die API.
 *
 * Weitere Unterseiten (Diagramm, PR-Sequence, …) können hier nach demselben Muster
 * ergänzt werden.
 */
class ProjectWorkspacePresenter
{
    public function __construct(private readonly BoardPresenter $board) {}

    /**
     * @return array<string, mixed>
     */
    public function props(Project $project, string $activeTab): array
    {
        $user = Auth::user();

        return [
            'activeTab' => $activeTab,
            'currentUserId' => $user?->id,
            'project' => [
                'alias' => $project->alias,
                'name' => $project->name,
                'showUrl' => route('projects.show', $project),
                'editUrl' => route('projects.edit', $project),
                'syncUrl' => route('projects.sync-prs', $project),
                'taskCreateUrl' => route('projects.tasks.create', $project),
                // URL-Templates für den Client (er ersetzt __ID__): Task-Detaillink
                // (Summary/Diagramm) und „claim review" (Diagramm).
                'taskUrlTemplate' => route('projects.tasks.show', [$project, '__ID__']),
                'reviewClaimUrlTemplate' => route('projects.tasks.review-claim', [$project, '__ID__']),
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
            'board' => [
                'meta' => $this->board->meta($project),
                'strings' => $this->boardStrings(),
            ],
            'summary' => [
                'strings' => $this->summaryStrings(),
            ],
            'diagram' => [
                'strings' => $this->diagramStrings(),
            ],
            'sequence' => [
                'strings' => $this->sequenceStrings(),
            ],
            'calibration' => [
                'strings' => $this->calibrationStrings(),
            ],
        ];
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
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function boardStrings(): array
    {
        return [
            'boardTitle' => __('common.board'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'sync' => __('components.sync'),
            'settings' => __('components.settings'),
            'task' => __('components.task'),
            'syncConfirm' => __('components.fetch_the_merge_status_of_all_open_prs'),
            'helpBullets' => [
                ['strong' => __('common.board'), 'text' => __('projects.all_tasks_of_the_project_by_status_in')],
                ['text' => __('projects.each_card_shows_the_task_key_summary')],
                ['strong' => __('projects.claim_release'), 'text' => __('projects.claim_a_task_or_release_it_again')],
                ['text' => __('projects.who_has_access_and_which_role_applies')],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryStrings(): array
    {
        return [
            'title' => __('common.summary'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'sync' => __('components.sync'),
            'settings' => __('components.settings'),
            'task' => __('components.task'),
            'syncConfirm' => __('components.fetch_the_merge_status_of_all_open_prs'),
            'helpBullets' => [
                ['strong' => __('common.summary'), 'text' => __('status.overview_of_the_project_s_progress')],
                ['text' => __('status.kpi_tiles_progress_completed_prs_story')],
                ['strong' => __('common.phases'), 'text' => __('status.colored_status_bars_hover_shows_count')],
                ['strong' => __('status.pickable_prs'), 'text' => __('status.tasks_you_can_start_right_now_the_first')],
            ],
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diagramStrings(): array
    {
        return [
            'title' => __('common.diagram'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'helpBullets' => [
                ['strong' => __('status.dependency_diagram'), 'text' => __('status.arrows_point_from_a_prerequisite_to_the')],
                ['text' => __('status.node_color_icon_status_see_legend_thick')],
                ['text' => __('status.edges_solid_open_dependency_light')],
                ['text' => __('status.clicking_a_node_highlights_its_chain')],
            ],
            'clickToFilter' => __('status.click_to_filter'),
            'clearSelection' => __('status.clear_selection'),
            'shortDescriptions' => __('status.short_descriptions'),
            'hideDone' => __('status.hide_done'),
            'asPng' => __('status.as_png'),
            'openDependency' => __('status.open_dependency'),
            'satisfied' => __('status.satisfied'),
            'bottleneck' => __('status.bottleneck'),
            'noOpenPrs' => __('status.no_open_prs'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sequenceStrings(): array
    {
        return [
            'title' => __('common.pr_sequence'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'helpBullets' => [
                ['strong' => __('common.pr_sequence'), 'text' => __('status.recommended_order_for_working_through')],
                ['text' => __('status.metrics_open_prs_total_story_points')],
                ['strong' => __('status.bottleneck'), 'text' => __('status.blocks_many_downstream_prs_finishing')],
                ['text' => __('status.the_filter_pills_narrow_to_pickable')],
            ],
            // Kennzahlen-Kacheln
            'openPrs' => __('status.open_prs'),
            'totalStoryPoints' => __('status.total_story_points'),
            'blocks' => __('common.blocks'),
            'criticalPath' => __('status.critical_path'),
            // Filter-Pills
            'all' => __('common.all'),
            'pickable' => __('status.pickable'),
            'concerns' => __('status.concerns'),
            'claimed' => __('status.claimed'),
            // Zeile
            'pos' => __('status.pos'),
            'bottleneck' => __('status.bottleneck'),
            'largePr' => __('status.large_pr'),
            'largestPr' => __('status.largest_pr'),
            'files' => __('status.files'),
            'tokens' => __('status.tokens'),
            'claimedBy' => __('status.claimed_by'),
            'since' => __('status.since'),
            'waitingOn' => __('status.waiting_on'),
            'noOpenPrs' => __('status.no_open_prs'),
            'noPrsInFilter' => __('status.no_prs_in_this_filter'),
            'loading' => __('status.loading'),
            // Roh-Templates (Client interpoliert :platzhalter / trans_choice via |)
            'dependDirectly' => __('status.count_prs_depend_directly_on_this_one'),
            'blocksPrs' => __('status.blocks_prs'),
            'showMoreBlocked' => __('status.show_count_more_blocked_prs'),
            'hideMoreBlocked' => __('status.hide_count_more_blocked_prs'),
            'completedPrs' => __('status.completed_prs'),
            'workWithClaude' => __('status.work_through_with_claude_l2lr_name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calibrationStrings(): array
    {
        return [
            'title' => __('common.calibration'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'loading' => __('status.loading'),
            'totalMergedTasks' => __('status.total_merged_tasks'),
            'lastSyncedTime' => __('status.last_synced_time'),
            // Hilfe-Abschnitte
            'metrics' => __('status.metrics'),
            'chartsTable' => __('status.charts_table'),
            'helpMetrics' => [
                ['strong' => __('status.median_deviation'), 'text' => __('status.typical_deviation_of_the_actually')],
                ['strong' => __('status.velocity'), 'text' => __('status.completed_story_points_per_day_measured')],
                ['strong' => __('status.accuracy_25'), 'text' => __('status.share_of_tasks_whose_file_deviation_is')],
                ['strong' => __('status.data_basis'), 'text' => __('status.how_many_of_the_merged_tasks_have_a')],
            ],
            'helpCharts' => [
                ['strong' => __('status.estimated_vs_actual'), 'text' => __('status.one_task_per_point_x_estimated_y')],
                ['strong' => __('status.accuracy_by_sp'), 'text' => __('status.hit_rate_grouped_by_task_size_shows')],
                ['strong' => __('status.deviation'), 'text' => __('status.changed_estimated_estimated_green_25')],
                ['strong' => __('status.outliers'), 'text' => __('status.deviation_over_50')],
                ['strong' => __('status.time_sp'), 'text' => __('status.calendar_time_from_claim_merge_divided')],
                ['strong' => __('status.no_estimate_2'), 'text' => __('status.task_with_no_file_count_on_record')],
            ],
            // KPI-Kacheln
            'medianDeviation' => __('status.median_deviation'),
            'velocity' => __('status.velocity'),
            'spDay' => __('status.sp_day'),
            'perSp' => __('status.per_sp'),
            'claimMerge' => __('status.claim_merge'),
            'accuracy25' => __('status.accuracy_25'),
            'dataBasis' => __('status.data_basis'),
            'tasksWithEstimate' => __('status.tasks_with_a_file_estimate'),
            // Warnbanner
            'noEstimateNote' => __('status.no_estimate_note'),
            'show' => __('status.show'),
            // Panels
            'estimatedVsActual' => __('status.estimated_vs_actual'),
            'filesPerTaskDiagonal' => __('status.files_per_task_diagonal_perfect_estimate'),
            'estimated' => __('status.estimated'),
            'changed' => __('status.changed'),
            'noTasksWithEstimate' => __('status.no_tasks_with_a_file_estimate'),
            'accuracyBySp' => __('status.accuracy_by_sp'),
            'shareWithin25' => __('status.share_within_25'),
            // Tabs + Sortierung
            'all' => __('common.all'),
            'outliersOnly' => __('status.outliers_only'),
            'noEstimate' => __('status.no_estimate'),
            'groupedBySp' => __('status.grouped_by_sp'),
            'sort' => __('status.sort'),
            'deviation' => __('status.deviation'),
            'storyPoints' => __('common.story_points'),
            'date' => __('status.date'),
            'timeSp' => __('status.time_sp'),
            // Tabelle
            'task' => __('status.task'),
            'filesEstimatedChanged' => __('status.files_estimated_changed'),
            'noEstimate2' => __('status.no_estimate_2'),
            'noEntries' => __('status.no_entries'),
            'avgToMerge' => __('status.avg_to_merge'),
            'tasks' => __('common.tasks'),
            'days' => __('status.days'),
        ];
    }
}
