<?php

namespace App\Http\Controllers;

use App\Support\DashboardPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Startseite nach dem Login und Ziel des Logos (/dashboard): die Ich-Sicht über
 * ALLE sichtbaren Projekte — was liegt bei mir an, was ist frei zum Ziehen, wie
 * stehen meine Projekte, was ist zuletzt passiert.
 *
 * Die Auswertung ist projektübergreifend und kommt deshalb fertig vom Server
 * ({@see DashboardPresenter}) — der geteilte React-Store hält immer genau EIN
 * Projekt. Sie steckt direkt in der Antwort und nicht in einer Deferred-Prop: die
 * Seite BESTEHT aus dieser Auswertung, ein zweiter Roundtrip würde den Inhalt nur
 * hinter das ohnehin nötige Laden des JS-Bundles schieben. Task-Änderungen ziehen
 * die `data`-Prop per Partial-Reload nach.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPresenter $presenter): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'person' => [
                'name' => $user->name,
                // Vorname für die Begrüßung; bei einteiligen Namen der ganze Name.
                'firstName' => explode(' ', trim($user->name))[0],
            ],
            'data' => $presenter->payload($user),
            'urls' => [
                'projects' => route('projects.index'),
                'newProject' => route('projects.create'),
                'statistics' => route('statistics', $user),
            ],
            'strings' => $this->strings(),
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
            'title' => __('dashboard.title'),
            'greeting' => __('dashboard.greeting'),
            'subtitle' => __('dashboard.subtitle'),

            // Kopfzahlen
            'kpiActionable' => __('dashboard.kpi_actionable'),
            'kpiActionableSub' => __('dashboard.kpi_actionable_sub'),
            'kpiReviews' => __('dashboard.kpi_reviews'),
            'kpiReviewsSub' => __('dashboard.kpi_reviews_sub'),
            'kpiWeek' => __('dashboard.kpi_week'),
            'kpiWeekSub' => __('dashboard.kpi_week_sub'),
            'kpiOldest' => __('dashboard.kpi_oldest'),
            'kpiOldestSub' => __('dashboard.kpi_oldest_sub'),

            // „Bei mir"
            'myWorkTitle' => __('dashboard.my_work_title'),
            'myWorkSub' => __('dashboard.my_work_sub'),
            'groupWork' => __('dashboard.group_work'),
            'groupWorkHint' => __('dashboard.group_work_hint'),
            'groupReview' => __('dashboard.group_review'),
            'groupReviewHint' => __('dashboard.group_review_hint'),
            'groupBlocked' => __('dashboard.group_blocked'),
            'groupBlockedHint' => __('dashboard.group_blocked_hint'),
            'groupEmpty' => __('dashboard.group_empty'),
            'moreItems' => __('dashboard.more_items'),
            'allClearTitle' => __('dashboard.all_clear_title'),
            'allClearText' => __('dashboard.all_clear_text'),

            // Task-Zeile
            'freeReview' => __('dashboard.free_review'),
            'assignedToMe' => __('dashboard.assigned_to_me'),
            'reviewOfOwnWork' => __('dashboard.review_of_own_work'),
            'ciFailed' => __('dashboard.ci_failed'),
            'openThreads' => __('dashboard.open_threads'),
            'changesRequested' => __('dashboard.changes_requested'),
            'sinceHint' => __('dashboard.since_hint'),
            'claimedFrom' => __('dashboard.claimed_from'),

            // Nebenpanels
            'pickableTitle' => __('dashboard.pickable_title'),
            'pickableSub' => __('dashboard.pickable_sub'),
            'pickableEmpty' => __('dashboard.pickable_empty'),
            'dependents' => __('dashboard.dependents'),
            'projectsTitle' => __('dashboard.projects_title'),
            'projectsSub' => __('dashboard.projects_sub'),
            'projectMine' => __('dashboard.project_mine'),
            'projectOpen' => __('dashboard.project_open'),
            'activityTitle' => __('dashboard.activity_title'),
            'activitySub' => __('dashboard.activity_sub'),
            'activityEmpty' => __('dashboard.activity_empty'),
            'activityYou' => __('dashboard.activity_you'),

            // Verweise + Leerzustände
            'linkProjects' => __('dashboard.link_projects'),
            'linkStatistics' => __('dashboard.link_statistics'),
            'linkNewProject' => __('dashboard.link_new_project'),
            'emptyTitle' => __('dashboard.empty_title'),
            'emptyText' => __('dashboard.empty_text'),

            // Einheiten (formatDuration in resources/js/stats/format.js)
            'unitMin' => __('statistics.unit_min'),
            'unitHours' => __('statistics.unit_hours'),
            'unitDays' => __('statistics.unit_days'),
            'none' => __('statistics.none'),
            'tasks' => __('common.tasks'),
        ];
    }
}
