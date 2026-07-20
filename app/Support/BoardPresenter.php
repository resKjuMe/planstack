<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Serialises a project's board into the plain-array shape the React board
 * consumes. Kept separate from TaskBoardService (which computes the x_*
 * attributes) so the wire format lives in one place and both the initial
 * page render and the drag-and-drop move endpoint emit identical task objects.
 */
class BoardPresenter
{
    public function __construct(private readonly TaskBoardService $board) {}

    /**
     * Full board payload: decorated tasks + the workflow config + view context
     * (current user, assignee list, i18n strings, endpoints).
     *
     * @return array<string, mixed>
     */
    public function payload(Project $project): array
    {
        $tasks = $this->board->board($project);
        $tasks->loadMissing('concern:id,task_id,summary,description_blocker');

        $userId = auth()->id();

        return [
            'projectId' => $project->id,
            'currentUserId' => $userId,
            'tasks' => $tasks->map(fn (Task $t) => $this->task($t, $project, $userId))->values()->all(),
            'assignees' => $this->assignees($tasks),
            'workflow' => BoardWorkflow::toArray(),
            'endpoints' => [
                // {task} is replaced client-side with the task id.
                'move' => route('projects.tasks.board-move', [$project, '__TASK__']),
                'claim' => route('projects.tasks.claim', [$project, '__TASK__']),
            ],
            'csrf' => csrf_token(),
            'strings' => $this->strings(),
        ];
    }

    /**
     * One decorated task in wire format. Requires the x_* attributes from
     * TaskBoardService (call decorate()/board() first).
     *
     * @return array<string, mixed>
     */
    public function task(Task $task, Project $project, ?int $currentUserId): array
    {
        $display = $task->x_display_status ?? $this->board->displayStatusFor($task);

        return [
            'id' => $task->id,
            'name' => $task->name,
            'summary' => $task->summary,
            // Raw stored status vs. the derived board status. The board groups by
            // displayStatus (consistent with diagram/summary); the drop endpoint
            // validates transitions against it too.
            'status' => $task->status->value,
            'displayStatus' => $display->value,
            'claimerId' => $task->claimed_by_id,
            'claimerName' => $task->claimer?->name,
            'storyPoints' => (int) $task->effort_story_points,
            'prNumber' => $task->pr_number,
            'prUrl' => $task->x_pr_url,
            'mergedAt' => $task->merged_at?->toIso8601String(),
            'mergedAtHuman' => $task->merged_at?->locale(app()->getLocale())->diffForHumans(),
            // Exception badges. Because BLOCKED/CONCERNED are exclusive statuses
            // (no preserved "last regular status"), such a task lives ONLY in the
            // exception lane — see the note in BoardWorkflow / the React board.
            'isBlocked' => $display === TaskStatus::BLOCKED,
            'isConcerned' => $display === TaskStatus::CONCERNED,
            'concernSummary' => $task->concern?->summary ?: $task->concern?->description_blocker,
            'url' => route('projects.tasks.show', [$project, $task]),
            // The existing claim/release buttons stay as an alternative to DnD.
            'canClaim' => $currentUserId !== null && auth()->user()?->can('claim', $task),
            'isClaimed' => $task->claimed_by_id !== null,
        ];
    }

    /**
     * Distinct claimers present on the board (for the assignee filter dropdown).
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array{id: int, name: string}>
     */
    private function assignees(Collection $tasks): array
    {
        return $tasks
            ->filter(fn (Task $t) => $t->claimer !== null)
            ->map(fn (Task $t) => ['id' => $t->claimed_by_id, 'name' => $t->claimer->name])
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Board UI strings for the client (i18n stays server-side; React reads them
     * from here rather than shipping a second translation layer).
     *
     * @return array<string, string>
     */
    private function strings(): array
    {
        return collect(array_keys(__('board')))
            ->mapWithKeys(fn (string $k) => [$k => __("board.$k")])
            ->all();
    }
}
