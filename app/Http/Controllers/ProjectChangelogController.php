<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectChangelogController extends Controller
{
    /**
     * Der Changelog ist Teil der EINEN Inertia-Seite `ProjectWorkspace`; der
     * Tab-Wechsel läuft danach clientseitig ohne Server-Call. Diese Route rendert
     * den Workspace mit aktivem Changelog-Tab. Den (paginierten) Feed lädt der
     * Client über GET /api/projects/{alias}/changelog (siehe ChangelogPresenter).
     */
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'changelog'));
    }
}
