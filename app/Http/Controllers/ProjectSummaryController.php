<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectSummaryController extends Controller
{
    /**
     * Die Summary-Seite ist Teil der EINEN Inertia-Seite `ProjectWorkspace`
     * (zusammen mit dem Board); der Tab-Wechsel läuft danach clientseitig ohne
     * Server-Call. Diese Route rendert den Workspace mit aktivem Summary-Tab. Die
     * eigentliche Aggregation (KPIs, Phasen, pickbare PRs) passiert clientseitig in
     * resources/js/summary/derive.js aus dem geteilten Store.
     */
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'summary'));
    }
}
