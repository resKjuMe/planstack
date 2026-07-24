<?php

namespace App\Http\Controllers;

use App\Enums\StatusRole;
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
use App\Support\TaskShowPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // Statische Hülle (Titel/URLs/Strings) sofort; die Formular-Daten
        // (Options-Listen + Kandidaten) werden per Deferred-Prop nachgeladen, damit
        // die Seite sofort mit Skeleton erscheint (einheitliches Ladeverhalten).
        $strings = $formPresenter->shared($project)['strings'];
        $strings['title'] = __('tasks.new_task');
        $strings['submit'] = __('tasks.create_task');

        return Inertia::render('TaskCreate', [
            'project' => ['alias' => $project->alias, 'showUrl' => route('projects.show', $project)],
            'showReview' => false,
            'storeUrl' => route('projects.tasks.store', $project),
            'strings' => $strings,
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'formData' => Inertia::defer(function () use ($project, $formPresenter) {
                $shared = $formPresenter->shared($project);
                unset($shared['strings']);
                $shared['candidates'] = $project->tasks()->orderBy('name')
                    ->get(['id', 'name', 'summary'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'summary' => $c->summary])->values();

                return $shared;
            }),
        ]);
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

    public function show(Project $project, Task $task, TaskShowPresenter $presenter): InertiaResponse
    {
        $this->authorize('view', $task);

        // Akzeptanzkriterien/Testanleitung sind IMMER abhakbare Checklisten:
        // vorhandene Freitext-Prosa wird beim Anzeigen einmalig automatisch in
        // Items konvertiert (idempotent, nur mit Update-Recht — read-only Betrachter
        // lösen keine Schreibvorgänge aus).
        if (auth()->user()?->can('update', $task)) {
            \App\Support\ChecklistConverter::ensure($task, 'acceptance');
            \App\Support\ChecklistConverter::ensure($task, 'test');
        }

        $task->load([
            'creator', 'claimer', 'phase', 'reviewer', 'concern.creator',
            'prerequisites', 'dependents', 'checklistItems.checker',
        ]);

        return Inertia::render('TaskShow', array_merge($presenter->props($project, $task), [
            'flash' => ['status' => session('status'), 'error' => session('error')],
        ]));
    }

    public function edit(Project $project, Task $task, TaskFormPresenter $formPresenter): InertiaResponse
    {
        $this->authorize('update', $task);

        $strings = $formPresenter->shared($project)['strings'];
        $strings['title'] = __('tasks.edit_task');
        $strings['submit'] = __('common.save');
        $strings['deleteTitle'] = __('tasks.delete_task');
        $strings['deleteConfirm'] = __('tasks.really_delete_this_task');
        $strings['delete'] = __('common.delete');

        // Hülle (Kopf/URLs/Strings) sofort; Options-Listen, Kandidaten, Werte und
        // Löschrecht per Deferred-Prop nachladen (Skeleton während des Ladens).
        return Inertia::render('TaskEdit', [
            'project' => ['alias' => $project->alias],
            'task' => ['name' => $task->name],
            'showReview' => $task->status === TaskStatus::IN_REVIEW,
            'updateUrl' => route('projects.tasks.update', [$project, $task]),
            'destroyUrl' => route('projects.tasks.destroy', [$project, $task]),
            'showUrl' => route('projects.tasks.show', [$project, $task]),
            'strings' => $strings,
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'formData' => Inertia::defer(function () use ($project, $task, $formPresenter) {
                $shared = $formPresenter->shared($project);
                unset($shared['strings']);
                $shared['candidates'] = $project->tasks()->whereKeyNot($task->id)->orderBy('name')
                    ->get(['id', 'name', 'summary'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'summary' => $c->summary])->values();
                $shared['canDelete'] = auth()->user()->can('delete', $task);
                $shared['values'] = [
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
                    'prerequisites' => $task->prerequisites()->pluck('tasks.id')->all(),
                ];

                return $shared;
            }),
        ]);
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
     * possible while the task awaits review (in the REVIEWABLE pool, e.g. column
     * REVIEWBAR, or a not-yet-taken IN_REVIEW task), has no reviewer yet, and the
     * user is not its own assignee (you don't review your own work).
     */
    public function reviewClaim(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $user = request()->user();
        $organization = $project->organization;

        if (! in_array($task->status_id, $organization?->reviewPoolStatusIds() ?? [], true)
            || $task->reviewed_by !== null
            || $task->claimed_by_id === $user->id) {
            return back()->with('status', __('flash.review_cannot_claim'));
        }

        // Reviewer stempeln. Die Board-UI arbeitet ohne Fortschritts-Events,
        // daher den Task hier — analog zu move() — aktiv aus dem Pool (REVIEWBAR)
        // nach IN_REVIEW ziehen, sofern der Übergang erlaubt ist. Liegt er schon
        // (verwaist) in IN_REVIEW, bleibt der Status und nur reviewed_by wird
        // gesetzt.
        $attrs = [];
        $inReview = $organization?->statusForRole(StatusRole::IN_REVIEW);
        if ($inReview !== null
            && $task->status_id !== $inReview->id
            && OrgBoardWorkflow::forOrganization($organization)->canTransition($task->orgStatus?->key, $inReview->key)) {
            $attrs = array_merge(
                ['status_id' => $inReview->id, 'status' => TaskStatus::tryFrom($inReview->key)?->value],
                StatusEffects::resolve($task, $inReview, $user),
            );
        }
        $attrs['reviewed_by'] = $user->id;

        $task->update($attrs);

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
