<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Support\TaskTimelinePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/projects/{project}/timeline?days=30 — die Status-Aufenthalte der letzten
 * N Tage je Task (Grundlage der Balken auf der Zeitachsen-Unterseite).
 *
 * Eigener Endpunkt, weil der Verlauf im Änderungsprotokoll steckt und sich nicht aus
 * dem geteilten Tasks-Store ableiten lässt. Der Client lädt ihn gecacht (einmal je
 * Projekt) und ergänzt damit die Tasks, die er ohnehin hat.
 */
class TimelineController extends ApiController
{
    public function __construct(private readonly TaskTimelinePresenter $presenter) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $days = (int) $request->query('days', (string) TaskTimelinePresenter::DEFAULT_DAYS);

        return response()->json($this->presenter->payload($project, $days));
    }
}
