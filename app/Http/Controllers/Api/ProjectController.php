<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectRole;
use App\Http\Middleware\AttachPlanstackConfig;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\ProjectConfig;
use App\Support\ProjectOverviewPresenter;
use App\Support\TaskBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends ApiController
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * GET /api/projects — projects the token user can access.
     */
    public function index(Request $request): JsonResource
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
            ->withCount('tasks')
            ->with('owner')
            ->latest()
            ->get();

        return ProjectResource::collection($projects);
    }

    /**
     * GET /api/projects/overview — kompakte Aggregat-Übersicht für die Projektliste
     * (Zähler/SP/Segment-Buckets je Projekt per DB-Gruppierung). Der Client leitet
     * daraus die Karten ab (Kategorie, Styling, Balken). Deutlich leichter als das
     * Laden aller Tasks.
     */
    public function overview(Request $request, ProjectOverviewPresenter $overview): JsonResponse
    {
        return response()->json($overview->payload($request->user()));
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

        $project = new Project($data);
        $project->created_by_id = $request->user()->id;
        $project->organization_id = $request->user()->organization_id;
        $project->save();

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
        // Relationen für die abgeleiteten Unterseiten mitladen: prerequisites fürs
        // Summary (Phasen-Blocker), reviewer fürs Diagramm, pullRequests für die
        // Kalibrierung (Ist-Kennzahlen). Der geteilte React-Store leitet ALLE
        // Unterseiten aus dieser einen Antwort ab.
        $tasks->loadMissing(['phase', 'claimer', 'reviewer', 'concern', 'prerequisites.orgStatus', 'pullRequests']);
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
     *
     * Shape is governed by the per-project config (server-enforced token knobs):
     *  - board.scope:     next_only | pickable | all
     *  - board.format:    terse (text/plain) | lean (compact JSON) | full
     *  - board.aggregates:include totals + per-phase aggregates
     *  - board.diff_mode: etag ⇒ 304 Not Modified when the board is unchanged
     * Plus config_version and, on drift, a client_hints delta.
     */
    public function board(Request $request, Project $project): JsonResponse|Response
    {
        $this->authorize('view', $project);

        $cfg = $project->effectiveConfig();

        $project->load('phases');
        $tasks = $this->board->board($project);

        $pickable = $tasks
            ->filter(fn ($t) => $t->x_pickable)
            ->sortByDesc('x_unlocks')
            ->values();

        if ($cfg['board.scope'] === 'next_only') {
            $pickable = $pickable->take(1)->values();
        }

        // ETag over the pick-relevant state + config version + format, so a
        // change in any of them busts the cache.
        $etag = $this->boardEtag($project, $tasks, $cfg);
        $etagHeader = '"'.$etag.'"';

        if ($cfg['board.diff_mode'] === 'etag'
            && trim((string) $request->header('If-None-Match', ''), '"') === $etag) {
            return response('', 304)->header('ETag', $etagHeader);
        }

        if ($cfg['board.format'] === 'terse') {
            return response($this->terseBoard($project, $tasks, $pickable, $cfg, $request), 200)
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('ETag', $etagHeader);
        }

        $lean = $cfg['board.format'] === 'lean';
        $payload = ['config_version' => $project->config_version];

        if ($cfg['board.aggregates']) {
            $payload['project'] = [
                'id' => $project->id,
                'alias' => $project->alias,
                'name' => $project->name,
            ];
            $payload['totals'] = $this->totals($tasks, $pickable);
            $payload['phases'] = $this->phaseAggregates($project, $tasks);
        }

        $payload['pickable'] = $lean
            ? $pickable->map(fn ($t) => $this->leanEntry($t))->values()
            : TaskResource::collection($pickable);

        if ($cfg['board.scope'] === 'all') {
            $payload['tasks'] = $lean
                ? $tasks->map(fn ($t) => $this->leanEntry($t))->values()
                : TaskResource::collection($tasks->values());
        }

        $hints = $this->driftHints($request, $project, $cfg);
        if ($hints !== null) {
            $payload['client_hints'] = $hints;
        }

        return response()->json($payload)->header('ETag', $etagHeader);
    }

    /**
     * A compact pickable/task entry for board.format=lean (short keys, no nulls).
     *
     * @return array<string, mixed>
     */
    private function leanEntry(Task $task): array
    {
        return array_filter([
            'id' => $task->id,
            'name' => $task->name,
            'summary' => $task->summary,
            'unlocks' => $task->x_unlocks ?? 0,
            'sp' => (int) $task->effort_story_points,
            'gate' => ($task->x_gate ?? '—') === '—' ? null : $task->x_gate,
            'status' => $task->displayStatusKey(),
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Task>  $pickable
     * @return array<string, int>
     */
    private function totals(Collection $tasks, Collection $pickable): array
    {
        $totalSp = max(1, (int) $tasks->sum('effort_story_points'));
        $doneSp = (int) $tasks->filter(fn ($t) => $this->board->isDelivered($t))->sum('effort_story_points');

        return [
            'tasks' => $tasks->count(),
            'done' => $tasks->filter(fn ($t) => $this->board->isDelivered($t))->count(),
            'story_points' => (int) $tasks->sum('effort_story_points'),
            'done_story_points' => $doneSp,
            'pct' => (int) round($doneSp / $totalSp * 100),
            'pickable' => $pickable->count(),
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function phaseAggregates(Project $project, Collection $tasks): Collection
    {
        return $project->phases->map(function ($phase) use ($tasks) {
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
    }

    /**
     * The plain-text board for board.format=terse — one line per pickable task,
     * an optional totals header (aggregates), and a hint line on config drift.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Task>  $pickable
     * @param  array<string, string|bool|int>  $cfg
     */
    private function terseBoard(Project $project, Collection $tasks, Collection $pickable, array $cfg, Request $request): string
    {
        $lines = [];

        if ($cfg['board.aggregates']) {
            $t = $this->totals($tasks, $pickable);
            $lines[] = "# {$t['done']}/{$t['tasks']} tasks · {$t['pct']}% · pickable={$t['pickable']} · cfg=v{$project->config_version}";
        }

        foreach ($pickable as $task) {
            $gate = ($task->x_gate ?? '—');
            $lines[] = "{$task->name} unlocks={$task->x_unlocks} sp={$task->effort_story_points} gate={$gate} :: {$task->summary}";
        }

        if ($pickable->isEmpty()) {
            $lines[] = '# keine pickbaren Tasks';
        }

        $hints = $this->driftHints($request, $project, $cfg);
        if ($hints !== null) {
            $pairs = collect($hints)->map(fn ($v, $k) => $k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : $v))->implode(' ');
            $lines[] = "# hints: {$pairs}";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * The client-hint delta to include, or null when the client is already
     * up to date (⇒ no block, zero extra tokens). Included when the client's
     * known config version (header) differs from the current one and there are
     * non-default hints to convey.
     *
     * @param  array<string, string|bool|int>  $cfg
     * @return array<string, string|bool|int>|null
     */
    private function driftHints(Request $request, Project $project, array $cfg): ?array
    {
        $known = $request->header(AttachPlanstackConfig::CLIENT_VERSION_HEADER);
        $hints = ProjectConfig::clientHints($cfg);

        if ($hints === [] || (is_numeric($known) && (int) $known === $project->config_version)) {
            return null;
        }

        return $hints;
    }

    /**
     * A cheap fingerprint of the board's pick-relevant state. Changes whenever a
     * task's pickability/status/PR/unlocks change, or the config/format changes.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  array<string, string|bool|int>  $cfg
     */
    private function boardEtag(Project $project, Collection $tasks, array $cfg): string
    {
        $state = $tasks
            ->map(fn ($t) => "{$t->id}:{$t->status?->value}:{$t->pr_number}:{$t->claimed_by_id}:{$t->x_unlocks}")
            ->implode('|');

        return substr(hash('xxh128', $state.'#'.$project->config_version.'#'.$cfg['board.scope'].'#'.$cfg['board.format']), 0, 16);
    }
}
