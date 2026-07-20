<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskBoardService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectDiagramController extends Controller
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * The dependency graph. The heavy lifting (mermaid source, layout,
     * highlight interaction, "hide done" toggle) happens client-side in
     * diagram.js; here we only assemble the structured graph model it renders.
     */
    public function __invoke(Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->board->decorate($project);
        $tasks->load('concern:id,task_id,summary,description_blocker', 'reviewer:id,name');
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
            // Fortschritt zählt nur erledigte/gemergte Tasks (COMPLETED/MERGED) —
            // deckungsgleich mit Projektübersicht und Summary; ein offener PR gilt
            // nicht als Fortschritt (Gate-/Kanten-Logik nutzt weiter isDelivered()).
            $done = (int) $pt->filter(fn ($t) => $this->board->isDone($t->status))->sum('effort_story_points');

            $out[] = [
                'id' => $phase->id,
                'short' => $this->phaseShort($phase->name),
                'name' => $phase->name,
                'pct' => (int) round($done / $sp * 100),
            ];
        }

        return $out;
    }

    private function phaseShort(string $name): string
    {
        return explode(' ', $name)[0];
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

        $userId = auth()->id();

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
                'files' => $task->affected_files,
                'pr' => $task->pr_number,
                'prUrl' => $task->x_pr_url,
                'statusLabel' => $ds->label(),
                'unlocks' => (int) ($task->x_unlocks ?? 0),
                'depOpen' => $depTotal - $depMet,
                'depTotal' => $depTotal,
                'depMet' => $depMet,
                'reason' => $cat === 'concern' ? $reason : null,
                // Reviewer name (resolved from the user FK) is shown in every
                // status once a reviewer is set.
                'reviewedBy' => $task->reviewer?->name,
                // Completed review result → corner badge (check / changes).
                'reviewRecommendation' => $task->last_review_recommendation?->value,
                // Whether the active viewer is that reviewer — drives the
                // colour split (own review vs. someone else reviewing).
                'reviewedByMe' => $task->reviewed_by !== null && $task->reviewed_by === $userId,
                // In review, no reviewer yet, and the viewer is not the task's
                // own assignee → offer a "claim" button to become the reviewer.
                'reviewClaimUrl' => ($cat === 'inreview'
                    && $task->reviewed_by === null
                    && $task->claimed_by_id !== $userId)
                    ? route('projects.tasks.review-claim', [$project, $task])
                    : null,
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
}
