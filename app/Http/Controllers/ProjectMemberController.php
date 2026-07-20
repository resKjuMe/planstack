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
            return back()->withErrors(['role' => __('flash.owner_role_immutable')]);
        }

        if (! $project->hasMember($user)) {
            return back()->withErrors(['role' => __('flash.user_no_team_access')]);
        }

        $project->members()->syncWithoutDetaching([
            $user->id => ['role' => $request->validated('role')],
        ]);

        return back()->with('status', __('flash.role_updated'));
    }

    /**
     * Remove the explicit role row, reverting the user to the default WORKER role.
     */
    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        if ($project->isOwner($user)) {
            return back()->withErrors(['role' => __('flash.owner_role_cannot_reset')]);
        }

        $project->members()->detach($user->id);

        return back()->with('status', __('flash.role_reset_to_worker'));
    }
}
