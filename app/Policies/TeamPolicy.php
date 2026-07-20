<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $this->ownsOrganization($user, $team) || $team->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * The team creator or the organization owner may rename the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->ownsOrganization($user, $team) || $team->isOwner($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->ownsOrganization($user, $team) || $team->isOwner($user);
    }

    /**
     * The team creator or the organization owner may add/remove team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $this->ownsOrganization($user, $team) || $team->isOwner($user);
    }

    /**
     * Der Gründer der Organisation hat volle Rechte auf alle Teams seiner
     * Organisation – auch ohne selbst Mitglied zu sein.
     */
    private function ownsOrganization(User $user, Team $team): bool
    {
        return $user->organization_id !== null
            && $team->organization_id === $user->organization_id
            && $user->organization?->isOwner($user) === true;
    }
}
