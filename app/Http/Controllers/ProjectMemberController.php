<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ProjectMemberController extends Controller
{
    /**
     * Set (or override) a user's role within the project. Access itself is
     * granted via team assignment; this only records the role distribution.
     */
    public function update(UpdateMemberRequest $request, Project $project, User $user): RedirectResponse
    {
        if ($project->isOwner($user)) {
            return back()->withErrors(['role' => 'Die Rolle des Owners kann nicht geändert werden.']);
        }

        if (! $project->hasMember($user)) {
            return back()->withErrors(['role' => 'Dieser User hat keinen Team-Zugriff auf das Projekt.']);
        }

        $project->members()->syncWithoutDetaching([
            $user->id => ['role' => $request->validated('role')],
        ]);

        return back()->with('status', 'Rolle aktualisiert.');
    }

    /**
     * Remove the explicit role row, reverting the user to the default WORKER role.
     */
    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        if ($project->isOwner($user)) {
            return back()->withErrors(['role' => 'Die Owner-Rolle kann nicht zurückgesetzt werden.']);
        }

        $project->members()->detach($user->id);

        return back()->with('status', 'Rolle auf WORKER zurückgesetzt.');
    }
}
