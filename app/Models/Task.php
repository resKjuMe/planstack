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
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }
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
        'description_target_actual',
        'description_test_cases',
        'criticality',
        'phase_id',
        'effort_man_days',
        'effort_story_points',
        'effort_tokens',
        'affected_files',
        'pr_number',
        'reviewed_by',
        'last_reviewed_at',
        'last_review_recommendation',
        'last_review_summary',
        'status',
        'status_id',
        'claimed_at',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'criticality' => \App\Enums\Criticality::class,
            'effort_man_days' => 'decimal:1',
            'effort_story_points' => 'integer',
            'effort_tokens' => 'integer',
            'affected_files' => 'integer',
            'reviewed_by' => 'integer',
            'last_reviewed_at' => 'datetime',
            'last_review_recommendation' => \App\Enums\ReviewRecommendation::class,
            'claimed_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    /**
     * Dual-write during the status migration (Phase 2): the ENUM `status` stays
     * the authority, but every save mirrors it into `status_id` (FK to the
     * organization's configurable status). UNKNOWN maps to the PICKABLE status
     * (no UNKNOWN in the seeded set). Reads still use `status`; Phase 4 flips the
     * authority. Removed once the ENUM is dropped (Phase 7).
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->status === null) {
                return;
            }
            // Only resolve when the enum changed or the mirror is still empty.
            if (! $task->isDirty('status') && $task->status_id !== null) {
                return;
            }

            $organizationId = Project::whereKey($task->project_id)->value('organization_id');
            if ($organizationId === null) {
                return; // legacy/unassigned project — leave mirror untouched
            }

            $key = $task->status === TaskStatus::UNKNOWN ? 'PICKABLE' : $task->status->value;
            $statusId = OrgStatus::query()
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->value('id');

            if ($statusId !== null) {
                $task->status_id = $statusId;
            }
        });
    }

    /**
     * The organization's configurable status this task currently sits in (mirror
     * of the ENUM `status` during the migration).
     */
    public function orgStatus(): BelongsTo
    {
        return $this->belongsTo(OrgStatus::class, 'status_id');
    }

    /**
     * Route-model binding by id *or* name: a numeric segment resolves by primary
     * key, anything else by the task's `name` (e.g. "C27"). Scoped bindings keep
     * this constrained to the parent project, so names only need to be unique per
     * project. Lets clients address a task by its short handle without a separate
     * name→id lookup (e.g. POST /projects/{project}/tasks/C27/claim).
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return $query->where($field, $value);
        }

        return is_numeric($value)
            ? $query->where($this->getKeyName(), $value)
            : $query->where('name', $value);
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    /**
     * Abhakbare Checklisten-Items (Akzeptanzkriterien und Testschritte), geordnet
     * nach Position. Nach `kind` ('acceptance' | 'test') im View gefiltert.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    public function pullRequests(): HasMany
    {
        return $this->hasMany(TaskPullRequest::class);
    }
}
