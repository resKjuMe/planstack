<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectTimelineController extends Controller
{
    /**
     * Die Zeitachse ist Teil der EINEN Inertia-Seite `ProjectWorkspace` (wie Board
     * und Summary); der Tab-Wechsel läuft danach clientseitig ohne Server-Call.
     * Diese Route rendert den Workspace mit aktivem Zeitachsen-Tab. Der Baum
     * (Abhängigkeiten) entsteht clientseitig aus dem geteilten Store
     * (resources/js/timeline/derive.js), die Balken aus dem Verlaufs-Endpunkt
     * GET /api/projects/{project}/timeline.
     */
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'timeline'));
    }
}
