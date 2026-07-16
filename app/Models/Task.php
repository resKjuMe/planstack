<?php

namespace App\Models;

use App\Enums\TaskStatus;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use Auditable;
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by_id',
        'claimed_by_id',
        'name',
        'summary',
        'description',
        'description_acceptance_criteria',
        'phase_id',
        'effort_man_days',
        'effort_story_points',
        'effort_tokens',
        'affected_files',
        'pr_number',
        'reviewed_by',
        'status',
        'claimed_at',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'effort_man_days' => 'decimal:1',
            'effort_story_points' => 'integer',
            'effort_tokens' => 'integer',
            'affected_files' => 'integer',
            'claimed_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    /**
     * Requirement rows in which this task is the dependent side.
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(TaskRequirement::class, 'task_id');
    }

    /**
     * Tasks this task depends on (its prerequisites).
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_requirements', 'task_id', 'parent_id')
            ->withTimestamps();
    }

    /**
     * Tasks that depend on this task.
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_requirements', 'parent_id', 'task_id')
            ->withTimestamps();
    }

    public function concern(): HasOne
    {
        return $this->hasOne(TaskConcern::class, 'task_id');
    }

    public function pullRequests(): HasMany
    {
        return $this->hasMany(TaskPullRequest::class);
    }
}
