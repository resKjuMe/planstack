<?php

namespace App\Support\Mcp;

use App\Enums\StatusRole;
use App\Enums\TaskEvent;
use App\Enums\TaskStatus;
use App\Http\Resources\PhaseResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskBoardService;
use App\Support\TaskEventService;
use App\Support\TaskStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The Planstack MCP server: exposes the board/task/phase operations as MCP
 * tools, scoped to a single project. It reuses {@see TaskBoardService} and the
 * API resources so the tool output never drifts from the REST API.
 *
 * Transport and JSON-RPC framing live in the McpController; this class only
 * knows the protocol metadata, the tool catalogue and how to run a tool.
 */
class McpServer
{
    /** Protocol revisions this server understands (newest first). */
    private const SUPPORTED_PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(
        private readonly TaskBoardService $board,
        private readonly TaskStatusService $statuses,
        private readonly TaskEventService $events,
    ) {}

    /**
     * Echo the client's requested protocol version when we support it, otherwise
     * offer our newest. The client may still disconnect on a mismatch.
     */
    public function negotiateProtocol(?string $requested): string
    {
        return in_array($requested, self::SUPPORTED_PROTOCOLS, true)
            ? $requested
            : self::SUPPORTED_PROTOCOLS[0];
    }

    /**
     * @return array{name: string, version: string}
     */
    public function serverInfo(): array
    {
        return ['name' => 'planstack', 'version' => '1.0.0'];
    }

