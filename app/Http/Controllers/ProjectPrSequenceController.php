<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Support\TaskBoardService;
use Illuminate\View\View;

class ProjectPrSequenceController extends Controller
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * The PR sequence table.
     */
    public function __invoke(Project $project): View
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
}
