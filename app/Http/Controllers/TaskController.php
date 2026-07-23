<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Support\BoardPresenter;
use App\Support\OrgBoardWorkflow;
use App\Support\StatusEffects;
use App\Support\TaskBoardService;
use App\Support\TaskFormPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskBoardService $board,
        private readonly BoardPresenter $presenter,
        private readonly \App\Support\TaskStatusService $statuses,
    ) {}

    public function create(Project $project, TaskFormPresenter $formPresenter): InertiaResponse
    {
        $this->authorize('contribute', $project);

        $project->load('phases');
        $candidates = $project->tasks()->orderBy('name')->get(['id', 'name', 'summary']);

        $props = $formPresenter->shared($project);
        $props['strings']['title'] = __('tasks.new_task');
        $props['strings']['submit'] = __('tasks.create_task');

        return Inertia::render('TaskCreate', array_merge($props, [
            'project' => ['alias' => $project->alias, 'showUrl' => route('projects.show', $project)],
            'candidates' => $candidates->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'summary' => $c->summary])->values(),
            'showReview' => false,
            'storeUrl' => route('projects.tasks.store', $project),
            'flash' => ['status' => session('status'), 'error' => session('error')],
        ]));
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $prerequisites = $data['prerequisites'] ?? [];
        unset($data['prerequisites']);

        $task = $project->tasks()->create([
            ...$data,
            'created_by_id' => $request->user()->id,
            'status' => $data['status'] ?? TaskStatus::UNKNOWN->value,
        ]);

        $task->prerequisites()->sync($prerequisites);

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', __('flash.task_created', ['name' => $task->name]));
    }

    public function show(Project $project, Task $task): View
    {
        $this->authorize('view', $task);

        $task->load([
            'creator', 'claimer', 'phase', 'reviewer', 'concern.creator',
            'prerequisites', 'dependents', 'checklistItems.checker',
        ]);

        return view('tasks.show', compact('project', 'task'));
    }

    public function edit(Project $project, Task $task, TaskFormPresenter $formPresenter): InertiaResponse
    {
        $this->authorize('update', $task);

        $project->load('phases');
        $candidates = $project->tasks()->whereKeyNot($task->id)->orderBy('name')->get(['id', 'name', 'summary']);
        $selected = $task->prerequisites()->pluck('tasks.id')->all();

        $props = $formPresenter->shared($project);
        $props['strings']['title'] = __('tasks.edit_task');
        $props['strings']['submit'] = __('common.save');
        $props['strings']['deleteTitle'] = __('tasks.delete_task');
        $props['strings']['deleteConfirm'] = __('tasks.really_delete_this_task');
        $props['strings']['delete'] = __('common.delete');

        return Inertia::render('TaskEdit', array_merge($props, [
            'project' => ['alias' => $project->alias],
            'candidates' => $candidates->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'summary' => $c->summary])->values(),
            'showReview' => $task->status === TaskStatus::IN_REVIEW,
            'canDelete' => auth()->user()->can('delete', $task),
            'updateUrl' => route('projects.tasks.update', [$project, $task]),
            'destroyUrl' => route('projects.tasks.destroy', [$project, $task]),
            'showUrl' => route('projects.tasks.show', [$project, $task]),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'task' => [
                'name' => $task->name,
                'values' => [
                    'name' => $task->name,
                    'status' => $task->status?->value ?? 'UNKNOWN',
                    'summary' => $task->summary ?? '',
                    'criticality' => $task->criticality?->value ?? '',
                    'description' => $task->description ?? '',
                    'description_acceptance_criteria' => $task->description_acceptance_criteria ?? '',
                    'description_target_actual' => $task->description_target_actual ?? '',
                    'description_test_cases' => $task->description_test_cases ?? '',
                    'phase_id' => $task->phase_id ?? '',
                    'effort_man_days' => $task->effort_man_days ?? '',
                    'effort_story_points' => $task->effort_story_points ?? '',
                    'effort_tokens' => $task->effort_tokens ?? '',
                    'affected_files' => $task->affected_files ?? '',
                    'pr_number' => $task->pr_number ?? '',
                    'reviewed_by' => $task->reviewed_by ?? '',
                    'last_review_recommendation' => $task->last_review_recommendation?->value ?? '',
                    'last_reviewed_at' => $task->last_reviewed_at?->format('Y-m-d\TH:i') ?? '',
                    'last_review_summary' => $task->last_review_summary ?? '',
                    'prerequisites' => $selected,
                ],
            ],
        ]));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $prerequisites = $data['prerequisites'] ?? [];
        unset($data['prerequisites']);

        // Stamp merged_at the first time a task reaches MERGED.
        if (($data['status'] ?? null) === TaskStatus::MERGED->value && $task->merged_at === null) {
            $data['merged_at'] = now();
        }

        $task->update($data);
        $task->prerequisites()->sync($prerequisites);

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', __('flash.task_updated'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('status', __('flash.task_deleted'));
    }

    /**
     * Toggle claim/release of a task by the current user.
     */
    public function claim(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('claim', $task);

        if ($task->claimed_by_id === null) {
            $this->statuses->applyRole($task, \App\Enums\StatusRole::CLAIMED, request()->user());
            $message = __('flash.task_claimed', ['name' => $task->name]);
        } else {
            $this->statuses->applyRole($task, \App\Enums\StatusRole::PICKABLE, request()->user());
            $message = __('flash.task_released', ['name' => $task->name]);
        }

        return back()->with('status', $message);
    }

    /**
     * Claim the review of a task: stamp the current user as reviewer. Only
     * possible while the task is in review, has no reviewer yet, and the user is
     * not its own assignee (you don't review your own work).
     */
    public function reviewClaim(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $user = request()->user();

        if ($task->status !== TaskStatus::IN_REVIEW
            || $task->reviewed_by !== null
            || $task->claimed_by_id === $user->id) {
            return back()->with('status', __('flash.review_cannot_claim'));
        }

        $task->update(['reviewed_by' => $user->id]);

        return back()->with('status', __('flash.review_claimed', ['name' => $task->name]));
    }

    /**
     * Board drag-and-drop status change. Validates the transition server-side
     * against the *derived* board status (the column the card visually sits in)
     * and returns the updated task as JSON. Rejected transitions → 422 so the
     * React board can snap the card back and show a toast.
     *
     * The card's status change is authoritative here; the display status is
     * re-derived from gates afterwards (a card dropped into PICKABLE with an
     * unmet gate will correctly come back as BLOCKED).
     */
    public function move(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $organization = $project->organization;
        // Target is an org status key (may be a custom, non-enum status).
        $target = $organization->statusForKey($data['status']);

        if ($target === null) {
            return response()->json(['message' => __('board.move_forbidden', [
                'from' => '?', 'to' => $data['status'],
            ])], 422);
        }

        $decorated = $this->board->decorate($project)->firstWhere('id', $task->id) ?? $task;
        $currentKey = $this->presenter->displayKeyFor($decorated, $project);

        $workflow = OrgBoardWorkflow::forOrganization($organization);
        if (! $workflow->canTransition($currentKey, $target->key)) {
            return response()->json([
                'message' => __('board.move_forbidden', ['from' => $currentKey, 'to' => $target->key]),
            ], 422);
        }

        // status_id is the authority; the legacy ENUM is mirrored for canonical
        // keys (null for a custom status). The target status's configurable
        // on-enter effects (assignments / field population) are applied on top —
        // for a default org these reproduce the former claim/release/merge
        // side effects.
        $attrs = [
            'status_id' => $target->id,
            'status' => TaskStatus::tryFrom($target->key)?->value,
        ];
        $attrs = array_merge($attrs, StatusEffects::resolve($task, $target, $request->user()));

        $task->update($attrs);

        $fresh = $this->board->board($project)->firstWhere('id', $task->id);

        return response()->json([
            'task' => $this->presenter->task($fresh, $project, $request->user()->id),
        ]);
    }
}
