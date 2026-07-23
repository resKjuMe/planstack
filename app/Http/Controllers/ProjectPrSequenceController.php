<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectPrSequenceController extends Controller
{
    /**
     * Die PR-Sequenz ist Teil der EINEN Inertia-Seite `ProjectWorkspace`; der
     * Tab-Wechsel läuft danach clientseitig ohne Server-Call. Diese Route rendert
     * den Workspace mit aktivem PR-Sequenz-Tab. Die Sequenz (Reihenfolge,
     * Abhängigkeiten, Kennzahlen, Filter) wird clientseitig aus dem geteilten Store
     * abgeleitet (resources/js/prsequence/derive.js) und live aktualisiert.
     */
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'pr-sequence'));
    }
}
