<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends ApiController
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * GET /api/projects/{project}/tasks — all tasks with computed board fields.
     */
    public function index(Project $project): JsonResource
    {
        $this->authorize('view', $project);

        $tasks = $this->board->board($project);
        $tasks->each->load('phase', 'claimer', 'concern');

        return TaskResource::collection($tasks);
    }

    /**
     * GET /api/projects/{project}/tasks/{task} — one task, decorated.
     */
    public function show(Project $project, Task $task): JsonResource
    {
        $this->authorize('view', $task);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST /api/projects/{project}/tasks — create a task with an optional gate.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('contribute', $project);

        $data = $this->validateTask($request, $project);
        $gate = $this->resolveGate($project, $request->input('gate', []));

        $task = $project->tasks()->create([
            ...$data,
            'created_by_id' => $request->user()->id,
            'status' => $data['status'] ?? TaskStatus::UNKNOWN->value,
        ]);

        $task->prerequisites()->sync($gate);

        return (new TaskResource($this->decorateOne($project, $task)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT|PATCH /api/projects/{project}/tasks/{task} — update the task's writable
     * fields. Same field set as store(); `status` accepts any lifecycle value
     * (MERGED stamps merged_at on first transition, mirroring the web update).
     * Prerequisites are only replaced when a `gate` key is present — omit it to
     * leave the existing gate untouched (use .../gate to change it in isolation).
     */
    public function update(Request $request, Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $data = $this->validateTask($request, $project);

        if (($data['status'] ?? null) === TaskStatus::MERGED->value && $task->merged_at === null) {
            $data['merged_at'] = now();
        }

        $task->update($data);

        if ($request->has('gate')) {
            $task->prerequisites()->sync(
                $this->resolveGate($project, $request->input('gate', []), excludeTaskId: $task->id)
            );
        }

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * DELETE /api/projects/{project}/tasks/{task}.
     */
    public function destroy(Project $project, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(status: 204);
    }

    /**
     * POST .../claim — claim an unclaimed task for the token user.
     */
    public function claim(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('claim', $task);

        if ($task->claimed_by_id !== null) {
            return response()->json([
                'message' => $task->claimed_by_id === $request->user()->id
                    ? 'Du hast diesen Task bereits beansprucht.'
                    : 'Task ist bereits beansprucht.',
            ], 409);
        }

        $task->update([
            'claimed_by_id' => $request->user()->id,
            'claimed_at' => now(),
            'status' => TaskStatus::CLAIMED->value,
        ]);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../release — release a task the token user holds.
     */
    public function release(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('claim', $task);

        if ($task->claimed_by_id === null) {
            return response()->json(['message' => 'Task ist nicht beansprucht.'], 409);
        }

        $task->update([
            'claimed_by_id' => null,
            'claimed_at' => null,
            'status' => TaskStatus::PICKABLE->value,
        ]);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../status — analyze | in_progress | in_review | done.
     *
     * Once the work is finished and a PR exists the task moves to IN_REVIEW
     * (open PR awaiting merge); it never reaches COMPLETED via a PR — only via a
     * split (parent) — and MERGED only via /merge. "done" is the "work finished"
     * signal: it lands on IN_REVIEW when a PR is set, otherwise it stays
     * IN_PROGRESS (nothing to review yet). "in_review" sets that state directly.
     */
    public function status(Request $request, Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(['analyze', 'in_progress', 'in_review', 'done'])],
        ]);

        $task->update(['status' => match ($data['status']) {
            'analyze' => TaskStatus::ANALYZING,
            'in_review' => TaskStatus::IN_REVIEW,
            'done' => $task->pr_number !== null ? TaskStatus::IN_REVIEW : TaskStatus::IN_PROGRESS,
            'in_progress' => TaskStatus::IN_PROGRESS,
        }]);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../pr — set the PR number (digits only).
     */
    public function pr(Request $request, Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'pr_number' => ['required', 'integer', 'min:1'],
        ]);

        $task->update(['pr_number' => $data['pr_number']]);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../merge — mark MERGED; merged_at only on the first transition.
     */
    public function merge(Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $update = ['status' => TaskStatus::MERGED->value];
        if ($task->merged_at === null) {
            $update['merged_at'] = now();
        }
        $task->update($update);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../gate — replace the task's prerequisites.
     */
    public function gate(Request $request, Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $gate = $this->resolveGate($project, $request->input('gate', []), excludeTaskId: $task->id);
        $task->prerequisites()->sync($gate);

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../concern — set/update the concern and flip to CONCERNED.
     */
    public function concern(Request $request, Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string'],
            'blocker' => ['nullable', 'string'],
            'misconception' => ['nullable', 'string'],
            'decisions' => ['nullable', 'string'],
        ]);

        $task->concern()->updateOrCreate(
            ['task_id' => $task->id],
            [
                'created_by_id' => $task->concern?->created_by_id ?? $request->user()->id,
                'summary' => $data['summary'],
                'description_context' => $data['context'] ?? null,
                'description_blocker' => $data['blocker'] ?? null,
                'description_misconception' => $data['misconception'] ?? null,
                'description_decisions' => $data['decisions'] ?? null,
            ],
        );

        $task->update(['status' => TaskStatus::CONCERNED->value]);

        return new TaskResource($this->decorateOne($project, $task->fresh()));
    }

    /**
     * DELETE .../concern — resolve the concern; task returns to pickable/unknown.
     */
    public function resolveConcern(Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $task->concern()->delete();

        if ($task->status === TaskStatus::CONCERNED) {
            $task->update(['status' => $task->claimed_by_id ? TaskStatus::CLAIMED->value : TaskStatus::PICKABLE->value]);
        }

        return new TaskResource($this->decorateOne($project, $task->fresh()));
    }

    /**
     * POST .../split — mark the parent COMPLETED and create child tasks
     * (same phase, own gates and efforts). Atomic.
     */
    public function split(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'children' => ['required', 'array', 'min:1'],
            'children.*.name' => ['required', 'string', 'max:50'],
            'children.*.summary' => ['required', 'string', 'max:255'],
            'children.*.effort_story_points' => ['nullable', 'integer', 'min:0'],
            'children.*.effort_man_days' => ['nullable', 'integer', 'min:0'],
            'children.*.effort_tokens' => ['nullable', 'integer', 'min:0'],
            'children.*.affected_files' => ['nullable', 'integer', 'min:0'],
            'children.*.gate' => ['nullable', 'array'],
        ]);

        $created = DB::transaction(function () use ($project, $task, $validated, $request) {
            $task->update(['status' => TaskStatus::COMPLETED->value]);

            return collect($validated['children'])->map(function ($child) use ($project, $task, $request) {
                $new = $project->tasks()->create([
                    'created_by_id' => $request->user()->id,
                    'name' => $child['name'],
                    'summary' => $child['summary'],
                    'phase_id' => $task->phase_id,
                    'effort_story_points' => $child['effort_story_points'] ?? 0,
                    'effort_man_days' => $child['effort_man_days'] ?? 0,
                    'effort_tokens' => $child['effort_tokens'] ?? 0,
                    'affected_files' => $child['affected_files'] ?? 0,
                    'status' => TaskStatus::UNKNOWN->value,
                ]);
                $new->prerequisites()->sync($this->resolveGate($project, $child['gate'] ?? [], excludeTaskId: $new->id));

                return $new;
            });
        });

        $decorated = $this->board->board($project)->whereIn('id', $created->pluck('id'))->values();

        return TaskResource::collection($decorated)->response()->setStatusCode(201);
    }

    /**
     * Validate the writable task fields (shared by store).
     *
     * @return array<string, mixed>
     */
    private function validateTask(Request $request, Project $project): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // The public API exposes this as `acceptance_criteria` (see
            // TaskResource); accept that name (and the legacy column name) on
            // input and map it to the stored column below.
            'acceptance_criteria' => ['nullable', 'string'],
            'description_acceptance_criteria' => ['nullable', 'string'],
            'phase_id' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $project->id)],
            'effort_man_days' => ['nullable', 'integer', 'min:0'],
            'effort_story_points' => ['nullable', 'integer', 'min:0'],
            'effort_tokens' => ['nullable', 'integer', 'min:0'],
            'affected_files' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
        ]);

        // Fold the public field name onto the underlying column (the short name
        // wins if both are present) so create() actually persists it.
        if (array_key_exists('acceptance_criteria', $data)) {
            $data['description_acceptance_criteria'] = $data['acceptance_criteria'];
            unset($data['acceptance_criteria']);
        }

        return $data;
    }

    /**
     * Resolve a gate list (task ids or names) to project-scoped task ids.
     * Throws a 422 for unknown references or a self-gate.
     *
     * @param  array<int, int|string>  $gate
     * @return array<int, int>
     */
    private function resolveGate(Project $project, array $gate, ?int $excludeTaskId = null): array
    {
        $ids = [];

        foreach ($gate as $ref) {
            $query = $project->tasks();
            $match = is_numeric($ref)
                ? $query->whereKey((int) $ref)->first()
                : $query->where('name', $ref)->first();

            if (! $match) {
                throw ValidationException::withMessages([
                    'gate' => "Unbekannte Gate-Referenz: \"{$ref}\" (nicht in diesem Projekt).",
                ]);
            }

            if ($excludeTaskId !== null && $match->id === $excludeTaskId) {
                throw ValidationException::withMessages([
                    'gate' => 'Ein Task kann sich nicht selbst als Gate haben.',
                ]);
            }

            $ids[] = $match->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Load one task with its relations and attach board attributes.
     */
    private function decorateOne(Project $project, Task $task): Task
    {
        $tasks = $this->board->board($project);
        $decorated = $tasks->firstWhere('id', $task->id) ?? $task;
        $decorated->load('phase', 'claimer', 'concern');

        return $decorated;
    }
}
