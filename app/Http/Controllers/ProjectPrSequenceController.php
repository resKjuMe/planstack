<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Support\StatusSegments;
use App\Support\TaskBoardService;
use Illuminate\View\View;

class ProjectPrSequenceController extends Controller
{
    public function __construct(
        private readonly TaskBoardService $board,
        private readonly StatusSegments $segments,
    ) {}

    /**
     * The PR sequence table.
     */
    public function __invoke(Project $project): View
    {
        $this->authorize('view', $project);

        $all = $this->board->decorate($project);
        $all->load('concern:id,task_id,summary,description_blocker');
        $this->board->attachUnlocks($all); // x_dependents / x_unlocks over the full set
        // Attach the org's configurable status (role/kind/label/color) per task so
        // custom statuses show through with their own identity instead of the enum.
        $this->segments->annotate($project, $all);

        // Merged PRs drop off the sequence; only outstanding work is listed.
        $tasks = $all->reject(fn ($t) => $t->x_status_role === TaskStatus::MERGED->value)->values();

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

        // "Done" work (kind=done, e.g. COMPLETED and custom done statuses) is
        // collapsed into its own block; the rest is the list.
        $completed = $tasks->filter(fn ($t) => $t->x_status_kind === 'done')->values();
        $open = $tasks->reject(fn ($t) => $t->x_status_kind === 'done')->values();

        // Filter-chip counts by action role (custom statuses fall outside these
        // buckets and show only under "all").
        $countRole = fn (TaskStatus $role) => $open->filter(fn ($t) => $t->x_status_role === $role->value)->count();

        return view('status.pr-sequence', [
            'project' => $project,
            'active' => 'pr-sequence',
            'tasks' => $open,
            'completed' => $completed,
            'counts' => [
                'all' => $open->count(),
                'pickable' => $countRole(TaskStatus::PICKABLE),
                'blocked' => $countRole(TaskStatus::BLOCKED),
                'concerned' => $countRole(TaskStatus::CONCERNED),
                'claimed' => $countRole(TaskStatus::CLAIMED),
            ],
        ]);
    }
}
