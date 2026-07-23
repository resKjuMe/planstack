<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectCalibrationController extends Controller
{
    /**
     * Die Kalibrierung ist Teil der EINEN Inertia-Seite `ProjectWorkspace`; der
     * Tab-Wechsel läuft danach clientseitig ohne Server-Call. Diese Route rendert
     * den Workspace mit aktivem Kalibrierungs-Tab. Die (PR-basierten) Daten lädt der
     * Client gecacht über GET /api/projects/{alias}/calibration (siehe
     * CalibrationPresenter) und aktualisiert sie live per entity-changed.
     */
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'calibration'));
    }
}
