<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();
        $orgId = Auth::user()->organization_id;

        $teams = Team::query()
            ->where('organization_id', $orgId)
            ->where(fn ($q) => $q
                ->where('created_by_id', $userId)
                ->orWhereHas('members', fn ($m) => $m->where('users.id', $userId)))
            ->withCount('members')
            ->with('owner')
            ->latest()
            ->get();

        return view('teams.index', compact('teams'));
    }

    public function create(): View
    {
        $this->authorize('create', Team::class);

        return view('teams.create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = new Team($request->validated());
        $team->created_by_id = $request->user()->id;
        $team->organization_id = $request->user()->organization_id;
        $team->save();

        // The creator is automatically a member.
        $team->members()->attach($request->user()->id);

        return redirect()
            ->route('teams.show', $team)
            ->with('status', "Team \"{$team->name}\" wurde angelegt.");
    }

    public function show(Team $team): View
    {
        $this->authorize('view', $team);

        $team->load(['owner', 'members']);

        return view('teams.show', compact('team'));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $team->update($validated);

        return redirect()
            ->route('teams.show', $team)
            ->with('status', "Team umbenannt in \"{$team->name}\".");
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team gelöscht.');
    }
}
