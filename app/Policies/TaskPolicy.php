<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    /**
     * Claim (or release) a task. Any project member may claim an unclaimed task;
     * the current claimer (or owner/ADMIN) may release it.
     */
    public function claim(User $user, Task $task): bool
    {
        if (! $task->project->hasMember($user)) {
            return false;
        }

        if ($task->claimed_by_id === null) {
            return true;
        }

        return $task->claimed_by_id === $user->id
            || $task->project->isOwner($user)
            || $task->project->roleFor($user) === ProjectRole::ADMIN;
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->created_by_id === $user->id
            || $task->project->isOwner($user)
            || $task->project->roleFor($user) === ProjectRole::ADMIN;
    }
}
