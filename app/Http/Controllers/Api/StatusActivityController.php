<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ProjectPerformanceController;
use App\Models\Project;
use App\Models\User;
use App\Support\StatusActivityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zähler der Statusupdates je Tag und Stunde — die Datenbasis der Aktivitäts-Heatmap,
 * in drei Geltungsbereichen:
 *
 *   GET /api/projects/{project}/status-activity   ein Projekt (Performance-Seite)
 *   GET /api/organization/status-activity         die eigene Organisation
 *   GET /api/users/{user}/status-activity         die eigenen Updates einer Person
 *
 * Alle nehmen ?days=182&tz=Europe/Berlin. Eigener Endpunkt, weil die Zeitpunkte im
 * Änderungsprotokoll stehen und sich nicht aus dem geteilten Tasks-Store ableiten
 * lassen; der Client lädt das größte Fenster einmal gecacht und schneidet die
 * kürzeren Zeiträume selbst zu.
 *
 * Die Rechte spiegeln je Bereich die Seite, die die Karte zeigt — die Zahlen sollen
 * über die API nicht weiter reichen als dort:
 *  - Projekt und Organisation: nur der Organisations-Owner (wie
 *    {@see ProjectPerformanceController}),
 *  - Person: sie selbst, sonst nur der Organisations-Owner (wie
 *    {@see \App\Http\Controllers\UserStatisticsController}).
 */
class StatusActivityController extends ApiController
{
    public function __construct(private readonly StatusActivityPresenter $presenter) {}

    public function project(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->abortUnlessOrgOwner($request);

        return response()->json(
            $this->presenter->forProject($project, $this->days($request), $request->query('tz')),
        );
    }

    public function organization(Request $request): JsonResponse
    {
        $this->abortUnlessOrgOwner($request);

        return response()->json(
            $this->presenter->forOrganization(
                $request->user()->organization,
                $this->days($request),
                $request->query('tz'),
            ),
        );
    }

    public function user(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();
        $isSelf = $viewer->id === $user->id;

        // 404 statt 403 über Organisationsgrenzen: dass es diesen Nutzer gibt, ist
        // außerhalb der eigenen Organisation nichts, was die Antwort verraten müsste.
        abort_unless($isSelf || $user->organization_id === $viewer->organization_id, 404);
        abort_unless($isSelf || $viewer->organization?->isOwner($viewer) === true, 403);

        return response()->json(
            $this->presenter->forUser($user, $this->days($request), $request->query('tz')),
        );
    }

    private function abortUnlessOrgOwner(Request $request): void
    {
        $user = $request->user();

        abort_unless($user->organization?->isOwner($user) === true, 403);
    }

    private function days(Request $request): int
    {
        return (int) $request->query('days', (string) StatusActivityPresenter::DEFAULT_DAYS);
    }
}