    /**
     * The tool catalogue (name, description, JSON-Schema of the arguments).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tools(): array
    {
        $taskArg = [
            'task' => ['type' => 'string', 'description' => 'Task-Name (z. B. "C27") oder numerische id.'],
        ];

        return [
            [
                'name' => 'get_board',
                'description' => 'Board-Read-Modell: totals (Fortschritt/SP/pickable), phases (Aggregate) und pickable (Tasks absteigend nach unlocks). Einstiegspunkt.',
                'inputSchema' => $this->schema([]),
            ],
            [
                'name' => 'list_tasks',
                'description' => 'Alle Tasks des Projekts inkl. berechneter Board-Felder.',
                'inputSchema' => $this->schema([]),
            ],
            [
                'name' => 'get_task',
                'description' => 'Ein Task mit Details (summary, acceptance_criteria, prerequisites, concern, Board-Felder).',
                'inputSchema' => $this->schema($taskArg, ['task']),
            ],
            [
                'name' => 'claim_task',
                'description' => 'Beansprucht einen freien Task für den Token-Benutzer (atomar). Fehler, wenn bereits beansprucht.',
                'inputSchema' => $this->schema($taskArg, ['task']),
            ],
            [
                'name' => 'release_task',
                'description' => 'Gibt einen beanspruchten Task wieder frei.',
                'inputSchema' => $this->schema($taskArg, ['task']),
            ],
            [
                'name' => 'set_task_status',
                'description' => 'Setzt den Bearbeitungsstatus: analyze → ANALYZING, in_progress → IN_PROGRESS, in_review → IN_REVIEW. done markiert die Arbeit als fertig: mit gesetztem PR → IN_REVIEW, sonst IN_PROGRESS.',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'status' => ['type' => 'string', 'enum' => ['analyze', 'in_progress', 'in_review', 'done']],
                ], ['task', 'status']),
            ],
            [
                'name' => 'set_task_pr',
                'description' => 'Trägt die PR-Nummer ein (nur Ziffern). Ein offener PR erfüllt das Gate abhängiger Tasks. Der Status wird dabei nicht verändert; erst set_task_status done/in_review hebt den Task auf IN_REVIEW.',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'pr_number' => ['type' => 'integer', 'minimum' => 1],
                ], ['task', 'pr_number']),
            ],
            [
                'name' => 'merge_task',
                'description' => 'Markiert den Task als MERGED (idempotent). Erst der Merge nimmt ihn vom Board.',
                'inputSchema' => $this->schema($taskArg, ['task']),
            ],
            [
                'name' => 'set_task_gate',
                'description' => 'Ersetzt die Voraussetzungen (Gate) des Tasks. Referenzen als Task-Namen und/oder ids, kein Self-Gate.',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'gate' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Liste aus Task-Namen und/oder ids.'],
                ], ['task', 'gate']),
            ],
            [
                'name' => 'report_concern',
                'description' => 'Legt/aktualisiert einen Concern und setzt den Task auf CONCERNED.',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'summary' => ['type' => 'string', 'description' => 'Kurzbeschreibung (max. 255).'],
                    'context' => ['type' => 'string'],
                    'blocker' => ['type' => 'string'],
                    'misconception' => ['type' => 'string'],
                    'decisions' => [
                        'type' => 'string',
                        'description' => 'Offene Entscheidungen, eine pro Zeile im Format "Frage;Option A;Option B;Option C" (Semikolon-getrennt). Optionen NICHT im Fragetext auflisten, z. B. nicht "Frage: (a) ... (b) ... (c) ...", sondern jede Option als eigenes Feld hinter einem Semikolon.',
                    ],
                ], ['task', 'summary']),
            ],
            [
                'name' => 'resolve_concern',
                'description' => 'Löst den Concern auf; der Task kehrt nach CLAIMED bzw. PICKABLE zurück.',
                'inputSchema' => $this->schema($taskArg, ['task']),
            ],
            [
                'name' => 'emit_event',
                'description' => 'Meldet ein Fortschritts-Event zum Task (Event-API). Wendet die je Event in der Organisation konfigurierte Automation an (optionaler Statuswechsel + Feld-Effekte) und protokolliert das Event; ohne Konfiguration reine Meldung. Best-effort, nicht blockierend.',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'event' => [
                        'type' => 'string',
                        'enum' => array_map(fn (TaskEvent $e) => $e->value, TaskEvent::cases()),
                        'description' => 'Event-Name, z. B. CLAIMED, ANALYZING, PROCESSING, PUBLISHING, POLISHING, POLISHED, CONCERNED, REVIEWING, REVIEWED, APPROVED, CHANGES_REQUESTED.',
                    ],
                ], ['task', 'event']),
            ],
            [
                'name' => 'create_task',
                'description' => 'Legt einen Task mit optionalem Gate an.',
                'inputSchema' => $this->schema([
                    'name' => ['type' => 'string', 'description' => 'Kurzname (max. 50), z. B. "C40".'],
                    'summary' => ['type' => 'string', 'description' => 'Kurzbeschreibung (max. 255).'],
                    'description' => ['type' => 'string'],
                    'acceptance_criteria' => ['type' => 'string'],
                    'phase_id' => ['type' => 'integer'],
                    'effort_man_days' => ['type' => 'integer', 'minimum' => 0],
                    'effort_story_points' => ['type' => 'integer', 'minimum' => 0],
                    'effort_tokens' => ['type' => 'integer', 'minimum' => 0],
                    'affected_files' => ['type' => 'integer', 'minimum' => 0],
                    'gate' => ['type' => 'array', 'items' => ['type' => 'string']],
                ], ['name', 'summary']),
            ],
            [
                'name' => 'update_task',
                'description' => 'Aktualisiert die angegebenen Felder eines Tasks (nur mitgegebene Felder ändern sich). gate ersetzt die Voraussetzungen; weglassen lässt sie unverändert.',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'name' => ['type' => 'string', 'description' => 'Kurzname (max. 50).'],
                    'summary' => ['type' => 'string', 'description' => 'Kurzbeschreibung (max. 255).'],
                    'description' => ['type' => 'string'],
                    'acceptance_criteria' => ['type' => 'string'],
                    'phase_id' => ['type' => 'integer'],
                    'effort_man_days' => ['type' => 'integer', 'minimum' => 0],
                    'effort_story_points' => ['type' => 'integer', 'minimum' => 0],
                    'effort_tokens' => ['type' => 'integer', 'minimum' => 0],
                    'affected_files' => ['type' => 'integer', 'minimum' => 0],
                    'status' => ['type' => 'string', 'description' => 'TaskStatus-Wert, z. B. IN_PROGRESS (MERGED setzt merged_at).'],
                    'gate' => ['type' => 'array', 'items' => ['type' => 'string']],
                ], ['task']),
            ],
            [
                'name' => 'split_task',
                'description' => 'Setzt den Parent auf COMPLETED und legt N Kinder in derselben Phase an (atomar).',
                'inputSchema' => $this->schema([
                    ...$taskArg,
                    'children' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'summary' => ['type' => 'string'],
                                'effort_story_points' => ['type' => 'integer', 'minimum' => 0],
                                'effort_man_days' => ['type' => 'integer', 'minimum' => 0],
                                'effort_tokens' => ['type' => 'integer', 'minimum' => 0],
                                'affected_files' => ['type' => 'integer', 'minimum' => 0],
                                'gate' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                            'required' => ['name', 'summary'],
                        ],
                    ],
                ], ['task', 'children']),
            ],
            [
                'name' => 'list_phases',
                'description' => 'Phasen des Projekts, nach position sortiert.',
                'inputSchema' => $this->schema([]),
            ],
            [
                'name' => 'create_phase',
                'description' => 'Legt eine Phase an. Ohne position wird sie hinten angehängt.',
                'inputSchema' => $this->schema([
                    'name' => ['type' => 'string'],
                    'position' => ['type' => 'integer', 'minimum' => 0],
                ], ['name']),
            ],
        ];
    }

    /**
     * Run a tool and return its result content as a JSON string. Throws
     * {@see McpToolException} for recoverable, model-visible failures.
     *
     * @param  array<string, mixed>  $args
     */
    public function callTool(Project $project, User $user, string $name, array $args): string
    {
        return match ($name) {
            'get_board' => $this->getBoard($project),
            'list_tasks' => $this->listTasks($project),
            'get_task' => $this->json($this->taskPayload($project, $this->findTask($project, $args))),
            'claim_task' => $this->claimTask($project, $user, $args),
            'release_task' => $this->releaseTask($project, $user, $args),
            'set_task_status' => $this->setStatus($project, $user, $args),
            'set_task_pr' => $this->setPr($project, $user, $args),
            'merge_task' => $this->mergeTask($project, $user, $args),
            'set_task_gate' => $this->setGate($project, $user, $args),
            'report_concern' => $this->reportConcern($project, $user, $args),
            'resolve_concern' => $this->resolveConcern($project, $user, $args),
            'emit_event' => $this->emitEvent($project, $user, $args),
            'create_task' => $this->createTask($project, $user, $args),
            'update_task' => $this->updateTask($project, $user, $args),
            'split_task' => $this->splitTask($project, $user, $args),
            'list_phases' => $this->listPhases($project),
            'create_phase' => $this->createPhase($project, $user, $args),
            default => throw new McpToolException("Unbekanntes Tool: \"{$name}\"."),
        };
    }

