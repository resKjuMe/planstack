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
        return $this->ownsOrganization($user, $project) || $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->ownsOrganization($user, $project)
            || $project->isOwner($user)
            || $project->roleFor($user) === ProjectRole::ADMIN;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->ownsOrganization($user, $project) || $project->isOwner($user);
    }

    /**
     * Manage member assignments and roles.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $this->ownsOrganization($user, $project)
            || $project->isOwner($user)
            || $project->roleFor($user) === ProjectRole::ADMIN;
    }

    /**
     * Create tasks/phases within the project.
     */
    public function contribute(User $user, Project $project): bool
    {
        return $this->ownsOrganization($user, $project) || $project->hasMember($user);
    }

    /**
     * Der Gründer der Organisation hat volle Rechte auf alle Projekte seiner
     * Organisation.
     */
    private function ownsOrganization(User $user, Project $project): bool
    {
        return $user->organization_id !== null
            && $project->organization_id === $user->organization_id
            && $user->organization?->isOwner($user) === true;
    }
}
