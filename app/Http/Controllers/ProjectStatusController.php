<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskBoardService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectStatusController extends Controller
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * View 1 — the dependency graph. The heavy lifting (mermaid source, layout,
     * highlight interaction, "hide done" toggle) happens client-side in
     * diagram.js; here we only assemble the structured graph model it renders.
     */
    public function diagram(Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->board->decorate($project);
        $tasks->load('concern:id,task_id,summary,description_blocker');
        $this->board->attachUnlocks($tasks); // x_unlocks / x_dependents over the full set

        return view('status.diagram', [
            'project' => $project,
            'active' => 'diagram',
            'phases' => $this->phaseHeader($project, $tasks),
            // The full graph incl. merged nodes; the "Erledigte ausblenden"
            // toggle hides done (COMPLETED/MERGED) client-side on demand.
            'graph' => $this->buildGraph($project, $tasks),
        ]);
    }

    /**
     * View 2 — the PR sequence table.
     */
    public function prSequence(Project $project): View
    {
        $this->authorize('view', $project);

        $all = $this->board->decorate($project);
        $all->load('concern:id,task_id,summary,description_blocker');
        $this->board->attachUnlocks($all); // x_dependents / x_unlocks over the full set

        // Merged PRs drop off the sequence; only outstanding work is listed.
        $tasks = $all->reject(fn ($t) => $t->status === TaskStatus::MERGED)->values();

        // Per task: keep the board sequence number (position in the full,
        // non-merged sequence) and turn the gate into a readable list with
        // each prerequisite's state.
        foreach ($tasks as $i => $task) {
            $task->x_seq = $i + 1;

            $items = $task->prerequisites->map(fn ($p) => [
                'name' => $p->name,
                'met' => $this->board->isDelivered($p),
            ])->values();

            $task->x_dep_items = $items;
            $task->x_dep_open = $items->where('met', false)->count();
            $task->x_dep_met = $items->where('met', true)->count();
        }

        // COMPLETED work is collapsed into its own block; the rest is the list.
        $completed = $tasks->filter(fn ($t) => $t->x_display_status === TaskStatus::COMPLETED)->values();
        $open = $tasks->reject(fn ($t) => $t->x_display_status === TaskStatus::COMPLETED)->values();

        $count = fn (TaskStatus $s) => $open->filter(fn ($t) => $t->x_display_status === $s)->count();

        return view('status.pr-sequence', [
            'project' => $project,
            'active' => 'pr-sequence',
            'tasks' => $open,
            'completed' => $completed,
            'counts' => [
                'all' => $open->count(),
                'pickable' => $count(TaskStatus::PICKABLE),
                'blocked' => $count(TaskStatus::BLOCKED),
                'concerned' => $count(TaskStatus::CONCERNED),
                'claimed' => $count(TaskStatus::CLAIMED),
            ],
        ]);
    }

    /**
     * View 3 — KPI tiles, per-phase progress and pickable-PR cards.
     */
    public function summary(Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->board->decorate($project);
        $byId = $tasks->keyBy('id');
        $this->board->attachUnlocks($tasks);

        return view('status.summary', [
            'project' => $project,
            'active' => 'summary',
            'kpis' => $this->kpis($tasks),
            'rows' => $this->phaseRows($project, $tasks, $byId),
            'pickable' => $tasks->filter(fn ($t) => $t->x_pickable)->sortByDesc('x_unlocks')->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function kpis(Collection $tasks): array
    {
        $total = $tasks->count();
        $done = $tasks->filter(fn ($t) => $this->board->isDelivered($t));
        $remaining = $tasks->filter(fn ($t) => ! $this->board->isDelivered($t));

        $totalSp = (int) $tasks->sum('effort_story_points');
        $doneSp = (int) $done->sum('effort_story_points');
        $totalFiles = (int) $tasks->sum('affected_files');
        $doneFiles = (int) $done->sum('affected_files');
        $totalTokens = (int) $tasks->sum('effort_tokens');
        $doneTokens = (int) $done->sum('effort_tokens');

        // Velocity + last merge require real merge timestamps.
        $merged = $tasks
            ->filter(fn ($t) => $t->status === TaskStatus::MERGED && $t->merged_at !== null)
            ->sortBy('merged_at')
            ->values();

        $velocity = null;
        $lastMerge = null;

        if ($merged->isNotEmpty()) {
            $last = $merged->last();
            $lastMerge = [
                'when' => $last->merged_at->locale('de')->diffForHumans(),
                'pr' => $last->pr_number ? '#'.$last->pr_number : $last->name,
            ];

            $weeks = max(1 / 7, $merged->first()->merged_at->floatDiffInDays(now()) / 7);
            $rate = $merged->sum('effort_story_points') / $weeks;

            if ($rate > 0) {
                $eta = now()->addWeeks((int) ceil($remaining->sum('effort_story_points') / $rate));
                $velocity = ['rate' => round($rate, 1), 'eta' => 'KW '.$eta->isoWeek().'/'.$eta->isoWeekYear()];
            }
        }

        return [
            'progress' => [
                'pct' => $total ? (int) round($done->count() / $total * 100) : 0,
                'done' => $done->count(),
                'total' => $total,
            ],
            'storyPoints' => [
                'pct' => $totalSp ? (int) round($doneSp / $totalSp * 100) : 0,
                'done' => $doneSp,
                'total' => $totalSp,
            ],
            'files' => [
                'pct' => $totalFiles ? (int) round($doneFiles / $totalFiles * 100) : 0,
                'done' => $doneFiles,
                'total' => $totalFiles,
            ],
            'tokens' => [
                'pct' => $totalTokens ? (int) round($doneTokens / $totalTokens * 100) : 0,
                'done' => $this->board->formatTokens($doneTokens),
                'total' => $this->board->formatTokens($totalTokens),
            ],
            'velocity' => $velocity,
            'lastMerge' => $lastMerge,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function phaseRows(Project $project, Collection $tasks, Collection $byId): array
    {
        $rows = [];

        foreach ($project->phases as $phase) {
            $pt = $tasks->where('phase_id', $phase->id);
            $sp = max(1, (int) $pt->sum('effort_story_points'));
            $remaining = $pt->filter(fn ($t) => ! $this->board->isDelivered($t));

            // Phases that hold unfinished prerequisites of this phase's tasks.
            $blockers = $this->phaseBlockers($phase, $pt, $byId);

            // One entry per status present in this phase: badge + bar segment (SP-based).
            $statuses = [];
            foreach ($this->statusOrder() as $status) {
                $inStatus = $pt->filter(fn ($t) => $t->x_display_status === $status);
                if ($inStatus->isEmpty()) {
                    continue;
                }
                $statuses[] = [
                    'label' => $status->label(),
                    'count' => $inStatus->count(),
                    'badge' => $this->statusBadgeClasses($status),
                    'bar' => $this->statusBarClasses($status),
                    'width' => round((int) $inStatus->sum('effort_story_points') / $sp * 100, 1),
                ];
            }

            $doneCount = $pt->count() - $remaining->count();

            $rows[] = [
                'phase' => $phase->name,
                'short' => $this->phaseShort($phase->name),
                'done' => $doneCount,
                'total' => $pt->count(),
                'pct' => $pt->count() ? (int) round($doneCount / $pt->count() * 100) : 0,
                'open_prs' => $remaining->values(),
                'statuses' => $statuses,
                'pt' => ['remaining' => (float) $remaining->sum('effort_man_days'), 'total' => (float) $pt->sum('effort_man_days')],
                'files' => ['remaining' => (int) $remaining->sum('affected_files'), 'total' => (int) $pt->sum('affected_files')],
                'tokens' => [
                    'remaining' => $this->board->formatTokens((int) $remaining->sum('effort_tokens') ?: null),
                    'total' => $this->board->formatTokens((int) $pt->sum('effort_tokens') ?: null),
                ],
                'blocked_by' => array_values($blockers),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, TaskStatus>
     */
    /**
     * Logical lifecycle order, most complete → most open.
     */
    private function statusOrder(): array
    {
        return [
            TaskStatus::MERGED,
            TaskStatus::COMPLETED,
            TaskStatus::IN_REVIEW,
            TaskStatus::IN_PROGRESS,
            TaskStatus::ANALYZING,
            TaskStatus::CLAIMED,
            TaskStatus::PICKABLE,
            TaskStatus::CONCERNED,
            TaskStatus::BLOCKED,
            TaskStatus::UNKNOWN,
        ];
    }

    private function statusBadgeClasses(TaskStatus $status): string
    {
        return $status->badgeClasses();
    }

    private function statusBarClasses(TaskStatus $status): string
    {
        return match ($status) {
            TaskStatus::UNKNOWN => 'bg-gray-300',
            TaskStatus::BLOCKED => 'bg-rose-400',
            TaskStatus::CONCERNED => 'bg-red-500',
            TaskStatus::PICKABLE => 'bg-indigo-400',
            TaskStatus::CLAIMED => 'bg-sky-400',
            TaskStatus::ANALYZING => 'bg-blue-400',
            TaskStatus::IN_PROGRESS => 'bg-blue-700',
            TaskStatus::IN_REVIEW => 'bg-purple-500',
            TaskStatus::COMPLETED => 'bg-green-500',
            TaskStatus::MERGED => 'bg-emerald-500',
        };
    }

    private function phaseShort(string $name): string
    {
        return explode(' ', $name)[0];
    }

    /**
     * Slim phase header: short name + %-complete (SP-based). The detailed
     * per-phase breakdown lives in the Summary tab.
     *
     * @return array<int, array<string, mixed>>
     */
    private function phaseHeader(Project $project, Collection $tasks): array
    {
        $out = [];

        foreach ($project->phases as $phase) {
            $pt = $tasks->where('phase_id', $phase->id);
            $sp = max(1, (int) $pt->sum('effort_story_points'));
            $done = (int) $pt->filter(fn ($t) => $this->board->isDelivered($t))->sum('effort_story_points');

            $out[] = [
                'id' => $phase->id,
                'short' => $this->phaseShort($phase->name),
                'name' => $phase->name,
                'pct' => (int) round($done / $sp * 100),
            ];
        }

        return $out;
    }

    /**
     * The dependency graph as a plain array (nodes + edges) for diagram.js.
     * Colour = display status; each edge carries whether its prerequisite is
     * already met (→ dashed/dimmed) or still open (→ solid). No mermaid string
     * is built here so the client can re-render for the "hide done" toggle and
     * wire up the highlight interaction.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function buildGraph(Project $project, Collection $tasks): array
    {
        // Stable, mermaid-safe node keys derived from the PR id. The 'n' prefix
        // guarantees a valid identifier even for ids that start with a digit
        // (e.g. "2S7"), which mermaid would otherwise reject.
        $keys = [];
        $used = [];
        foreach ($tasks as $task) {
            $safe = 'n'.(preg_replace('/[^A-Za-z0-9]/', '', $task->name) ?: 'N');
            while (isset($used[$safe])) {
                $safe .= 'x';
            }
            $used[$safe] = true;
            $keys[$task->id] = $safe;
        }

        // children[parentId][] = childId over the drawn set — for transitive
        // dependent counts (bottleneck detection).
        $children = [];
        foreach ($tasks as $task) {
            foreach ($task->prerequisites as $parent) {
                if (isset($keys[$parent->id])) {
                    $children[$parent->id][] = $task->id;
                }
            }
        }

        $transitive = [];
        foreach ($tasks as $task) {
            $transitive[$task->id] = $this->descendantCount($task->id, $children);
        }

        // A "bottleneck" is a node whose transitive dependent count is clearly
        // above the crowd — mean + one standard deviation — so the badge marks
        // the few real choke points, not merely everything above average (in a
        // deep chain that would be half the graph). Floor of 2 keeps it honest
        // in tiny/flat graphs.
        $n = count($transitive);
        $avg = $n ? array_sum($transitive) / $n : 0;
        $variance = $n ? array_sum(array_map(fn ($v) => ($v - $avg) ** 2, $transitive)) / $n : 0;
        $threshold = $avg + sqrt($variance);

        $nodes = [];
        foreach ($tasks as $task) {
            $ds = $task->x_display_status;
            $depTotal = $task->prerequisites->count();
            $depMet = $task->prerequisites->filter(fn ($p) => $this->board->isDelivered($p))->count();
            $reason = $task->concern?->summary ?: $task->concern?->description_blocker;
            $cat = $this->nodeCategory($ds);

            $nodes[] = [
                'key' => $keys[$task->id],
                'name' => $task->name,
                'summary' => $task->summary,
                'url' => route('projects.tasks.show', [$project, $task]),
                'cat' => $cat,
                // Phase membership drives the header-chip filter in diagram.js
                // (null = task without a phase, never matched by a chip).
                'phase' => $task->phase_id,
                'done' => $this->board->isDone($task->status),
                'sp' => (int) $task->effort_story_points,
                'pr' => $task->pr_number,
                'prUrl' => $task->x_pr_url,
                'statusLabel' => $ds->label(),
                'unlocks' => (int) ($task->x_unlocks ?? 0),
                'depOpen' => $depTotal - $depMet,
                'depTotal' => $depTotal,
                'depMet' => $depMet,
                'reason' => $cat === 'concern' ? $reason : null,
                'claimer' => in_array($cat, ['claimed', 'analyzing', 'inprogress', 'inreview'], true)
                    ? $task->claimer?->name
                    : null,
                'dependents' => $transitive[$task->id],
                // Direct dependents only — the bottleneck badge tooltip reports
                // "blockiert N PRs" (N = tasks that gate directly on this one).
                'directDependents' => (int) ($task->x_dependents ?? 0),
                // A fully-done node (COMPLETED/MERGED) no longer blocks anyone,
                // so it never carries the bottleneck badge — relevant now that
                // merged nodes are drawn.
                'bottleneck' => ! $this->board->isDone($task->status)
                    && $transitive[$task->id] > $threshold && $transitive[$task->id] >= 2,
            ];
        }

        $edges = [];
        foreach ($tasks as $task) {
            foreach ($task->prerequisites as $parent) {
                if (! isset($keys[$parent->id])) {
                    continue;
                }
                $edges[] = [
                    'from' => $keys[$parent->id],
                    'to' => $keys[$task->id],
                    'met' => $this->board->isDelivered($parent),
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Number of tasks transitively depending on $id (its descendants in the
     * dependent direction). Iterative + visited-set, so cycles can't loop.
     *
     * @param  array<int, array<int, int>>  $children
     */
    private function descendantCount(int $id, array $children): int
    {
        $seen = [];
        $stack = $children[$id] ?? [];

        while ($stack) {
            $cur = array_pop($stack);
            if (isset($seen[$cur])) {
                continue;
            }
            $seen[$cur] = true;
            foreach ($children[$cur] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $stack[] = $next;
                }
            }
        }

        return count($seen);
    }

    /**
     * Collapse the display status into the diagram's colour buckets. CLAIMED,
     * ANALYZING and IN_PROGRESS each get their own colour but stay part of the
     * "in Arbeit / beansprucht" family (all carry a claimer name — see
     * WORK_CATS in diagram.js).
     */
    private function nodeCategory(TaskStatus $ds): string
    {
        return match ($ds) {
            TaskStatus::COMPLETED, TaskStatus::MERGED => 'done',
            TaskStatus::CONCERNED => 'concern',
            TaskStatus::PICKABLE => 'pickable',
            TaskStatus::CLAIMED => 'claimed',
            TaskStatus::ANALYZING => 'analyzing',
            TaskStatus::IN_PROGRESS => 'inprogress',
            TaskStatus::IN_REVIEW => 'inreview',
            default => 'blocked',
        };
    }

    /**
     * Short names of the phases that hold unfinished prerequisites of this
     * phase's remaining tasks (i.e. the phases blocking it).
     *
     * @param  Collection<int, Task>  $phaseTasks
     * @param  Collection<int, Task>  $byId
     * @return array<int, string>
     */
    private function phaseBlockers($phase, Collection $phaseTasks, Collection $byId): array
    {
        $remaining = $phaseTasks->filter(fn ($t) => ! $this->board->isDelivered($t));

        $blockers = [];
        foreach ($remaining as $task) {
            foreach ($task->prerequisites as $parent) {
                if ($this->board->isDelivered($parent)) {
                    continue;
                }
                $parentTask = $byId->get($parent->id);
                if ($parentTask && $parentTask->phase_id !== $phase->id) {
                    $blockers[$parentTask->phase->position] = $this->phaseShort($parentTask->phase->name);
                }
            }
        }
        ksort($blockers);

        return array_values($blockers);
    }

}
