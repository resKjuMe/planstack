<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewRecommendation;
use App\Enums\StatusRole;
use App\Enums\TaskStatus;
use App\Http\Middleware\AttachPlanstackConfig;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskBoardService;
use App\Support\TaskStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends ApiController
{
    public function __construct(
        private readonly TaskBoardService $board,
        private readonly TaskStatusService $statuses,
    ) {}

    /**
     * GET /api/projects/{project}/tasks — all tasks with computed board fields.
     */
    public function index(Project $project): JsonResource
    {
        $this->authorize('view', $project);

        $tasks = $this->board->board($project);
        $tasks->each->load('phase', 'claimer', 'concern', 'reviewer');

        return TaskResource::collection($tasks);
    }

    /**
     * GET /api/tasks — alle Tasks der zugänglichen Projekte (org-weit), dekoriert
     * und mit vollem Feldumfang (dieselben Relationen wie der Board-Read), sodass
     * der geteilte Client-Store daraus sowohl die Projektübersicht (Aggregate/
     * Segmente je Projekt) als auch die Projekt-Unterseiten speisen kann.
     */
    public function all(Request $request): JsonResource
    {
        $user = $request->user();
        $userId = $user->id;
        $isOrgOwner = $user->organization?->isOwner($user) === true;

        $projects = Project::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $isOrgOwner, fn ($q) => $q
                ->where(fn ($inner) => $inner
                    ->where('created_by_id', $userId)
                    ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $userId))))
            ->get();

        $tasks = collect();
        foreach ($projects as $project) {
            $decorated = $this->board->board($project);
            $decorated->loadMissing(['phase', 'claimer', 'reviewer', 'concern', 'prerequisites.orgStatus', 'pullRequests']);
            $tasks = $tasks->merge($decorated);
        }

        return TaskResource::collection($tasks->values());
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
     * GET /api/projects/{project}/tasks/by-name/{name} — find a task by its exact
     * name within the project (e.g. "C27"), decorated. Unlike the {task} segment
     * (which treats a numeric value as an id), this always matches the `name`
     * column, so it disambiguates the rare numeric task name. 404 when unknown.
     */
    public function showByName(Project $project, string $name): JsonResource
    {
        $this->authorize('view', $project);

        $task = $project->tasks()->where('name', $name)->firstOrFail();

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * GET /api/projects/{project}/tasks/by-pr/{pr} — find the task carrying a given
     * pull-request number within the project, decorated (pr_number/pr_url always
     * included, so the review/fix flow can resolve a PR → task server-side instead
     * of scanning all tasks). 404 when no task references that PR.
     */
    public function showByPr(Project $project, int $pr): JsonResource|JsonResponse
    {
        $this->authorize('view', $project);

        if ($pr < 1) {
            return $this->conflict('Ungültige PR-Nummer.', 422);
        }

        $task = $project->tasks()->where('pr_number', $pr)->firstOrFail();

        return $this->reviewResource($project, $task);
    }

    /**
     * POST /api/projects/{project}/tasks — create a task with an optional gate.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('contribute', $project);

        $data = $this->validateTask($request, $project);
        $gate = $this->resolveGate($project, $request->input('gate', []));

        $custom = $this->customFieldValues($request, $project, null);
        if ($custom !== false) {
            $data['custom_fields'] = $custom;
        }

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

        // Partielles Update: PUT/PATCH ist kein Voll-Update — nur die
        // mitgeschickten Felder werden validiert und aktualisiert.
        $data = $this->validateTask($request, $project, partial: true);

        $custom = $this->customFieldValues($request, $project, $task);
        if ($custom !== false) {
            $data['custom_fields'] = $custom;
        }

        if (($data['status'] ?? null) === TaskStatus::MERGED->value && $task->merged_at === null) {
            $data['merged_at'] = now();
        }

        $task->update($data);

        if ($request->has('gate')) {
            $task->prerequisites()->sync(
                $this->resolveGate($project, $request->input('gate', []), excludeTaskId: $task->id)
            );
            // Pivot-Sync feuert kein Task-Event; nur wenn oben KEINE Felder
            // aktualisiert wurden (sonst hat $task->update bereits gebroadcastet).
            if ($data === []) {
                $task->emitEntityChange('update');
            }
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
            // Bereits vom eigenen Nutzer beansprucht → idempotent bestätigen
            // (kein erneutes Anwenden der CLAIMED-Rolle, um einen bereits
            // fortgeschrittenen Status nicht zurückzusetzen).
            if ($task->claimed_by_id === $request->user()->id) {
                return $this->ack($project, $task);
            }

            return $this->conflict('Task ist bereits beansprucht.');
        }

        if (! $this->statuses->allowsTransition($task, StatusRole::CLAIMED)) {
            return $this->transitionConflict($task, StatusRole::CLAIMED);
        }

        // Config-driven: move to the CLAIMED-role status and apply its on-enter
        // effects (default seed sets claimed_by_id=@actor, claimed_at=@now).
        $this->statuses->applyRole($task, StatusRole::CLAIMED, $request->user());

        return $this->ack($project, $task);
    }

    /**
     * POST /api/projects/{project}/claim-next — pick the best pickable task
     * (most `unlocks`) and claim it atomically for the token user, in one call.
     *
     * Bundles the board-pick + claim (+ the follow-up task read): the response is
     * the claimed task itself, decorated and shaped by `task.fields`, so the
     * worker has enough to start (summary, acceptance_criteria, gate/stacking …)
     * without a second request. Replaces the GET /board → POST /claim → GET /task
     * round-trips and the 409-retry loop.
     *
     * Concurrency-safe for parallel workers: the claim is a conditional UPDATE
     * (WHERE claimed_by_id IS NULL), so exactly one worker wins a given task; the
     * others fall through to the next candidate. Returns 200 `{"claimed": null}`
     * when nothing is pickable.
     */
    public function claimNext(Request $request, Project $project): JsonResource|JsonResponse
    {
        $this->authorize('contribute', $project);

        $candidates = $this->board->board($project)
            ->filter(fn ($t) => $t->x_pickable)
            ->sortByDesc('x_unlocks')
            ->values();

        $claimedStatus = $project->organization?->statusForRole(StatusRole::CLAIMED);

        foreach ($candidates as $candidate) {
            // Atomarer Claim: nur greifen, wenn der Task noch frei ist. Bei
            // paralleler Konkurrenz trifft genau ein Worker (affected rows == 1),
            // alle anderen probieren den nächsten Kandidaten. Dieser Query-Builder-
            // Update umgeht Model-Events, daher status_id explizit setzen (via
            // attributesFor: status_id + enum-Spiegel + On-Enter-Effekte).
            $attrs = $claimedStatus !== null
                ? $this->statuses->attributesFor($candidate, $claimedStatus, $request->user())
                : ['claimed_by_id' => $request->user()->id, 'claimed_at' => now(), 'status' => TaskStatus::CLAIMED->value];

            $claimed = Task::whereKey($candidate->id)
                ->whereNull('claimed_by_id')
                ->whereNull('pr_number')
                ->update($attrs);

            if ($claimed === 1) {
                // Query-Builder-Update umgeht Eloquent-Events → entity-changed
                // (Socket) explizit anstoßen, damit offene Boards nachladen.
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return new TaskResource($this->decorateOne($project, $candidate));
            }
        }

        return response()->json(['claimed' => null]);
    }

    /**
     * POST /api/projects/{project}/review-next — pick the first task that is
     * IN_REVIEW and has a PR, take over its review (set reviewed_by) for the token
     * user, and return it decorated (pr_url, summary … for reviewing). Ordered
     * oldest-first. Concurrency-safe: reviewed_by is set via a conditional UPDATE,
     * so two reviewers never grab the same task. Returns 200 `{"reviewing": null}`
     * when nothing is awaiting review.
     */
    public function reviewNext(Request $request, Project $project): JsonResource|JsonResponse
    {
        $this->authorize('contribute', $project);

        $uid = $request->user()->id;

        $reviewStatusId = $project->organization?->statusForRole(StatusRole::IN_REVIEW)?->id;

        $candidates = $project->tasks()
            ->where('status_id', $reviewStatusId)
            ->whereNotNull('pr_number')
            ->whereNull('reviewed_by')
            // Eigene Tasks (selbst beansprucht/umgesetzt) nicht zum Review picken.
            ->where(fn ($q) => $q->whereNull('claimed_by_id')->orWhere('claimed_by_id', '!=', $uid))
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            $claimed = Task::whereKey($candidate->id)
                ->whereNull('reviewed_by')
                ->update(['reviewed_by' => $request->user()->id]);

            if ($claimed === 1) {
                // Query-Builder-Update umgeht Eloquent-Events → Socket explizit anstoßen.
                $candidate->setRelation('project', $project);
                $candidate->emitEntityChange('update');

                return $this->reviewResource($project, $candidate);
            }
        }

        return response()->json(['reviewing' => null]);
    }

    /**
     * POST .../review-claim — take over the review of a specific IN_REVIEW task
     * (set reviewed_by) before running the actual review.
     */
    public function reviewClaim(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->status !== TaskStatus::IN_REVIEW) {
            return $this->conflict('Task ist nicht in Review.');
        }

        if ($task->claimed_by_id === $request->user()->id) {
            return $this->conflict('Du kannst deinen eigenen Task nicht reviewen.');
        }

        if ($task->reviewed_by !== null && $task->reviewed_by !== $request->user()->id) {
            return $this->conflict('Review ist bereits übernommen.');
        }

        $task->update(['reviewed_by' => $request->user()->id]);

        return $this->reviewResource($project, $task);
    }

    /**
     * POST .../review — record a completed review result on the task:
     * last_review_recommendation (APPROVE | REQUEST_CHANGES), last_review_summary
     * and last_reviewed_at (now). Sets reviewed_by to the token user if unset.
     */
    public function review(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->claimed_by_id === $request->user()->id) {
            return $this->conflict('Du kannst deinen eigenen Task nicht reviewen.');
        }

        $data = $request->validate([
            'recommendation' => ['required', Rule::enum(ReviewRecommendation::class)],
            'summary' => ['nullable', 'string'],
        ]);

        $task->update([
            'reviewed_by' => $task->reviewed_by ?? $request->user()->id,
            'last_reviewed_at' => now(),
            'last_review_recommendation' => $data['recommendation'],
            'last_review_summary' => $data['summary'] ?? null,
        ]);

        return $this->reviewResource($project, $task);
    }

    /**
     * POST .../release — release a task the token user holds.
     */
    public function release(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('claim', $task);

        if ($task->claimed_by_id === null) {
            return $this->conflict('Task ist nicht beansprucht.');
        }

        // Config-driven: PICKABLE-role status; its on-enter effects clear the
        // assignee (default seed: claimed_by_id/@clear, claimed_at/@clear).
        $this->statuses->applyRole($task, StatusRole::PICKABLE, $request->user());

        return $this->ack($project, $task);
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
    public function status(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(['analyze', 'in_progress', 'in_review', 'done'])],
        ]);

        $role = match ($data['status']) {
            'analyze' => StatusRole::ANALYZING,
            'in_review' => StatusRole::IN_REVIEW,
            'done' => $task->pr_number !== null ? StatusRole::IN_REVIEW : StatusRole::IN_PROGRESS,
            'in_progress' => StatusRole::IN_PROGRESS,
        };

        if (! $this->statuses->allowsTransition($task, $role)) {
            return $this->transitionConflict($task, $role);
        }

        $this->statuses->applyRole($task, $role, $request->user());

        return $this->ack($project, $task);
    }

    /**
     * POST .../pr — set the PR number (digits only).
     */
    public function pr(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'pr_number' => ['required', 'integer', 'min:1'],
        ]);

        $task->update(['pr_number' => $data['pr_number']]);

        return $this->ack($project, $task);
    }

    /**
     * POST .../merge — mark MERGED; merged_at only on the first transition.
     */
    public function merge(Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        if (! $this->statuses->allowsTransition($task, StatusRole::MERGED)) {
            return $this->transitionConflict($task, StatusRole::MERGED);
        }

        $this->markMerged($task);

        return $this->ack($project, $task);
    }

    /**
     * POST .../complete — the bundled "work finished" action (actions.bundling):
     * optionally set the PR number, move to done (⇒ IN_REVIEW when a PR exists,
     * otherwise IN_PROGRESS), and optionally merge — all in one round-trip.
     */
    public function complete(Request $request, Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'pr_number' => ['sometimes', 'integer', 'min:1'],
            'merge' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('pr_number', $data)) {
            $task->pr_number = $data['pr_number'];
            $task->save();
        }

        if ($data['merge'] ?? false) {
            if (! $this->statuses->allowsTransition($task, StatusRole::MERGED)) {
                return $this->transitionConflict($task, StatusRole::MERGED);
            }
            $this->markMerged($task, $request->user());
        } else {
            $role = $task->pr_number !== null ? StatusRole::IN_REVIEW : StatusRole::IN_PROGRESS;
            if (! $this->statuses->allowsTransition($task, $role)) {
                return $this->transitionConflict($task, $role);
            }
            $this->statuses->applyRole($task, $role, $request->user());
        }

        return $this->ack($project, $task);
    }

    /**
     * Mark a task MERGED. The MERGED-role status's on-enter effects stamp
     * merged_at (default seed: @now, only when empty).
     */
    private function markMerged(Task $task, ?\App\Models\User $actor = null): void
    {
        $this->statuses->applyRole($task, StatusRole::MERGED, $actor);
    }

    /**
     * POST .../gate — replace the task's prerequisites.
     */
    public function gate(Request $request, Project $project, Task $task): JsonResource
    {
        $this->authorize('update', $task);

        $gate = $this->resolveGate($project, $request->input('gate', []), excludeTaskId: $task->id);
        $task->prerequisites()->sync($gate);

        // Gate-Änderung ist ein Pivot-Sync (keine Task-Spalte) → kein Eloquent-
        // Event. Socket explizit anstoßen, da sich blocked/pickable ändern kann.
        $task->emitEntityChange('update');

        return new TaskResource($this->decorateOne($project, $task));
    }

    /**
     * POST .../concern — set/update the concern and flip to CONCERNED.
     */
    public function concern(Request $request, Project $project, Task $task): JsonResource|JsonResponse
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

        $this->statuses->applyRole($task, StatusRole::CONCERNED, $request->user());

        return $this->ack($project, $task->fresh());
    }

    /**
     * DELETE .../concern — resolve the concern; task returns to pickable/unknown.
     */
    public function resolveConcern(Project $project, Task $task): JsonResource|JsonResponse
    {
        $this->authorize('update', $task);

        $task->concern()->delete();

        if ($task->status === TaskStatus::CONCERNED) {
            $this->statuses->applyRole($task, $task->claimed_by_id ? StatusRole::CLAIMED : StatusRole::PICKABLE);
        }

        return $this->ack($project, $task->fresh());
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
            $this->statuses->applyRole($task, StatusRole::COMPLETED, $request->user());

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
    private function validateTask(Request $request, Project $project, bool $partial = false): array
    {
        // Bei einem partiellen Update (PUT/PATCH) sind auch name/summary optional —
        // nur mitgeschickte Felder werden angewandt. Bei store() bleiben sie Pflicht.
        $req = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$req, 'string', 'max:50'],
            'summary' => [$req, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // The public API exposes this as `acceptance_criteria` (see
            // TaskResource); accept that name (and the legacy column name) on
            // input and map it to the stored column below.
            'acceptance_criteria' => ['nullable', 'string'],
            'description_acceptance_criteria' => ['nullable', 'string'],
            // Exposed as `target_actual` / `test_cases` (see TaskResource); accept
            // those names and the legacy column names, mapped to the columns below.
            'target_actual' => ['nullable', 'string'],
            'description_target_actual' => ['nullable', 'string'],
            'test_cases' => ['nullable', 'string'],
            'description_test_cases' => ['nullable', 'string'],
            'criticality' => ['nullable', Rule::enum(\App\Enums\Criticality::class)],
            'phase_id' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $project->id)],
            'effort_man_days' => ['nullable', 'integer', 'min:0'],
            'effort_story_points' => ['nullable', 'integer', 'min:0'],
            'effort_tokens' => ['nullable', 'integer', 'min:0'],
            'affected_files' => ['nullable', 'integer', 'min:0'],
            'reviewed_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
        ]);

        // Fold the public field names onto the underlying columns (the short name
        // wins if both are present) so create() actually persists them.
        foreach ([
            'acceptance_criteria' => 'description_acceptance_criteria',
            'target_actual' => 'description_target_actual',
            'test_cases' => 'description_test_cases',
        ] as $public => $column) {
            if (array_key_exists($public, $data)) {
                $data[$column] = $data[$public];
                unset($data[$public]);
            }
        }

        return $data;
    }

    /**
     * Validate and merge the posted `custom_fields` object against the org's
     * field definitions (App\Models\CustomField). Returns:
     *  - false when no `custom_fields` key was sent (⇒ leave the column untouched),
     *  - the merged value map otherwise (partial update: provided keys override,
     *    a null value clears that key; empty result ⇒ null column).
     *
     * Each value is validated with the field's type rule plus its optional,
     * org-defined Laravel rule. Unknown keys raise a 422.
     *
     * @return array<string, mixed>|null|false
     */
    private function customFieldValues(Request $request, Project $project, ?Task $task): array|null|false
    {
        if (! $request->has('custom_fields')) {
            return false;
        }

        $input = $request->input('custom_fields');
        if (! is_array($input)) {
            throw ValidationException::withMessages([
                'custom_fields' => 'custom_fields muss ein Objekt (key ⇒ value) sein.',
            ]);
        }

        $definitions = $project->organization
            ? $project->organization->customFields()->get()->keyBy('key')
            : collect();

        $rules = [];
        foreach ($input as $key => $value) {
            $definition = $definitions->get($key);
            if ($definition === null) {
                throw ValidationException::withMessages([
                    "custom_fields.$key" => "Unbekanntes benutzerdefiniertes Feld: \"$key\".",
                ]);
            }
            if ($value !== null) {
                $rules["custom_fields.$key"] = $definition->valueRules();
            }
        }

        if ($rules !== []) {
            Validator::make(['custom_fields' => $input], $rules)->validate();
        }

        // Partielles Update: bestehende Werte übernehmen, mitgeschickte
        // überschreiben, null entfernt den Schlüssel.
        $merged = $task?->custom_fields ?? [];
        foreach ($input as $key => $value) {
            if ($value === null) {
                unset($merged[$key]);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged ?: null;
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
     * The response for a write action. Honors `claim.return_details`: when off,
     * a minimal ack (id/name/status) instead of the full decorated task — the
     * client re-reads the board anyway. When on, the full TaskResource (itself
     * trimmed by `task.fields`).
     */
    private function ack(Project $project, Task $task): JsonResource|JsonResponse
    {
        $decorated = $this->decorateOne($project, $task);

        if (! AttachPlanstackConfig::value(request(), 'claim.return_details')) {
            return response()->json([
                'id' => $decorated->id,
                'name' => $decorated->name,
                'status' => $decorated->status?->value ?? $decorated->orgStatus?->key,
                'display_status' => $decorated->displayStatusKey(),
            ]);
        }

        return new TaskResource($decorated);
    }

    /**
     * 409 for a disallowed status transition (config workflow), naming the
     * current and target status.
     */
    private function transitionConflict(Task $task, StatusRole $target): JsonResponse
    {
        return $this->conflict(__('board.move_forbidden', [
            'from' => $task->status?->value ?? $task->orgStatus?->key ?? '?',
            'to' => $target->value,
        ]));
    }

    /**
     * A conflict/error response, suppressing the message body when
     * `response.errors=minimal` (status code carries the meaning).
     */
    private function conflict(string $message, int $status = 409): JsonResponse
    {
        $minimal = AttachPlanstackConfig::value(request(), 'response.errors') === 'minimal';

        return response()->json($minimal ? new \stdClass : ['message' => $message], $status);
    }

    /**
     * Load one task with its relations and attach board attributes.
     */
    private function decorateOne(Project $project, Task $task): Task
    {
        $tasks = $this->board->board($project);
        $decorated = $tasks->firstWhere('id', $task->id) ?? $task;
        // Relationen mitladen, damit ein partiell nachgeladener Task im React-Store
        // dieselbe Form wie in der Board-Liste hat (Summary-Blocker, Diagramm,
        // Kalibrierungs-Ist-Kennzahlen).
        $decorated->loadMissing(['phase', 'claimer', 'concern', 'reviewer', 'prerequisites.orgStatus', 'pullRequests']);

        return $decorated;
    }

    /**
     * Review response: the decorated task with the PR always included
     * (pr_number/pr_url), regardless of the project's task.fields config — the
     * review flow must be able to address the PR even under `minimal`.
     */
    private function reviewResource(Project $project, Task $task): TaskResource
    {
        $resource = new TaskResource($this->decorateOne($project, $task));
        $resource->alwaysIncludePr = true;

        return $resource;
    }
}
