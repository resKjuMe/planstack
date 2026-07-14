<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->isOwner($user) || $project->roleFor($user) === ProjectRole::ADMIN;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->isOwner($user);
    }

    /**
     * Manage member assignments and roles.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $project->isOwner($user) || $project->roleFor($user) === ProjectRole::ADMIN;
    }

    /**
     * Create tasks/phases within the project.
     */
    public function contribute(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }
}
