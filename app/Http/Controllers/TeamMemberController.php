<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TeamMemberController extends Controller
{
    public function store(StoreTeamMemberRequest $request, Team $team): RedirectResponse
    {
        $user = User::findOrFail($request->validated('user_id'));

        // Teams sind organisationsgebunden: nur Mitglieder derselben Organisation
        // dürfen hinzugefügt werden.
        if ($user->organization_id !== $team->organization_id) {
            return back()->withErrors(['user_id' => __('flash.user_not_in_organization')]);
        }

        if ($team->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['user_id' => __('flash.user_already_in_team')]);
        }

        $team->members()->attach($user->id);

        return back()->with('status', __('flash.user_added_to_team', ['name' => $user->name]));
    }

    public function destroy(Team $team, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        if ($team->isOwner($user)) {
            return back()->withErrors(['member' => __('flash.creator_cannot_be_removed')]);
        }

        $team->members()->detach($user->id);

        return back()->with('status', __('flash.member_removed'));
    }
}
