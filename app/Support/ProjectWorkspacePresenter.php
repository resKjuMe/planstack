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
        // Personenbezogene Leistungsdaten sind Owner-Sache: der Performance-Tab
        // erscheint nur für den Organisations-Owner, und die Route prüft dasselbe
        // noch einmal serverseitig (ProjectPerformanceController).
        $isOrgOwner = $user?->organization?->isOwner($user) === true;

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
                'viewPerformance' => $isOrgOwner,
            ],
            'tabs' => $this->tabs($project, $isOrgOwner),
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
            'changelog' => [
                'strings' => $this->changelogStrings(),
            ],
            'timeline' => [
                'strings' => $this->timelineStrings(),
            ],
            // Owner-only: für andere gar nicht mitschicken, damit die Labels nicht
            // in einer Antwort landen, die die Seite nie rendert.
            'performance' => $isOrgOwner ? [
                'strings' => $this->performanceStrings(),
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tabs(Project $project, bool $isOrgOwner): array
    {
        return collect([
            'diagram' => ['label' => __('common.diagram'), 'route' => 'projects.diagram'],
            // Die Zeitachse zeigt dieselben Abhängigkeiten wie das Diagramm, nur
            // über die Zeit — sie teilt dessen Tab und wird dort umgeschaltet
            // (`hidden`: eigene URL/Deep-Link, aber kein eigener Reiter).
            'timeline' => ['label' => __('common.timeline'), 'route' => 'projects.timeline', 'hidden' => true],
            'pr-sequence' => ['label' => __('common.pr_sequence'), 'route' => 'projects.pr-sequence'],
            'summary' => ['label' => __('common.summary'), 'route' => 'projects.summary'],
            'board' => ['label' => __('common.board'), 'route' => 'projects.show'],
            'changelog' => ['label' => __('common.changelog'), 'route' => 'projects.changelog'],
            'calibration' => ['label' => __('common.calibration'), 'route' => 'projects.calibration'],
            ...($isOrgOwner
                ? ['performance' => ['label' => __('common.performance'), 'route' => 'projects.performance']]
                : []),
        ])->map(fn (array $t, string $key) => [
            'key' => $key,
            'label' => $t['label'],
            'href' => route($t['route'], $project),
            // Nicht in der Reiterleiste, aber Teil der Liste: der Client löst
            // daraus URL und Beschriftung des Umschalters auf.
            'hidden' => $t['hidden'] ?? false,
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
            'forecastEtaDay' => __('status.forecast_eta_day'),
            'mergedToday' => __('status.merged_today'),
            'mergedTodayNone' => __('status.merged_today_none'),
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
            'statusFilter' => __('status.status_filter'),
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
            // Vom Client berechnete Kennzahlen (früher serverseitig):
            'medianHintNoEstimates' => __('calibration.median_hint_no_estimates'),
            'medianHintTooSmall' => __('calibration.median_hint_too_small'),
            'medianHintTooLarge' => __('calibration.median_hint_too_large'),
            'medianHintOk' => __('calibration.median_hint_ok'),
            'accuracyTip' => __('calibration.accuracy_tip'),
            'unitMin' => __('calibration.unit_min'),
            'unitHours' => __('calibration.unit_hours'),
            'unitDays' => __('calibration.unit_days'),
        ];
    }

    /**
     * Labels der Performance-Unterseite. Templates mit :platzhaltern bleiben roh
     * (der Client interpoliert sie, siehe resources/js/summary/i18n.js).
     *
     * @return array<string, mixed>
     */
    private function performanceStrings(): array
    {
        return [
            'title' => __('common.performance'),
            'subtitle' => __('performance.subtitle'),
            'ownerOnlyNote' => __('performance.owner_only_note'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'loading' => __('status.loading'),
            'loadError' => __('status.load_error'),
            'tasks' => __('common.tasks'),

            // Hilfe-Infobox
            'helpAttribution' => __('performance.help_attribution'),
            'helpAttributionText' => __('performance.help_attribution_text'),
            'helpMetrics' => __('performance.help_metrics'),
            'helpMetricBullets' => [
                ['strong' => __('performance.help_delivered'), 'text' => __('performance.help_delivered_text')],
                ['strong' => __('performance.help_cycle'), 'text' => __('performance.help_cycle_text')],
                ['strong' => __('performance.help_wip'), 'text' => __('performance.help_wip_text')],
                ['strong' => __('performance.help_accuracy'), 'text' => __('performance.help_accuracy_text')],
                ['strong' => __('performance.help_durations'), 'text' => __('performance.help_durations_text')],
                ['strong' => __('performance.help_heatmap'), 'text' => __('performance.help_heatmap_text')],
                ['strong' => __('performance.help_quality'), 'text' => __('performance.help_quality_text')],
            ],
            'helpReviews' => __('performance.help_reviews'),
            'helpReviewsText' => __('performance.help_reviews_text'),
            'helpLimits' => __('performance.help_limits'),
            'helpLimitsText' => __('performance.help_limits_text'),

            // Team-Kacheln
            'teamPeople' => __('performance.team_people'),
            'teamPeopleSub' => __('performance.team_people_sub'),
            'teamDelivered' => __('performance.team_delivered'),
            'teamDeliveredSub' => __('performance.team_delivered_sub'),
            'teamCycle' => __('performance.team_cycle'),
            'teamCycleSub' => __('performance.team_cycle_sub'),
            'teamAccuracy' => __('performance.team_accuracy'),
            'teamAccuracySub' => __('performance.team_accuracy_sub'),

            // Vergleichsdiagramme
            'chartDeliveredSp' => __('performance.chart_delivered_sp'),
            'chartDeliveredSpSub' => __('performance.chart_delivered_sp_sub'),
            'chartCycle' => __('performance.chart_cycle'),
            'chartCycleSub' => __('performance.chart_cycle_sub'),
            'chartOpen' => __('performance.chart_open'),

            // Tabelle
            'tableTitle' => __('performance.table_title'),
            'colPerson' => __('performance.col_person'),
            'colDelivered' => __('performance.col_delivered'),
            'colOpen' => __('performance.col_open'),
            'colCycle' => __('performance.col_cycle'),
            'colAccuracy' => __('performance.col_accuracy'),
            'colQuality' => __('performance.col_quality'),
            'colVolume' => __('performance.col_volume'),
            'colReviews' => __('performance.col_reviews'),
            'sort' => __('performance.sort'),
            'sortDeliveredSp' => __('performance.sort_delivered_sp'),
            'sortDelivered' => __('performance.sort_delivered'),
            'sortCycle' => __('performance.sort_cycle'),
            'sortAccuracy' => __('performance.sort_accuracy'),
            'sortOpen' => __('performance.sort_open'),
            'sortReviews' => __('performance.sort_reviews'),
            'sortDuration' => __('performance.sort_duration'),
            'colDuration' => __('performance.col_duration'),

            // Verweildauer je Status (geteilte Bausteine mit der persönlichen
            // Statistik — die Schlüssel heißen dort gleich).
            'durationsTitle' => __('performance.durations_title'),
            'durationsSub' => __('performance.durations_sub'),
            'durationsMeta' => __('performance.durations_meta'),
            'durationsOpenHint' => __('performance.durations_open_hint'),
            'durationsReturnsHint' => __('performance.durations_returns_hint'),
            'durationsMedian' => __('performance.durations_median'),
            'durationsMedianTask' => __('performance.durations_median_task'),
            'durationsAvg' => __('performance.durations_avg'),
            'durationsPerVisit' => __('performance.durations_per_visit'),
            'durationsTotal' => __('performance.durations_total'),
            'durationsEmpty' => __('performance.durations_empty'),

            // Aktivitäts-Heatmap (eigener Endpunkt, siehe StatusActivityPresenter)
            'heatmapTitle' => __('performance.heatmap_title'),
            'heatmapSub' => __('performance.heatmap_sub'),
            'heatmapPerson' => __('performance.heatmap_person'),
            'heatmapPersonAll' => __('performance.heatmap_person_all'),
            'heatmapPersonOption' => __('performance.heatmap_person_option'),
            'heatmapRange' => __('performance.heatmap_range'),
            'heatmapRangeWeeks' => __('performance.heatmap_range_weeks'),
            'heatmapTotal' => __('performance.heatmap_total'),
            'heatmapBusiest' => __('performance.heatmap_busiest'),
            'heatmapCell' => __('performance.heatmap_cell'),
            'heatmapLegendLess' => __('performance.heatmap_legend_less'),
            'heatmapLegendMore' => __('performance.heatmap_legend_more'),
            'heatmapEmpty' => __('performance.heatmap_empty'),
            'heatmapEmptyPerson' => __('performance.heatmap_empty_person'),
            'sortName' => __('performance.sort_name'),
            'noPeople' => __('performance.no_people'),
            'unassigned' => __('performance.unassigned'),
            'unassignedSub' => __('performance.unassigned_sub'),

            // Detailkarte
            'detailsDelivery' => __('performance.details_delivery'),
            'detailsQuality' => __('performance.details_quality'),
            'detailsVolume' => __('performance.details_volume'),
            'detailsReviews' => __('performance.details_reviews'),
            'mDeliveredTasks' => __('performance.m_delivered_tasks'),
            'mDeliveredSp' => __('performance.m_delivered_sp'),
            'mVelocity' => __('performance.m_velocity'),
            'mCycleMedian' => __('performance.m_cycle_median'),
            'mCycleAvg' => __('performance.m_cycle_avg'),
            'mTimePerSp' => __('performance.m_time_per_sp'),
            'mWip' => __('performance.m_wip'),
            'mOldestClaim' => __('performance.m_oldest_claim'),
            'mBlocked' => __('performance.m_blocked'),
            'mConcerns' => __('performance.m_concerns'),
            'mAccuracy' => __('performance.m_accuracy'),
            'mMedianDeviation' => __('performance.m_median_deviation'),
            'mRework' => __('performance.m_rework'),
            'mReworkMultiple' => __('performance.m_rework_multiple'),
            'mRequestChanges' => __('performance.m_request_changes'),
            'neverSynced' => __('performance.never_synced'),
            'criticalityUnset' => __('performance.criticality_unset'),
            'criticalityKnown' => __('performance.criticality_known'),
            'mApproved' => __('performance.m_approved'),
            'mCiFailed' => __('performance.m_ci_failed'),
            'mOpenThreads' => __('performance.m_open_threads'),
            'mCriticality' => __('performance.m_criticality'),
            'mFiles' => __('performance.m_files'),
            'mLines' => __('performance.m_lines'),
            'mCommits' => __('performance.m_commits'),
            'mTokens' => __('performance.m_tokens'),
            'mReviewsGiven' => __('performance.m_reviews_given'),
            'mReviewsAuthors' => __('performance.m_reviews_authors'),
            'mLastReview' => __('performance.m_last_review'),
            'mUnlocks' => __('performance.m_unlocks'),
            'openTasksTitle' => __('performance.open_tasks_title'),
            'showDetails' => __('performance.show_details'),
            'hideDetails' => __('performance.hide_details'),

            // Einheiten
            'unitSpWeek' => __('performance.unit_sp_week'),
            'unitMin' => __('performance.unit_min'),
            'unitHours' => __('performance.unit_hours'),
            'unitDays' => __('performance.unit_days'),
            'ofTotal' => __('performance.of_total'),
            'none' => __('performance.none'),
        ];
    }

    /**
     * Labels der Zeitachse. Templates mit :platzhaltern bleiben roh (der Client
     * interpoliert sie, siehe resources/js/summary/i18n.js).
     *
     * @return array<string, mixed>
     */
    private function timelineStrings(): array
    {
        return [
            'title' => __('common.timeline'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'loading' => __('status.loading'),
            'loadError' => __('status.load_error'),
            'helpBullets' => [
                ['strong' => __('common.timeline'), 'text' => __('status.timeline_help_intro')],
                ['strong' => __('status.timeline_help_tree'), 'text' => __('status.timeline_help_tree_text')],
                ['strong' => __('status.timeline_help_bar'), 'text' => __('status.timeline_help_bar_text')],
                ['strong' => __('status.timeline_help_filters'), 'text' => __('status.timeline_help_filters_text')],
                ['text' => __('status.timeline_help_gaps')],
            ],
            'task' => __('status.task'),
            'today' => __('status.timeline_today'),
            'all' => __('common.all'),
            'activeOnly' => __('status.timeline_active_only'),
            // Status- und Phasenfilter teilt die Zeitachse mit dem Diagramm — auch
            // die Beschriftungen kommen aus denselben Schlüsseln.
            'statusFilter' => __('status.status_filter'),
            'clickToFilter' => __('status.click_to_filter'),
            'expandAll' => __('status.timeline_expand_all'),
            'collapseAll' => __('status.timeline_collapse_all'),
            'dependsOn' => __('status.timeline_depends_on'),
            'noTasks' => __('status.timeline_no_tasks'),
            'noActivity' => __('status.timeline_no_activity'),
            'stillOpen' => __('status.timeline_still_open'),
            'legend' => __('status.timeline_legend'),
            // Roh-Templates: der Client interpoliert die :platzhalter bzw. wertet
            // trans_choice (Singular|Plural über |) selbst aus.
            'windowDays' => __('status.timeline_window_days'),
            'barTooltip' => __('status.timeline_bar_tooltip'),
            'mixedTooltip' => __('status.timeline_mixed_tooltip'),
            'daysCount' => __('status.timeline_days_count'),
            'hoursCount' => __('status.timeline_hours_count'),
            'minutesCount' => __('status.timeline_minutes_count'),
            'dependentsCount' => __('status.timeline_dependents_count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changelogStrings(): array
    {
        return [
            'title' => __('common.changelog'),
            'showHideExplanation' => __('common.show_hide_explanation'),
            'loading' => __('status.loading'),
            'helpBullets' => [
                ['strong' => __('common.changelog'), 'text' => __('status.change_log_of_all_tasks_in_this_project')],
                ['text' => __('status.each_row_shows_the_time_the_changed')],
                ['text' => __('status.n_more_fields_reveals_additional')],
            ],
            'field' => __('common.field'),
            'before' => __('status.before'),
            'after' => __('status.after'),
            'countMoreFields' => __('status.count_more_fields'),
            'showLess' => __('status.show_less'),
            'noChanges' => __('status.no_changes_logged_yet'),
            'loadMore' => __('changelog.load_more'),
        ];
    }
}
