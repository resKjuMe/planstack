<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Support\SkillTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $projects = Project::query()
            ->where('created_by_id', $userId)
            ->orWhereHas('memberships', fn ($q) => $q->where('user_id', $userId))
            ->withCount('tasks')
            ->with('owner')
            ->latest()
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create', ['skillDefault' => SkillTemplate::default()]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Seed the skill text with the default template (placeholders intact) when
        // the form left it empty, so every project ships a working skill.
        if (blank($data['skill_description'] ?? null)) {
            $data['skill_description'] = SkillTemplate::default();
        }

        $project = Project::create([
            ...$data,
            'created_by_id' => $request->user()->id,
        ]);

        // The owner is automatically an ADMIN member.
        $project->members()->attach($request->user()->id, ['role' => ProjectRole::ADMIN->value]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Projekt \"{$project->alias}\" wurde angelegt.");
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load([
            'owner',
            'teams.members',
            'memberships',
            'phases',
            'tasks' => fn ($q) => $q->with(['claimer', 'concern'])->orderBy('name'),
        ]);

        // Users with access (owner + members of assigned teams) and their role.
        $accessUsers = $project->accessUsers();
        $roleByUser = $project->memberships->keyBy('user_id');

        // Teams the current user can still assign (their teams, not yet assigned).
        $assignedTeamIds = $project->teams->pluck('id');
        $assignableTeams = Auth::user()->teams()
            ->whereNotIn('teams.id', $assignedTeamIds)
            ->orderBy('name')
            ->get();

        return view('projects.show', compact('project', 'accessUsers', 'roleByUser', 'assignableTeams'));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Projekt aktualisiert.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', 'Projekt gelöscht.');
    }
}
