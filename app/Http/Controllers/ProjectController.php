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

    public function create(): InertiaResponse
    {
        $this->authorize('create', Project::class);

        return Inertia::render('ProjectCreate', [
            'storeUrl' => route('projects.store'),
            'cancelUrl' => route('projects.index'),
            'strings' => [
                'title' => __('projects.new_project'),
                'keyUnique' => __('projects.key_unique'),
                'keyHint' => __('projects.e_g_demo_letters_numbers_and'),
                'name' => __('common.name'),
                'description' => __('common.description'),
                'githubRepo' => __('projects.github_repository'),
                'githubHintPre' => __('projects.optional_format'),
                'githubHintPost' => __('projects.for_pr_linking_and_pr_status_sync'),
                'cancel' => __('common.cancel'),
                'create' => __('common.create'),
            ],
        ]);
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
    public function access(Project $project): InertiaResponse
    {
        $this->authorize('view', $project);

        $project->load(['owner', 'teams.members', 'memberships']);

        $accessUsers = $project->accessUsers();
        $roleByUser = $project->memberships->keyBy('user_id');

        $assignedTeamIds = $project->teams->pluck('id');
        $assignableTeams = Auth::user()->teams()
            ->whereNotIn('teams.id', $assignedTeamIds)
            ->orderBy('name')
            ->get();

        return Inertia::render('ProjectAccess', [
            'project' => ['alias' => $project->alias, 'name' => $project->name],
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'editTabs' => \App\Support\ProjectEditTabs::for($project, 'access'),
            'canManage' => Auth::user()->can('manageMembers', $project),
            'teams' => $project->teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'memberCount' => $t->members->count(),
            ])->values(),
            'assignableTeams' => $assignableTeams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'users' => $accessUsers->map(function ($user) use ($project, $roleByUser) {
                $isOwner = $project->isOwner($user);
                $role = $isOwner
                    ? ProjectRole::ADMIN
                    : ($roleByUser->get($user->id)?->role ?? ProjectRole::WORKER);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isOwner' => $isOwner,
                    'role' => $role->value,
                    'roleLabel' => $role->label(),
                    'hasMembership' => $roleByUser->has($user->id),
                ];
            })->values(),
            'roles' => collect(ProjectRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            'urls' => [
                'teamStore' => route('projects.teams.store', $project),
                'teamDestroy' => route('projects.teams.destroy', [$project, '__ID__']),
                'memberUpdate' => route('projects.members.update', [$project, '__ID__']),
                'memberDestroy' => route('projects.members.destroy', [$project, '__ID__']),
            ],
            'strings' => [
                'editTitle' => __('projects.edit_project'),
                'accessTitle' => __('common.access'),
                'showHideExplanation' => __('common.show_hide_explanation'),
                'assignedTeams' => __('projects.assigned_teams'),
                'rolesHeading' => __('projects.roles'),
                'helpTeams' => [
                    ['text' => __('projects.access_to_the_project_is_managed_via')],
                    ['strong' => __('projects.assign'), 'text' => __('projects.adds_one_of_your_teams_its_members_gain')],
                    ['strong' => __('common.remove'), 'text' => __('projects.revokes_the_assignment_the_members_lose')],
                    ['text' => __('projects.without_an_assigned_team_only_the')],
                ],
                'helpRoles' => [
                    ['text' => __('projects.the_role_determines_what_someone_may_do')],
                    ['strong' => __('projects.contributor'), 'text' => __('projects.default_role_view_claim_and_work_on')],
                    ['strong' => __('projects.architect'), 'text' => __('projects.for_technical_planning_slicing_tasks')],
                    ['strong' => __('projects.administrator'), 'text' => __('projects.may_additionally_manage_the_project')],
                ],
                'countMembers' => __('common.count_members'),
                'remove' => __('common.remove'),
                'removeTeamConfirm' => __('projects.remove_team_assignment'),
                'assignTeam' => __('projects.assign_team'),
                'assign' => __('projects.assign'),
                'noTeamAssigned' => __('projects.no_team_assigned_yet_without_a_team_no'),
                'noFurtherTeams' => __('projects.no_further_own_teams_to_assign_create'),
                'user' => __('projects.user'),
                'role' => __('projects.role'),
                'projectOwner' => __('projects.project_owner'),
                'save' => __('common.save'),
                'reset' => __('projects.reset'),
                'resetToWorker' => __('projects.reset_to_worker'),
                'accessViaTeams' => __('projects.access_comes_via_the_assigned_teams'),
            ],
        ]);
    }

    public function edit(Project $project): InertiaResponse
    {
        $this->authorize('update', $project);

        return Inertia::render('ProjectEdit', [
            'project' => [
                'alias' => $project->alias,
                'name' => $project->name,
                'description' => $project->description,
                'github_repo' => $project->github_repo,
                'completed' => $project->completed_at !== null,
                'archived' => $project->archived_at !== null,
                'showUrl' => route('projects.show', $project),
            ],
            'editTabs' => \App\Support\ProjectEditTabs::for($project, 'general'),
            'canDelete' => Auth::user()->can('delete', $project),
            'updateUrl' => route('projects.update', $project),
            'destroyUrl' => route('projects.destroy', $project),
            'strings' => [
                'title' => __('projects.edit_project'),
                'keyUnique' => __('projects.key_unique'),
                'name' => __('common.name'),
                'description' => __('common.description'),
                'githubRepo' => __('projects.github_repository'),
                'githubHintPre' => __('projects.format'),
                'githubHintPost' => __('projects.for_pr_linking_and_the_sync_prs_button'),
                'completedLabel' => __('projects.project_completed'),
                'completedHint' => __('projects.shows_the_completed_badge_in_the'),
                'archivedLabel' => __('projects.archive_project'),
                'archivedHint' => __('projects.hides_the_project_from_the_project_list'),
                'cancel' => __('common.cancel'),
                'save' => __('common.save'),
                'deleteTitle' => __('projects.delete_project'),
                'deleteHint' => __('projects.removes_the_project_including_all_tasks'),
                'deleteConfirm' => __('projects.really_delete_this_project'),
                'delete' => __('common.delete'),
            ],
        ]);
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
