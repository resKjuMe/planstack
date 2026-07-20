<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Support\TaskBoardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private readonly TaskBoardService $board) {}

    public function index(): View
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
            ->withCount(['tasks as closed_tasks_count' => fn (Builder $q) => $q->whereIn(
                'status', [TaskStatus::COMPLETED, TaskStatus::MERGED]
            )])
            ->withSum('tasks as total_sp', 'effort_story_points')
            ->withSum(['tasks as done_sp' => fn (Builder $q) => $q->whereIn(
                'status', [TaskStatus::COMPLETED, TaskStatus::MERGED]
            )], 'effort_story_points')
            ->with(['owner', 'teams:id,name'])
            ->latest()
            ->get();

        // Pro Projekt die Status-Segmente für den gestapelten Fortschrittsbalken
        // (gleiche Logik/Farben wie die Phasen-Balken der Summary).
        foreach ($projects as $project) {
            $project->x_status_segments = $this->statusSegments($project);
        }

        // Kopfzeilen-Statistiken beziehen sich auf die aktiven (nicht archivierten)
        // Projekte — archivierte sind standardmäßig ausgeblendet und zählen erst
        // über die Filter-Pill „Archiviert" wieder mit.
        $activeProjects = $projects->whereNull('archived_at');
        $activeCount = $activeProjects->count();
        $openTasks = $activeProjects->sum(fn (Project $p) => $p->tasks_count - $p->closed_tasks_count);
        $totalSp = (int) $activeProjects->sum('total_sp');

        return view('projects.index', compact('projects', 'userId', 'activeCount', 'openTasks', 'totalSp'));
    }

    /**
     * Nach SP gewichtete Balken-Segmente je Anzeige-Status (merged → offen),
     * identisch zur Summary. Tasks ohne Story Points erscheinen nicht im Balken.
     *
     * @return array<int, array<string, mixed>>
     */
    private function statusSegments(Project $project): array
    {
        $tasks = $this->board->decorate($project);
        $sp = max(1, (int) $tasks->sum('effort_story_points'));

        $segments = [];
        foreach (TaskStatus::displayOrder() as $status) {
            $inStatus = $tasks->filter(fn ($t) => $t->x_display_status === $status);
            $segSp = (int) $inStatus->sum('effort_story_points');
            if ($inStatus->isEmpty() || $segSp <= 0) {
                continue;
            }
            $segments[] = [
                'label' => $status->label(),
                'count' => $inStatus->count(),
                'bar' => $status->barClasses(),
                'text' => $status->textClasses(),
                'width' => round($segSp / $sp * 100, 1),
            ];
        }

        return $segments;
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

    public function show(Project $project, \App\Support\BoardPresenter $presenter): View
    {
        $this->authorize('view', $project);

        $project->load(['owner', 'phases']);

        // The board itself is a React app hydrated from this payload (tasks +
        // workflow config + view context). TaskBoardService/BoardPresenter build
        // the same shape the drag-and-drop move endpoint returns.
        return view('projects.show', [
            'project' => $project,
            'boardData' => $presenter->payload($project),
        ]);
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
