<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectDiagramController extends Controller
{
    /**
     * Das Abhängigkeitsdiagramm ist Teil der EINEN Inertia-Seite `ProjectWorkspace`
     * (Board/Summary/Diagramm); der Tab-Wechsel läuft danach clientseitig ohne
     * Server-Call. Diese Route rendert den Workspace mit aktivem Diagramm-Tab. Das
     * Graph-Modell (Knoten/Kanten, Bottlenecks, Legende) wird clientseitig aus dem
     * geteilten Store abgeleitet (resources/js/diagram/derive.js) und live über
     * entity-changed partiell aktualisiert.
     */
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'diagram'));
    }
}
