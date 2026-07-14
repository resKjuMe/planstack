<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectTeamRequest;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class ProjectTeamController extends Controller
{
    public function store(StoreProjectTeamRequest $request, Project $project): RedirectResponse
    {
        $project->teams()->syncWithoutDetaching([(int) $request->validated('team_id')]);

        return back()->with('status', 'Team dem Projekt zugewiesen.');
    }

    public function destroy(Project $project, Team $team): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $project->teams()->detach($team->id);

        return back()->with('status', 'Team-Zuweisung entfernt.');
    }
}
