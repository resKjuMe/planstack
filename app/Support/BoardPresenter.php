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
        $maps = $this->orgStatusMaps($project);

        return [
            'projectId' => $project->id,
            'currentUserId' => $userId,
            'tasks' => $tasks->map(fn (Task $t) => $this->task($t, $project, $userId, $maps))->values()->all(),
            'assignees' => $this->assignees($tasks),
            // Workflow comes from the organization's configurable statuses; for a
            // default-seeded org this equals the former static definition.
            'workflow' => OrgBoardWorkflow::forOrganization($project->organization)->toArray(),
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
     * TaskBoardService (call decorate()/board() first). The board status is keyed
     * off the task's status_id (the authority), with gate derivation for waiting
     * statuses; the legacy ENUM is only a fallback for not-yet-backfilled rows.
     *
     * @param  array{byId: Collection<int, \App\Models\OrgStatus>, roleKey: array<string, string>}|null  $maps
     * @return array<string, mixed>
     */
    public function task(Task $task, Project $project, ?int $currentUserId, ?array $maps = null): array
    {
        $maps ??= $this->orgStatusMaps($project);
        $displayKey = $this->displayStatusKey($task, $maps);

        $blockedKey = $maps['roleKey']['BLOCKED'] ?? 'BLOCKED';
        $concernedKey = $maps['roleKey']['CONCERNED'] ?? 'CONCERNED';

        return [
            'id' => $task->id,
            'name' => $task->name,
            'summary' => $task->summary,
            // Raw stored status (nullable once a task sits in a custom status) vs.
            // the derived board status key the board groups by.
            'status' => $task->status?->value,
            'displayStatus' => $displayKey,
            'claimerId' => $task->claimed_by_id,
            'claimerName' => $task->claimer?->name,
            'storyPoints' => (int) $task->effort_story_points,
            'prNumber' => $task->pr_number,
            'prUrl' => $task->x_pr_url,
            'mergedAt' => $task->merged_at?->toIso8601String(),
            'mergedAtHuman' => $task->merged_at?->locale(app()->getLocale())->diffForHumans(),
            // Exception badges. Because exception statuses are exclusive (no
            // preserved "last regular status"), such a task lives ONLY in the
            // exception lane — see the note in the React board.
            'isBlocked' => $displayKey === $blockedKey,
            'isConcerned' => $displayKey === $concernedKey,
            'concernSummary' => $task->concern?->summary ?: $task->concern?->description_blocker,
            'url' => route('projects.tasks.show', [$project, $task]),
            // The existing claim/release buttons stay as an alternative to DnD.
            'canClaim' => $currentUserId !== null && auth()->user()?->can('claim', $task),
            'isClaimed' => $task->claimed_by_id !== null,
        ];
    }

    /**
     * Public accessor for a task's current board status key (used by the
     * drag-and-drop move endpoint to validate the transition source).
     */
    public function displayKeyFor(Task $task, Project $project): string
    {
        return $this->displayStatusKey($task, $this->orgStatusMaps($project));
    }

    /**
     * The board status key for a task, from its status_id (authority). Waiting
     * statuses are derived from the gate: unmet prerequisite → BLOCKED role,
     * else PICKABLE role. Falls back to the enum-derived key when status_id is
     * not (yet) set.
     *
     * @param  array{byId: Collection<int, \App\Models\OrgStatus>, roleKey: array<string, string>}  $maps
     */
    private function displayStatusKey(Task $task, array $maps): string
    {
        $status = $task->status_id ? $maps['byId']->get($task->status_id) : null;

        if ($status === null) {
            $enum = $task->x_display_status ?? $this->board->displayStatusFor($task);

            return $enum->value;
        }

        if ($status->kind === 'waiting') {
            return ($task->x_unmet ?? 0) >= 1
                ? ($maps['roleKey']['BLOCKED'] ?? 'BLOCKED')
                : ($maps['roleKey']['PICKABLE'] ?? 'PICKABLE');
        }

        return $status->key;
    }

    /**
     * Preloaded lookups for an organization's statuses: by id, and role → key.
     *
     * @return array{byId: Collection<int, \App\Models\OrgStatus>, roleKey: array<string, string>}
     */
    private function orgStatusMaps(Project $project): array
    {
        $statuses = $project->organization->statuses()->get();

        return [
            'byId' => $statuses->keyBy('id'),
            'roleKey' => $statuses->whereNotNull('role')
                ->mapWithKeys(fn ($s) => [$s->role->value => $s->key])
                ->all(),
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
