<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Die Performance-Unterseite ist Teil der EINEN Inertia-Seite `ProjectWorkspace`
 * (wie Board/Summary/Kalibrierung); der Tab-Wechsel läuft danach clientseitig ohne
 * Server-Call. Die Auswertung je Mitarbeiter passiert clientseitig in
 * resources/js/performance/derive.js aus dem geteilten Store.
 *
 * Anders als die übrigen Unterseiten ist sie NUR für den Organisations-Owner
 * zugänglich (Personenbezogene Leistungsdaten). Die Route prüft das hart — der
 * fehlende Tab in der Navigation (siehe ProjectWorkspacePresenter) ist nur die
 * Anzeigeseite derselben Regel.
 */
class ProjectPerformanceController extends Controller
{
    public function __invoke(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        $user = Auth::user();
        abort_unless($user->organization?->isOwner($user) === true, 403);

        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'performance'));
    }
}
