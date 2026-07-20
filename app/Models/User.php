<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The organization this user belongs to (at most one), or null.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Projects owned (created) by this user.
     */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by_id');
    }

    /**
     * Projects this user is assigned to (n:m via users_to_projects), incl. role.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'users_to_projects', 'user_id', 'project_id')
            ->using(ProjectMembership::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /**
     * Tasks created by this user.
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by_id');
    }

    /**
     * Tasks currently claimed by this user.
     */
    public function claimedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'claimed_by_id');
    }

    /**
     * Teams created by this user.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'created_by_id');
    }

    /**
     * Teams this user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'users_to_teams', 'user_id', 'team_id')
            ->withTimestamps();
    }
}
