<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectRole;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Support\TaskBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class ProjectController extends ApiController
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * GET /api/projects — projects the token user can access.
     */
    public function index(Request $request): JsonResource
    {
        $userId = $request->user()->id;

        $projects = Project::query()
            ->where(fn ($q) => $q
                ->where('created_by_id', $userId)
                ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $userId)))
            ->withCount('tasks')
            ->with('owner')
            ->latest()
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * POST /api/projects — create a project owned by the token user.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validate([
            'alias' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('projects', 'alias')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            ...$data,
            'created_by_id' => $request->user()->id,
        ]);

        // The owner is automatically an ADMIN member (role distribution).
        $project->members()->attach($request->user()->id, ['role' => ProjectRole::ADMIN->value]);

        return (new ProjectResource($project->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/projects/{project} — the full board (phases + decorated tasks).
     */
    public function show(Project $project): JsonResource
    {
        $this->authorize('view', $project);

        $project->load('owner', 'phases');
        $tasks = $this->board->board($project);
        $tasks->each->load('phase', 'claimer', 'concern');
        $project->setRelation('tasks', $tasks);

        return new ProjectResource($project);
    }

    /**
     * PATCH /api/projects/{project} — update name/description (not the alias).
     */
    public function update(Request $request, Project $project): JsonResource
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $project->update($data);

        return new ProjectResource($project->load('owner'));
    }

    /**
     * GET /api/projects/{project}/board — the read model board clients pick from:
     * pickable list (sorted by unlocks), per-phase aggregates, and status/gate
     * info. Congruent with the Summary web view.
     */
    public function board(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load('phases');
        $tasks = $this->board->board($project);

        $pickable = $tasks
            ->filter(fn ($t) => $t->x_pickable)
            ->sortByDesc('x_unlocks')
            ->values();

        $phases = $project->phases->map(function ($phase) use ($tasks) {
            $pt = $tasks->where('phase_id', $phase->id);
            $sp = max(1, (int) $pt->sum('effort_story_points'));
            $doneSp = (int) $pt->filter(fn ($t) => $this->board->isDelivered($t))->sum('effort_story_points');

            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'position' => $phase->position,
                'tasks' => $pt->count(),
                'story_points' => (int) $pt->sum('effort_story_points'),
                'done_story_points' => $doneSp,
                'pct' => (int) round($doneSp / $sp * 100),
            ];
        })->values();

        $totalSp = max(1, (int) $tasks->sum('effort_story_points'));
        $doneSp = (int) $tasks->filter(fn ($t) => $this->board->isDelivered($t))->sum('effort_story_points');

        return response()->json([
            'project' => [
                'id' => $project->id,
                'alias' => $project->alias,
                'name' => $project->name,
            ],
            'totals' => [
                'tasks' => $tasks->count(),
                'done' => $tasks->filter(fn ($t) => $this->board->isDelivered($t))->count(),
                'story_points' => (int) $tasks->sum('effort_story_points'),
                'done_story_points' => $doneSp,
                'pct' => (int) round($doneSp / $totalSp * 100),
                'pickable' => $pickable->count(),
            ],
            'phases' => $phases,
            'pickable' => TaskResource::collection($pickable),
        ]);
    }
}
