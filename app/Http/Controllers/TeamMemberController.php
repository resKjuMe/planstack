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
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        // Teams sind organisationsgebunden: nur Mitglieder derselben Organisation
        // dürfen hinzugefügt werden.
        if ($user->organization_id !== $team->organization_id) {
            return back()->withErrors(['email' => 'Dieser User gehört nicht zu deiner Organisation.']);
        }

        if ($team->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'Dieser User ist bereits im Team.']);
        }

        $team->members()->attach($user->id);

        return back()->with('status', "{$user->name} wurde zum Team hinzugefügt.");
    }

    public function destroy(Team $team, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        if ($team->isOwner($user)) {
            return back()->withErrors(['member' => 'Der Creator kann nicht entfernt werden.']);
        }

        $team->members()->detach($user->id);

        return back()->with('status', 'Mitglied entfernt.');
    }
}
