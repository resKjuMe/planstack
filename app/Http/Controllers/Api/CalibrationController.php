<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Support\CalibrationPresenter;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/projects/{project}/calibration — die berechneten Kalibrierungs-Daten
 * (Schätzung vs. Ist, KPIs, Scatter, SP-Treffsicherheit). Der React-Store lädt sie
 * einmalig und cacht sie über die Navigation; ein entity-changed-Event des Projekts
 * löst ein erneutes Laden aus. Die schwere Statistik bleibt serverseitig.
 */
class CalibrationController extends ApiController
{
    public function __construct(private readonly CalibrationPresenter $presenter) {}

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($this->presenter->payload($project));
    }
}
