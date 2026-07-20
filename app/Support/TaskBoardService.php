<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Central board logic: loads a project's tasks and attaches the computed
 * board attributes (pickable, gate, stacking, unlocks, progress, pr_url,
 * colour). Shared by the web status views and the API so the two never
 * drift apart.
 */
class TaskBoardService
{
    /**
     * Load the project's tasks with their board relations and attach the
     * computed x_* attributes. Also attaches x_unlocks.
     *
     * @return Collection<int, Task>
     */
    public function board(Project $project): Collection
    {
        $tasks = $this->decorate($project);
        $this->attachUnlocks($tasks);

        return $tasks;
    }

    /**
     * Load the project's tasks and attach computed board attributes
     * (everything except x_unlocks, which needs the full set — see board()).
     *
     * @return Collection<int, Task>
     */
    public function decorate(Project $project): Collection
    {
        $tasks = $project->tasks()
            ->with(['phase', 'claimer', 'orgStatus', 'prerequisites:id,name,status_id,pr_number', 'prerequisites.orgStatus'])
            ->orderBy('id')
            ->get();

        $totalSp = max(1, (int) $tasks->sum('effort_story_points'));
        $repo = $project->githubRepo();

        foreach ($tasks as $task) {
            $pre = $task->prerequisites;

            // A gate is satisfied once the prerequisite is "delivered": it has a
            // PR (an open PR is enough, so dependents may stack on it) or is
            // otherwise done (split-parent COMPLETED / MERGED). See isDelivered().
            $unmet = $pre->filter(fn ($p) => ! $this->isDelivered($p));

            // A task with its own PR (open or merged) is no longer pickable.
            $task->x_pickable = $task->claimed_by_id === null
                && $task->pr_number === null
                && ! in_array($task->status, [TaskStatus::MERGED, TaskStatus::COMPLETED, TaskStatus::CLAIMED, TaskStatus::CONCERNED, TaskStatus::IN_REVIEW], true)
                && $unmet->isEmpty();
            $task->x_stacking = $pre->count() === 1 ? $pre->first()->name : '—';
            $task->x_gate = $pre->pluck('name')->implode(', ') ?: '—';
            $task->x_unmet = $unmet->count();
            $task->x_percent = round($task->effort_story_points / $totalSp * 100, 1);
            $task->x_tokens = $this->formatTokens($task->effort_tokens);
            $task->x_display_status = $this->displayStatusFor($task);
            $task->x_class = $this->colorFor($task);
            $task->x_pr_url = $task->pr_number && $repo
                ? "https://github.com/{$repo}/pull/{$task->pr_number}"
                : null;
        }

        return $tasks;
    }

    /**
     * Attach, per task, how many follow-up PRs it directly unblocks — counting
     * only direct dependents for which this task is their *only* gate. Also
     * attaches x_dependents: the number of tasks that directly depend on it
     * (used to flag bottlenecks).
     *
     * @param  Collection<int, Task>  $tasks
     */
    public function attachUnlocks(Collection $tasks): void
    {
        $prereqCount = [];
        $dependents = [];

        foreach ($tasks as $task) {
            $prereqCount[$task->id] = $task->prerequisites->count();
            foreach ($task->prerequisites as $parent) {
                $dependents[$parent->id][] = $task->id;
            }
        }

        foreach ($tasks as $task) {
            $direct = collect($dependents[$task->id] ?? []);
            $task->x_dependents = $direct->count();
            $task->x_unlocks = $direct
                ->filter(fn ($childId) => ($prereqCount[$childId] ?? 0) === 1)
                ->count();
        }
    }

    /**
     * The effective status shown on the board. Explicit lifecycle states are
     * kept as-is; a waiting task (UNKNOWN/PICKABLE/BLOCKED) is derived from the
     * gate: any unmet prerequisite → BLOCKED, otherwise PICKABLE.
     *
     * The raw UNKNOWN ("ausstehend") state is intentionally never surfaced: a
     * waiting task always resolves to BLOCKED or PICKABLE, so the board needs no
     * UNKNOWN column. (UNKNOWN remains the stored default until the per-org
     * status seed replaces it with PICKABLE as the initial status.)
     */
    public function displayStatusFor(Task $task): TaskStatus
    {
        // A task in a custom (org-defined) status has no canonical ENUM value.
        // The board reads status_id (see BoardPresenter); this enum-based path
        // only feeds the legacy diagram/summary views, so a neutral PICKABLE
        // placeholder is enough to avoid a null dereference there.
        if ($task->status === null) {
            return TaskStatus::PICKABLE;
        }

        if ($task->status->isExplicit()) {
            return $task->status;
        }

        if ($task->x_unmet >= 1) {
            return TaskStatus::BLOCKED;
        }

        return TaskStatus::PICKABLE;
    }

    /**
     * The mermaid colour class for a task, derived from its display status.
     */
    public function colorFor(Task $task): string
    {
        return match ($task->x_display_status ?? $this->displayStatusFor($task)) {
            TaskStatus::CONCERNED => 'concern',
            TaskStatus::COMPLETED, TaskStatus::MERGED => 'blau',
            TaskStatus::CLAIMED => 'hellblau',
            TaskStatus::ANALYZING => 'blau',
            TaskStatus::IN_PROGRESS => 'dunkelblau',
            TaskStatus::IN_REVIEW => 'lila',
            TaskStatus::BLOCKED => 'rot',
            TaskStatus::PICKABLE => 'gruen',
            default => 'blau',
        };
    }

    /**
     * Whether a task is fully closed — COMPLETED or MERGED. Drives the diagram's
     * done marker (✓, muted node, "hide done" toggle) only. Progress KPIs and
     * gate satisfaction use isDelivered(), which also counts an open PR.
     */
    /**
     * Whether a status counts as "done". Config-authoritative: when passed a
     * Task, the organisation's status flag (counts_as_done on the task's
     * status_id) decides; a raw TaskStatus (e.g. a prerequisite loaded without
     * status_id) or null falls back to the canonical COMPLETED/MERGED enum.
     */
    public function isDone(Task|TaskStatus|null $status): bool
    {
        if ($status instanceof Task) {
            $orgStatus = $status->orgStatus;
            if ($orgStatus !== null) {
                return (bool) $orgStatus->counts_as_done;
            }

            return $status->status?->isDone() ?? false;
        }

        return $status?->isDone() ?? false;
    }

    /**
     * Whether a task counts as delivered: it has a PR (an open PR already counts)
     * or its status is "done". Used both for progress KPIs and for satisfying
     * dependents' gates — an open PR keeps the task in progress yet already
     * contributes to progress and unblocks its dependents.
     */
    public function isDelivered(Task $task): bool
    {
        return $task->pr_number !== null || $this->isDone($task);
    }

    public function formatTokens(?int $tokens): string
    {
        if ($tokens === null) {
            return '—';
        }

        return $tokens >= 1_000_000
            ? number_format($tokens / 1_000_000, 1, ',', '').'M'
            : round($tokens / 1_000).'k';
    }
}
