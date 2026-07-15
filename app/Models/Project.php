<?php

namespace App\Models;

use App\Enums\ProjectRole;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use Auditable;
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'created_by_id',
        'alias',
        'name',
        'description',
        'github_repo',
        'skill_description',
    ];

    /**
     * The effective "owner/repo" for PR linking and PR-status sync: the value
     * stored on the project, falling back to the per-alias default from
     * config/planstack.php. Null when neither is configured.
     */
    public function githubRepo(): ?string
    {
        return $this->github_repo ?: null;
    }

    /**
     * Route-model binding uses the unique alias instead of the id.
     */
    public function getRouteKeyName(): string
    {
        return 'alias';
    }

    /**
     * The user who created the project (project owner).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Teams assigned to this project (n:m). Access is granted through teams.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'projects_to_teams', 'project_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Role rows (users_to_projects): the role of a user within this project.
     * This no longer grants access — it only records the role distribution.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_to_projects', 'project_id', 'user_id')
            ->using(ProjectMembership::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /**
     * Raw role rows for this project.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('position');
    }

    /**
     * The effective role of a user in this project, or null if they have no
     * access. Access comes from team assignment; the role comes from
     * users_to_projects and defaults to WORKER when no explicit row exists.
     */
    public function roleFor(User $user): ?ProjectRole
    {
        if (! $this->hasMember($user)) {
            return null;
        }

        // value('role') already returns the casted ProjectRole (or null).
        return $this->memberships()->where('user_id', $user->id)->value('role')
            ?? ProjectRole::WORKER;
    }

    public function isOwner(User $user): bool
    {
        return $this->created_by_id === $user->id;
    }

    /**
     * A user has access if they are the owner or a member of any assigned team.
     */
    public function hasMember(User $user): bool
    {
        return $this->isOwner($user)
            || $this->teams()->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->exists();
    }

    /**
     * All users with access to this project (owner + members of assigned teams).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function accessUsers(): \Illuminate\Support\Collection
    {
        $users = $this->teams->flatMap->members->unique('id')->values();

        if ($this->owner && ! $users->contains('id', $this->owner->id)) {
            $users->prepend($this->owner);
        }

        return $users;
    }
}
