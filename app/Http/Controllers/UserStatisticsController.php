<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UserStatisticsPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * „Statistik" (/{user:slug}/stats, z. B. /christian-mietze/stats): die persönliche
 * Bilanz eines Nutzers über ALLE Projekte, die ER sehen darf — aus dem
 * Benutzermenü verlinkt auf die eigene Seite.
 *
 * Zugriff: die eigene Seite immer; die eines Kollegen nur der Organisations-Owner
 * — dieselbe Regel wie auf der Projekt-Unterseite „Performance", damit
 * personenbezogene Leistungsdaten nicht über eine ratbare URL abfließen. Über
 * Organisationsgrenzen hinweg sieht niemand etwas (404 statt 403: dass es diesen
 * Nutzer überhaupt gibt, ist außerhalb der eigenen Organisation nichts, was die
 * Antwort verraten müsste).
 *
 * Die Aggregation liegt im UserStatisticsPresenter (projektübergreifend, also
 * nichts, was der projektbezogene React-Store liefern könnte).
 */
class UserStatisticsController extends Controller
{
    public function __invoke(Request $request, User $user, UserStatisticsPresenter $statistics): InertiaResponse
    {
        $viewer = $request->user();
        $isSelf = $viewer->id === $user->id;

        abort_unless($isSelf || $user->organization_id === $viewer->organization_id, 404);
        abort_unless($isSelf || $viewer->organization?->isOwner($viewer) === true, 403);

        return Inertia::render('Statistics', [
            'stats' => $statistics->payload($user),
            'person' => [
                'name' => $user->name,
                'slug' => $user->slug,
                'isSelf' => $isSelf,
            ],
            'strings' => $this->strings(),
            'urls' => ['projects' => route('projects.index')],
        ]);
    }

    /**
     * Labels der Seite. Templates mit :platzhaltern bleiben roh — der Client
     * interpoliert sie (resources/js/summary/i18n.js).
     *
     * @return array<string, mixed>
     */
    private function strings(): array
    {
        return [
            'title' => __('statistics.title'),
            'subtitle' => __('statistics.subtitle'),
            // Fremde Bilanz (nur Owner): Titel/Untertitel nennen die Person, damit
            // niemand die Zahlen für seine eigenen hält. Roh-Template, der Client
            // interpoliert :name.
            'titleOther' => __('statistics.title_other'),
            'subtitleOther' => __('statistics.subtitle_other'),
            'showHideExplanation' => __('common.show_hide_explanation'),

            'helpScope' => __('statistics.help_scope'),
            'helpScopeText' => __('statistics.help_scope_text'),
            'helpBullets' => [
                ['strong' => __('statistics.help_delivered'), 'text' => __('statistics.help_delivered_text')],
                ['strong' => __('statistics.help_cycle'), 'text' => __('statistics.help_cycle_text')],
                ['strong' => __('statistics.help_accuracy'), 'text' => __('statistics.help_accuracy_text')],
                ['strong' => __('statistics.help_volume'), 'text' => __('statistics.help_volume_text')],
            ],
            'helpLimits' => __('statistics.help_limits'),
            'helpLimitsText' => __('statistics.help_limits_text'),

            // KPI-Kacheln
            'kpiDelivered' => __('statistics.kpi_delivered'),
            'kpiDeliveredSub' => __('statistics.kpi_delivered_sub'),
            'kpiOpen' => __('statistics.kpi_open'),
            'kpiOpenSub' => __('statistics.kpi_open_sub'),
            'kpiCycle' => __('statistics.kpi_cycle'),
            'kpiCycleSub' => __('statistics.kpi_cycle_sub'),
            'kpiAccuracy' => __('statistics.kpi_accuracy'),
            'kpiAccuracySub' => __('statistics.kpi_accuracy_sub'),

            // Abschnitte
            'weeklyTitle' => __('statistics.weekly_title'),
            'weeklySub' => __('statistics.weekly_sub'),
            'statusTitle' => __('statistics.status_title'),
            'statusSub' => __('statistics.status_sub'),
            'qualityTitle' => __('statistics.quality_title'),
            'volumeTitle' => __('statistics.volume_title'),
            'projectsTitle' => __('statistics.projects_title'),
            'recentTitle' => __('statistics.recent_title'),

            // Kennzahlen-Labels
            'mVelocity' => __('statistics.m_velocity'),
            'mCycleAvg' => __('statistics.m_cycle_avg'),
            'mTimePerSp' => __('statistics.m_time_per_sp'),
            'mLastDelivery' => __('statistics.m_last_delivery'),
            'mOldestClaim' => __('statistics.m_oldest_claim'),
            'mMedianDeviation' => __('statistics.m_median_deviation'),
            'mApproved' => __('statistics.m_approved'),
            'mRequestChanges' => __('statistics.m_request_changes'),
            'mCiFailed' => __('statistics.m_ci_failed'),
            'mOpenThreads' => __('statistics.m_open_threads'),
            'mConcerns' => __('statistics.m_concerns'),
            'mCritical' => __('statistics.m_critical'),
            'mReviewsGiven' => __('statistics.m_reviews_given'),
            'mReviewedAuthors' => __('statistics.m_reviewed_authors'),
            'mPrs' => __('statistics.m_prs'),
            'mFiles' => __('statistics.m_files'),
            'mLines' => __('statistics.m_lines'),
            'mCommits' => __('statistics.m_commits'),
            'mComments' => __('statistics.m_comments'),
            'mReviewComments' => __('statistics.m_review_comments'),
            'mTokens' => __('statistics.m_tokens'),
            'mManDays' => __('statistics.m_man_days'),

            // Tabellen
            'colProject' => __('statistics.col_project'),
            'colDelivered' => __('statistics.col_delivered'),
            'colOpen' => __('statistics.col_open'),
            'colCycle' => __('statistics.col_cycle'),
            'colVolume' => __('statistics.col_volume'),
            'colTask' => __('statistics.col_task'),
            'colMerged' => __('statistics.col_merged'),
            'colFiles' => __('statistics.col_files'),
            'colDeviation' => __('statistics.col_deviation'),

            // Leerzustände
            'emptyTitle' => __('statistics.empty_title'),
            'emptyText' => __('statistics.empty_text'),
            'emptyToProjects' => __('statistics.empty_to_projects'),
            'noOpenTasks' => __('statistics.no_open_tasks'),
            'noDeliveries' => __('statistics.no_deliveries'),

            // Einheiten
            'unitSpWeek' => __('statistics.unit_sp_week'),
            'unitMin' => __('statistics.unit_min'),
            'unitHours' => __('statistics.unit_hours'),
            'unitDays' => __('statistics.unit_days'),
            'ofTotal' => __('statistics.of_total'),
            'none' => __('statistics.none'),
            'storyPoints' => __('common.story_points'),
            'tasks' => __('common.tasks'),
        ];
    }
}
