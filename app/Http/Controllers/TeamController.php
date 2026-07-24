<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TeamController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('TeamsIndex', [
            'createUrl' => route('teams.create'),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            // Teamliste asynchron nachladen (Skeleton währenddessen).
            'teams' => Inertia::defer(function () {
                $userId = Auth::id();
                $user = Auth::user();
                $isOrgOwner = $user->organization?->isOwner($user) === true;

                return Team::query()
                    ->where('organization_id', $user->organization_id)
                    ->when(! $isOrgOwner, fn ($q) => $q->where(fn ($inner) => $inner
                        ->where('created_by_id', $userId)
                        ->orWhereHas('members', fn ($m) => $m->where('users.id', $userId))))
                    ->withCount('members')
                    ->with('owner')
                    ->latest()
                    ->get()
                    ->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'membersCount' => $team->members_count,
                        'ownerName' => $team->owner?->name,
                        'showUrl' => route('teams.show', $team),
                    ])->values();
            }),
            'strings' => [
                'teams' => __('common.teams'),
                'newTeam' => __('teams.new_team'),
                'noTeams' => __('teams.no_teams_yet_create_a_team_and_add'),
                'creator' => __('teams.creator_2'),
                'countMembersTpl' => __('common.count_members', ['count' => '__COUNT__']),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', Team::class);

        return Inertia::render('TeamCreate', [
            'storeUrl' => route('teams.store'),
            'cancelUrl' => route('teams.index'),
            'strings' => [
                'newTeam' => __('teams.new_team'),
                'teamName' => __('teams.team_name'),
                'cancel' => __('common.cancel'),
                'create' => __('common.create'),
            ],
        ]);
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
            ->with('status', __('flash.team_created', ['name' => $team->name]));
    }

    public function show(Team $team): InertiaResponse
    {
        $this->authorize('view', $team);

        return Inertia::render('TeamShow', [
            'team' => ['id' => $team->id, 'name' => $team->name],
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'urls' => [
                'update' => route('teams.update', $team),
                'destroy' => route('teams.destroy', $team),
                'memberStore' => route('teams.members.store', $team),
                'memberDestroy' => route('teams.members.destroy', [$team, '__ID__']),
            ],
            // Mitglieder/Rechte/Auswahl asynchron nachladen (Skeleton währenddessen).
            'teamData' => Inertia::defer(function () use ($team) {
                $team->load(['owner', 'members']);
                $user = Auth::user();

                // User der Organisation, die noch nicht Mitglied dieses Teams sind —
                // Auswahlliste zum Hinzufügen.
                $assignableUsers = User::query()
                    ->where('organization_id', $team->organization_id)
                    ->whereNotIn('id', $team->members->pluck('id'))
                    ->orderBy('name')
                    ->get();

                return [
                    'canUpdate' => $user->can('update', $team),
                    'canManageMembers' => $user->can('manageMembers', $team),
                    'canDelete' => $user->can('delete', $team),
                    'members' => $team->members->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'email' => $m->email,
                        'isOwner' => $team->isOwner($m),
                    ])->values(),
                    'assignableUsers' => $assignableUsers->map(fn ($u) => [
                        'id' => $u->id,
                        'label' => "{$u->name} ({$u->email})",
                    ])->values(),
                ];
            }),
            'strings' => [
                'team' => __('teams.team'),
                'renameTeam' => __('teams.rename_team'),
                'teamName' => __('teams.team_name_2'),
                'members' => __('common.members'),
                'name' => __('common.name'),
                'email' => __('common.email'),
                'creatorBadge' => __('teams.creator'),
                'remove' => __('common.remove'),
                'removeMemberConfirm' => __('teams.remove_member'),
                'addMember' => __('teams.add_member'),
                'add' => __('teams.add'),
                'chooseHint' => __('teams.choose_from_the_members_of_your'),
                'allMembersHint' => __('teams.all_members_of_your_organization_are'),
                'deleteTeam' => __('teams.delete_team'),
                'deleteConfirm' => __('teams.really_delete_this_team'),
                'delete' => __('common.delete'),
                'save' => __('common.save'),
            ],
        ]);
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
            ->with('status', __('flash.team_renamed', ['name' => $team->name]));
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', __('flash.team_deleted'));
    }
}
