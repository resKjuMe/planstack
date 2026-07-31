<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ProjectPerformanceController;
use App\Models\Project;
use App\Support\StatusActivityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/projects/{project}/status-activity?days=182&tz=Europe/Berlin — Zähler der
 * Statusupdates je Tag und Stunde (Heatmap der Performance-Unterseite).
 *
 * Eigener Endpunkt, weil die Zeitpunkte im Änderungsprotokoll stehen und sich nicht
 * aus dem geteilten Tasks-Store ableiten lassen. Der Client lädt das größte Fenster
 * einmal gecacht und schneidet die kürzeren Zeiträume selbst zu.
 *
 * Owner-only wie die Unterseite selbst ({@see ProjectPerformanceController}):
 * die Zahlen sind zwar aggregiert und nicht personenbezogen, sollen aber nicht über
 * die API weiter reichen als die Seite, die sie zeigt.
 */
class StatusActivityController extends ApiController
{
    public function __construct(private readonly StatusActivityPresenter $presenter) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $user = $request->user();
        abort_unless($user->organization?->isOwner($user) === true, 403);

        $days = (int) $request->query('days', (string) StatusActivityPresenter::DEFAULT_DAYS);

        return response()->json($this->presenter->payload($project, $days, $request->query('tz')));
    }
}
