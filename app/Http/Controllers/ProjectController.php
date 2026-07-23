<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    public function index(): InertiaResponse
    {
        // Die Projektliste lädt Projekte über GET /api/projects und die restlichen
        // Infos (Tasks je Projekt → Zähler/SP/Segmente) über GET /api/tasks; die
        // Karten werden clientseitig abgeleitet und per entity-changed live
        // aktualisiert. Diese Seite liefert nur statische Props + i18n-Templates.
        return Inertia::render('ProjectsIndex', [
            'currentUserId' => Auth::id(),
            'filters' => [
                ['key' => 'all', 'label' => __('common.all')],
                ['key' => 'mine', 'label' => __('projects.my_projects')],
                ['key' => 'in_arbeit', 'label' => __('projects.in_progress')],
                ['key' => 'fast_fertig', 'label' => __('projects.almost_done')],
                ['key' => 'completed', 'label' => __('projects.completed')],
                ['key' => 'archived', 'label' => __('projects.archived')],
            ],
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'strings' => [
                'title' => __('common.projects'),
                'newProject' => __('projects.new_project'),
                'createUrl' => route('projects.create'),
                'searchProjects' => __('projects.search_projects'),
                'noProjects' => __('projects.no_projects_yet_create_your_first'),
                'progress' => __('common.progress'),
                'tasks' => __('common.tasks'),
                'loading' => __('status.loading'),
                // Kategorie-Labels (Karten-Badge)
                'notStarted' => __('projects.not_started'),
                'inProgress' => __('projects.in_progress'),
                'almostDone' => __('projects.almost_done'),
                'completed' => __('projects.completed'),
                // Kopfzeile + Karte (Roh-Templates / Singular-Plural)
                'projectSingular' => __('projects.project'),
                'projectsPlural' => __('common.projects'),
                'countOpenTasks' => __('projects.count_open_tasks'),
                'countStoryPoints' => __('projects.count_story_points'),
                'countTasks' => __('projects.count_tasks'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // skill_description bleibt leer, sofern nichts Projektspezifisches angegeben
        // wird — der generische Skill (Bootstrap + serverseitiges Betriebshandbuch)
        // greift automatisch. Projektnotizen werden auf der „Claude"-Unterseite gepflegt.

        $project = new Project($data);
        $project->created_by_id = $request->user()->id;
        $project->organization_id = $request->user()->organization_id;
        $project->save();

        // The owner is automatically an ADMIN member.
        $project->members()->attach($request->user()->id, ['role' => ProjectRole::ADMIN->value]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', __('flash.project_created', ['alias' => $project->alias]));
    }

    public function show(Project $project, ProjectWorkspacePresenter $workspace): InertiaResponse
    {
        $this->authorize('view', $project);

        // Board und Summary sind EINE Inertia-Seite (ProjectWorkspace); der
        // Tab-Wechsel passiert clientseitig (0 Server-Calls). Diese Route rendert
        // sie mit aktivem Board-Tab. Die statischen Props beider Unterseiten
        // liefert der ProjectWorkspacePresenter gebündelt; die Task-/Phasen-Daten
        // lädt der geteilte React-Store einmalig über die API.
        return Inertia::render('ProjectWorkspace', $workspace->props($project, 'board'));
    }

    /**
     * Zugriffs-Verwaltung (zugewiesene Teams + Rollen) als eigener Projekt-Tab.
     */
    public function access(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['owner', 'teams.members', 'memberships']);

        // Users with access (owner + members of assigned teams) and their role.
        $accessUsers = $project->accessUsers();
        $roleByUser = $project->memberships->keyBy('user_id');

        // Teams the current user can still assign (their teams, not yet assigned).
        $assignedTeamIds = $project->teams->pluck('id');
        $assignableTeams = Auth::user()->teams()
            ->whereNotIn('teams.id', $assignedTeamIds)
            ->orderBy('name')
            ->get();

        return view('projects.access', compact('project', 'accessUsers', 'roleByUser', 'assignableTeams'));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        // Archiv-Status aus der Checkbox ableiten: bereits gesetztes archived_at
        // bleibt erhalten (Zeitpunkt der ersten Archivierung), sonst jetzt.
        $data['archived_at'] = $request->boolean('archived')
            ? ($project->archived_at ?? now())
            : null;

        // Analog zum Archiv-Status: bereits gesetztes completed_at bleibt erhalten.
        $data['completed_at'] = $request->boolean('completed')
            ? ($project->completed_at ?? now())
            : null;

        $project->update($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', __('flash.project_updated'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', __('flash.project_deleted'));
    }
}