    // ---- Tool handlers -----------------------------------------------------

    private function getBoard(Project $project): string
    {
        $this->authorize('view', $project, $project);

        $project->loadMissing('phases');
        $tasks = $this->board->board($project);

        $pickable = $tasks->filter(fn ($t) => $t->x_pickable)->sortByDesc('x_unlocks')->values();

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

        $pickable->each->load('phase', 'claimer', 'concern');

        return $this->json([
            'project' => ['id' => $project->id, 'alias' => $project->alias, 'name' => $project->name],
            'totals' => [
                'tasks' => $tasks->count(),
                'done' => $tasks->filter(fn ($t) => $this->board->isDelivered($t))->count(),
                'story_points' => (int) $tasks->sum('effort_story_points'),
                'done_story_points' => $doneSp,
                'pct' => (int) round($doneSp / $totalSp * 100),
                'pickable' => $pickable->count(),
            ],
            'phases' => $phases,
            'pickable' => TaskResource::collection($pickable)->resolve(),
        ]);
    }

    private function listTasks(Project $project): string
    {
        $this->authorize('view', $project, $project);

        $tasks = $this->board->board($project);
        $tasks->each->load('phase', 'claimer', 'concern');

        return $this->json(TaskResource::collection($tasks)->resolve());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function claimTask(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('claim', $task, $project);

        if ($task->claimed_by_id !== null) {
            throw new McpToolException($task->claimed_by_id === $user->id
                ? 'Du hast diesen Task bereits beansprucht.'
                : 'Task ist bereits beansprucht.');
        }

        if (! $this->statuses->allowsTransition($task, StatusRole::CLAIMED)) {
            throw new McpToolException($this->transitionMessage($task, StatusRole::CLAIMED));
        }

        $this->statuses->applyRole($task, StatusRole::CLAIMED, $user);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function releaseTask(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('claim', $task, $project);

        if ($task->claimed_by_id === null) {
            throw new McpToolException('Task ist nicht beansprucht.');
        }

        $this->statuses->applyRole($task, StatusRole::PICKABLE, $user);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function setStatus(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $status = $args['status'] ?? null;
        if (! in_array($status, ['analyze', 'in_progress', 'in_review', 'done'], true)) {
            throw new McpToolException('status muss analyze, in_progress, in_review oder done sein.');
        }

        $role = match ($status) {
            'analyze' => StatusRole::ANALYZING,
            'in_review' => StatusRole::IN_REVIEW,
            'done' => $task->pr_number !== null ? StatusRole::IN_REVIEW : StatusRole::IN_PROGRESS,
            default => StatusRole::IN_PROGRESS,
        };

        if (! $this->statuses->allowsTransition($task, $role)) {
            throw new McpToolException($this->transitionMessage($task, $role));
        }

        $this->statuses->applyRole($task, $role, $user);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function setPr(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $pr = filter_var($args['pr_number'] ?? null, FILTER_VALIDATE_INT);
        if ($pr === false || $pr < 1) {
            throw new McpToolException('pr_number muss eine positive Ganzzahl sein.');
        }

        $task->update(['pr_number' => $pr]);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function mergeTask(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        if (! $this->statuses->allowsTransition($task, StatusRole::MERGED)) {
            throw new McpToolException($this->transitionMessage($task, StatusRole::MERGED));
        }

        $this->statuses->applyRole($task, StatusRole::MERGED, $user);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function setGate(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $gate = $this->resolveGate($project, (array) ($args['gate'] ?? []), $task->id);
        $task->prerequisites()->sync($gate);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function reportConcern(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $summary = trim((string) ($args['summary'] ?? ''));
        if ($summary === '') {
            throw new McpToolException('summary ist erforderlich.');
        }

        $task->concern()->updateOrCreate(
            ['task_id' => $task->id],
            [
                'created_by_id' => $task->concern?->created_by_id ?? $user->id,
                'summary' => mb_substr($summary, 0, 255),
                'description_context' => $args['context'] ?? null,
                'description_blocker' => $args['blocker'] ?? null,
                'description_misconception' => $args['misconception'] ?? null,
                'description_decisions' => $args['decisions'] ?? null,
            ],
        );

        $this->statuses->applyRole($task, StatusRole::CONCERNED, $user);

        return $this->json($this->taskPayload($project, $task->fresh()));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function resolveConcern(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $task->concern()->delete();

        if ($task->status === TaskStatus::CONCERNED) {
            $this->statuses->applyRole($task, $task->claimed_by_id ? StatusRole::CLAIMED : StatusRole::PICKABLE, $user);
        }

        return $this->json($this->taskPayload($project, $task->fresh()));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function emitEvent(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $event = TaskEvent::tryFrom(strtoupper(trim((string) ($args['event'] ?? ''))));
        if ($event === null) {
            throw new McpToolException(
                'Ungültiges event. Erlaubt: '.implode(', ', array_map(fn (TaskEvent $e) => $e->value, TaskEvent::cases())).'.'
            );
        }

        $result = $this->events->record($task, $event, $user);

        return $this->json(['task' => $task->name, 'event' => $event->value, ...$result]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createTask(Project $project, User $user, array $args): string
    {
        $this->authorize('contribute', $project, $project);

        $name = trim((string) ($args['name'] ?? ''));
        $summary = trim((string) ($args['summary'] ?? ''));
        if ($name === '' || $summary === '') {
            throw new McpToolException('name und summary sind erforderlich.');
        }

        if (isset($args['phase_id']) && ! $project->phases()->whereKey($args['phase_id'])->exists()) {
            throw new McpToolException('phase_id gehört nicht zu diesem Projekt.');
        }

        $gate = $this->resolveGate($project, (array) ($args['gate'] ?? []));

        $task = $project->tasks()->create([
            'created_by_id' => $user->id,
            'name' => mb_substr($name, 0, 50),
            'summary' => mb_substr($summary, 0, 255),
            'description' => $args['description'] ?? null,
            'description_acceptance_criteria' => $args['acceptance_criteria'] ?? null,
            'phase_id' => $args['phase_id'] ?? null,
            'effort_man_days' => $this->intOrZero($args['effort_man_days'] ?? null),
            'effort_story_points' => $this->intOrZero($args['effort_story_points'] ?? null),
            'effort_tokens' => $this->intOrZero($args['effort_tokens'] ?? null),
            'affected_files' => $this->intOrZero($args['affected_files'] ?? null),
            'status' => TaskStatus::UNKNOWN->value,
        ]);

        $task->prerequisites()->sync($gate);

        return $this->json($this->taskPayload($project, $task));
    }

    /**
     * Update the writable fields of an existing task. Only keys present in $args
     * are touched (partial update); `gate` (when present) replaces the
     * prerequisites. Mirrors the REST PUT .../tasks/{id}.
     *
     * @param  array<string, mixed>  $args
     */
    private function updateTask(Project $project, User $user, array $args): string
    {
        $task = $this->findTask($project, $args);
        $this->authorize('update', $task, $project);

        $update = [];

        if (array_key_exists('name', $args)) {
            $name = trim((string) $args['name']);
            if ($name === '') {
                throw new McpToolException('name darf nicht leer sein.');
            }
            $update['name'] = mb_substr($name, 0, 50);
        }

        if (array_key_exists('summary', $args)) {
            $summary = trim((string) $args['summary']);
            if ($summary === '') {
                throw new McpToolException('summary darf nicht leer sein.');
            }
            $update['summary'] = mb_substr($summary, 0, 255);
        }

        if (array_key_exists('description', $args)) {
            $update['description'] = $args['description'];
        }

        if (array_key_exists('acceptance_criteria', $args)) {
            $update['description_acceptance_criteria'] = $args['acceptance_criteria'];
        }

        if (array_key_exists('phase_id', $args)) {
            if ($args['phase_id'] !== null && ! $project->phases()->whereKey($args['phase_id'])->exists()) {
                throw new McpToolException('phase_id gehört nicht zu diesem Projekt.');
            }
            $update['phase_id'] = $args['phase_id'];
        }

        foreach (['effort_man_days', 'effort_story_points', 'effort_tokens', 'affected_files'] as $field) {
            if (array_key_exists($field, $args)) {
                $update[$field] = $this->intOrZero($args[$field]);
            }
        }

        if (array_key_exists('status', $args)) {
            $status = TaskStatus::tryFrom((string) $args['status']);
            if ($status === null) {
                throw new McpToolException('status ist kein gültiger TaskStatus-Wert.');
            }
            $update['status'] = $status->value;
            if ($status === TaskStatus::MERGED && $task->merged_at === null) {
                $update['merged_at'] = now();
            }
        }

        if ($update !== []) {
            $task->update($update);
        }

        if (array_key_exists('gate', $args)) {
            $task->prerequisites()->sync($this->resolveGate($project, (array) $args['gate'], $task->id));
        }

        return $this->json($this->taskPayload($project, $task->fresh()));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function splitTask(Project $project, User $user, array $args): string
    {
        $parent = $this->findTask($project, $args);
        $this->authorize('update', $parent, $project);

        $children = $args['children'] ?? null;
        if (! is_array($children) || $children === []) {
            throw new McpToolException('children muss mindestens einen Eintrag enthalten.');
        }

        $created = DB::transaction(function () use ($project, $parent, $children, $user) {
            $this->statuses->applyRole($parent, StatusRole::COMPLETED, $user);

            return collect($children)->map(function ($child) use ($project, $parent, $user) {
                $name = trim((string) ($child['name'] ?? ''));
                $summary = trim((string) ($child['summary'] ?? ''));
                if ($name === '' || $summary === '') {
                    throw new McpToolException('Jedes Kind braucht name und summary.');
                }

                $new = $project->tasks()->create([
                    'created_by_id' => $user->id,
                    'name' => mb_substr($name, 0, 50),
                    'summary' => mb_substr($summary, 0, 255),
                    'phase_id' => $parent->phase_id,
                    'effort_story_points' => $this->intOrZero($child['effort_story_points'] ?? null),
                    'effort_man_days' => $this->intOrZero($child['effort_man_days'] ?? null),
                    'effort_tokens' => $this->intOrZero($child['effort_tokens'] ?? null),
                    'affected_files' => $this->intOrZero($child['affected_files'] ?? null),
                    'status' => TaskStatus::UNKNOWN->value,
                ]);
                $new->prerequisites()->sync($this->resolveGate($project, (array) ($child['gate'] ?? []), $new->id));

                return $new;
            });
        });

        $decorated = $this->board->board($project)->whereIn('id', $created->pluck('id'))->values();
        $decorated->each->load('phase', 'claimer', 'concern');

        return $this->json(TaskResource::collection($decorated)->resolve());
    }

    private function listPhases(Project $project): string
    {
        $this->authorize('view', $project, $project);

        return $this->json(PhaseResource::collection($project->phases()->orderBy('position')->get())->resolve());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createPhase(Project $project, User $user, array $args): string
    {
        $this->authorize('contribute', $project, $project);

        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            throw new McpToolException('name ist erforderlich.');
        }

        $position = isset($args['position'])
            ? max(0, (int) $args['position'])
            : (((int) $project->phases()->max('position')) + 1);

        $phase = $project->phases()->create(['name' => mb_substr($name, 0, 100), 'position' => $position]);

        return $this->json((new PhaseResource($phase))->resolve());
    }

    // ---- Helpers -----------------------------------------------------------

    /**
     * Build a JSON-Schema object for a tool's arguments.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private function schema(array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => (object) $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Resolve the "task" argument (name or id) to a project-scoped task.
     *
     * @param  array<string, mixed>  $args
     */
    private function transitionMessage(Task $task, StatusRole $target): string
    {
        return __('board.move_forbidden', [
            'from' => $task->status?->value ?? $task->orgStatus?->key ?? '?',
            'to' => $target->value,
        ]);
    }

    private function findTask(Project $project, array $args): Task
    {
        $ref = $args['task'] ?? null;
        if ($ref === null || $ref === '') {
            throw new McpToolException('task (Name oder id) ist erforderlich.');
        }

        $task = is_numeric($ref)
            ? $project->tasks()->whereKey((int) $ref)->first()
            : $project->tasks()->where('name', $ref)->first();

        if (! $task) {
            throw new McpToolException("Unbekannter Task: \"{$ref}\" (nicht in diesem Projekt).");
        }

        return $task;
    }

    /**
     * Resolve a gate list (names and/or ids) to project-scoped task ids.
     *
     * @param  array<int, int|string>  $gate
     * @return array<int, int>
     */
    private function resolveGate(Project $project, array $gate, ?int $excludeTaskId = null): array
    {
        $ids = [];

        foreach ($gate as $ref) {
            $match = is_numeric($ref)
                ? $project->tasks()->whereKey((int) $ref)->first()
                : $project->tasks()->where('name', $ref)->first();

            if (! $match) {
                throw new McpToolException("Unbekannte Gate-Referenz: \"{$ref}\" (nicht in diesem Projekt).");
            }

            if ($excludeTaskId !== null && $match->id === $excludeTaskId) {
                throw new McpToolException('Ein Task kann sich nicht selbst als Gate haben.');
            }

            $ids[] = $match->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Decorate one task (board fields + relations) and return the resource array.
     *
     * @return array<string, mixed>
     */
    private function taskPayload(Project $project, Task $task): array
    {
        $decorated = $this->board->board($project)->firstWhere('id', $task->id) ?? $task;
        $decorated->load('phase', 'claimer', 'concern');

        return (new TaskResource($decorated))->resolve();
    }

    /**
     * Authorize an ability for the token user, mapping a denial to a
     * model-visible tool error rather than an HTTP 403.
     */
    private function authorize(string $ability, mixed $target, Project $project): void
    {
        if (Gate::forUser($this->tokenUser())->denies($ability, $target)) {
            throw new McpToolException('Kein Zugriff für diese Aktion (fehlende Berechtigung/Rolle).');
        }
    }

    private function tokenUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function intOrZero(mixed $value): int
    {
        return max(0, (int) $value);
    }

    /**
     * @param  array<mixed>|object  $data
     */
    private function json(array|object $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
