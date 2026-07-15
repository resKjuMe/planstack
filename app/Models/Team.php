<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use Auditable;
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'created_by_id',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_to_teams', 'team_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Projects this team is assigned to (n:m).
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'projects_to_teams', 'team_id', 'project_id')
            ->withTimestamps();
    }

    public function isOwner(User $user): bool
    {
        return $this->created_by_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->isOwner($user)
            || $this->members()->where('users.id', $user->id)->exists();
    }
}
