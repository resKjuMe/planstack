<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\StatusActivityController;
use App\Support\OrganizationTabs;
use App\Support\StatusActivityStrings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Organisations-Unterseite „Aktivität": die Aktivitäts-Heatmap über ALLE Projekte der
 * Organisation — wann wird gearbeitet, und von wem.
 *
 * Dieselbe Karte wie auf der Projekt-Performance und in der persönlichen Statistik,
 * nur mit weiterem Geltungsbereich; die Zähler kommen aus demselben Endpunkt
 * ({@see StatusActivityController}), die Seite selbst
 * schickt nur Hülle, URL und Beschriftungen.
 *
 * Owner-only wie die übrigen Organisations-Unterseiten: der Personenfilter macht die
 * Zahlen personenbezogen, und dieselbe Regel gilt schon für „Performance" und für die
 * Statistik eines Kollegen.
 */
class OrganizationActivityController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        abort_unless($organization && $organization->isOwner($user), 403);

        return Inertia::render('OrganizationActivity', [
            'tabs' => OrganizationTabs::for('activity'),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'urls' => ['activity' => route('api.status-activity.organization')],
            'strings' => [
                'title' => __('organization.activity_title'),
                'intro' => __('organization.activity_intro'),
                ...StatusActivityStrings::all(),
            ],
        ]);
    }
}
