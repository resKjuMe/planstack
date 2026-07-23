<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\StatusRole;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Support\ProjectWorkspacePresenter;
use App\Support\StatusSegments;
use App\Support\TaskBoardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    public function __construct(
        private readonly TaskBoardService $board,
        private readonly StatusSegments $segments,
    ) {}

    public function index(): InertiaResponse
    {
        $userId = Auth::id();
        $user = Auth::user();
        // Der Organisationsgründer sieht alle Projekte seiner Organisation;
        // alle anderen nur ihre eigenen bzw. per Team zugänglichen.
        $isOrgOwner = $user->organization?->isOwner($user) === true;

        // done_sp zählt bewusst nur erledigte/gemergte Tasks (COMPLETED/MERGED) —
        // ein offener PR allein gilt in der Projektübersicht nicht als Fortschritt.
        // Damit ist die SP-Definition deckungsgleich mit closed_tasks_count, das
        // in der Kopfzeile die offenen Tasks bestimmt.
        // Zugriff kommt über Teamzuweisung (Project::hasMember()); memberships
        // (users_to_projects) trägt nur noch die Rolle, nicht mehr den Zugriff —
        // reine WORKER ohne expliziten Rollen-Datensatz müssen daher über
        // teams.members geprüft werden, sonst sehen sie ihre Projekte hier nicht.
        $projects = Project::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $isOrgOwner, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('created_by_id', $userId)
                ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $userId))))
            ->withCount('tasks')
            ->withCount(['tasks as closed_tasks_count' => fn (Builder $q) => $q->whereHas(
                'orgStatus', fn (Builder $s) => $s->whereIn('role', [StatusRole::COMPLETED->value, StatusRole::MERGED->value])
            )])
            ->withSum('tasks as total_sp', 'effort_story_points')
            ->withSum(['tasks as done_sp' => fn (Builder $q) => $q->whereHas(
                'orgStatus', fn (Builder $s) => $s->whereIn('role', [StatusRole::COMPLETED->value, StatusRole::MERGED->value])
            )], 'effort_story_points')
            ->with(['owner', 'teams:id,name'])
            ->latest()
            ->get();

        // Kopfzeilen-Statistiken beziehen sich auf die aktiven (nicht archivierten)
        // Projekte — archivierte sind standardmäßig ausgeblendet und zählen erst
        // über die Filter-Pill „Archiviert" wieder mit.
        $activeProjects = $projects->whereNull('archived_at');
        $activeCount = $activeProjects->count();
        $openTasks = (int) $activeProjects->sum(fn (Project $p) => $p->tasks_count - $p->closed_tasks_count);
        $totalSp = (int) $activeProjects->sum('total_sp');

        $summaryLine = ($activeCount === 1 ? '1 '.__('projects.project') : $activeCount.' '.__('common.projects'))
            .' · '.__('projects.count_open_tasks', ['count' => number_format($openTasks, 0, ',', '.')])
            .' · '.__('projects.count_story_points', ['count' => number_format($totalSp, 0, ',', '.')]);

        return Inertia::render('ProjectsIndex', [
            'projects' => $projects->map(fn (Project $p) => $this->card($p, $userId))->all(),
            'summaryLine' => $summaryLine,
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
            ],
        ]);
    }

    /**
     * Eine Projektkarte für die React-Projektliste (ProjectsIndex) — Fortschritt,
     * Status-Segmente (wie Summary), Kategorie/Badge und Besitzer/Teams.
     *
     * @return array<string, mixed>
     */
    private function card(Project $project, ?int $userId): array
    {
        $sp = (int) $project->total_sp;
        $pct = $sp > 0 ? (int) $project->done_sp / $sp * 100 : 0;
        $isCompleted = $project->completed_at !== null;
        // Abgeschlossene Projekte tragen die Kategorie „completed" und überschreiben
        // die berechnete Kategorie.
        $category = $isCompleted
            ? 'completed'
            : ($pct <= 0 ? 'nicht_gestartet' : ($pct >= 80 ? 'fast_fertig' : 'in_arbeit'));

        $categoryLabel = [
            'nicht_gestartet' => __('projects.not_started'),
            'in_arbeit' => __('projects.in_progress'),
            'fast_fertig' => __('projects.almost_done'),
            'completed' => __('projects.completed'),
        ][$category];
        $badgeClass = [
            'nicht_gestartet' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
            'in_arbeit' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
            'fast_fertig' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
            'completed' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        ][$category];
        $barClass = [
            'nicht_gestartet' => 'bg-gray-300',
            'in_arbeit' => 'bg-indigo-600',
            'fast_fertig' => 'bg-green-500',
            'completed' => 'bg-blue-500',
        ][$category];

        $initials = collect(preg_split('/\s+/', trim($project->owner?->name ?? '?')))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $avatarPalette = ['bg-emerald-600', 'bg-indigo-600', 'bg-rose-600', 'bg-amber-600', 'bg-sky-600', 'bg-fuchsia-600'];

        $segments = collect($this->statusSegments($project))
            ->map(fn (array $s) => array_merge($s, ['pctLabel' => number_format((float) $s['width'], 1, ',', '')]))
            ->all();

        return [
            'alias' => $project->alias,
            'name' => $project->name,
            'descriptionHtml' => filled($project->description) ? Str::markdown($project->description, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'renderer' => ['soft_break' => "<br>\n"],
            ]) : null,
            'diagramUrl' => route('projects.diagram', $project),
            'category' => $category,
            'categoryLabel' => $categoryLabel,
            'badgeClass' => $badgeClass,
            'barClass' => $barClass,
            'pct' => round($pct, 1),
            'pctLabel' => number_format($pct, 1, ',', ''),
            'segments' => $segments,
            'ownerName' => $project->owner?->name,
            'initials' => $initials,
            'avatarClass' => $avatarPalette[($project->created_by_id ?? 0) % count($avatarPalette)],
            'teams' => $project->teams->pluck('name')->all(),
            'tasksCount' => $project->tasks_count,
            'tasksLabel' => __('projects.count_tasks', ['count' => $project->tasks_count]),
            'sp' => $sp,
            'mine' => $project->created_by_id === $userId,
            'archived' => $project->archived_at !== null,
            'searchText' => Str::lower($project->alias.' '.$project->name),
        ];
    }

    /**
     * Balken-Segmente je Anzeige-Status, identisch zur Summary — aus den je
     * Organisation konfigurierbaren Status (inkl. Custom-Status). Gewichtung nach
     * Story Points, ersatzweise nach Task-Anzahl (siehe StatusSegments), damit
     * jeder vorhandene Status einen Balkenabschnitt und ein Badge erhält.
     *
     * @return array<int, array<string, mixed>>
     */
    private function statusSegments(Project $project): array
    {
        return $this->segments->segments($project, $this->board->decorate($project));
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
