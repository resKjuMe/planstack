<?php

namespace App\Models;

use App\Enums\StatusRole;
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
    use \App\Concerns\BroadcastsEntityChange;
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    /**
     * Diese Aufgabe wird als `task` gemeldet; der Client lädt sie partiell nach.
     *
     * @return array{entity: string, id: int, organization_id: int|null, project_id: int|null, project_alias: string|null}|null
     */
    public function entityChangeScope(): ?array
    {
        $project = $this->project;

        return [
            'entity' => 'task',
            'id' => $this->id,
            'organization_id' => $project?->organization_id,
            'project_id' => $project?->id,
            'project_alias' => $project?->alias,
        ];
    }

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
        'custom_fields',
        'pr_number',
        'pr_node_id',
        'pr_title',
        'pr_ci_status',
        'pr_ci_failed',
        'pr_ci_running',
        'pr_ci_success',
        'pr_ci_waiting',
        'pr_in_merge_queue',
        'pr_merge_queue_state',
        'pr_mergeable',
        'pr_unresolved_threads',
        'pr_review_decision',
        'pr_last_commit_at',
        'pr_status_synced_at',
        'fix_leased_by',
        'fix_lease_expires_at',
        'reviewed_by',
        'last_reviewed_at',
        'last_review_recommendation',
        'last_review_summary',
        'status',
        'status_id',
        'claimed_at',
        'merged_at',
    ];

    /**
     * Pending status key set via the `status` mutator, resolved to status_id in
     * the saving hook (once project_id — and thus the organisation — is known).
     * Declared properties, so they never become persisted attributes.
     */
    protected ?string $pendingStatusKey = null;

    protected bool $hasPendingStatus = false;

    protected function casts(): array
    {
        return [
            'criticality' => \App\Enums\Criticality::class,
            'effort_man_days' => 'decimal:1',
            'effort_story_points' => 'integer',
            'effort_tokens' => 'integer',
            'affected_files' => 'integer',
            'custom_fields' => 'array',
            'pr_ci_failed' => 'integer',
            'pr_ci_running' => 'integer',
            'pr_ci_success' => 'integer',
            'pr_ci_waiting' => 'integer',
            'pr_in_merge_queue' => 'boolean',
            'pr_unresolved_threads' => 'integer',
            'pr_last_commit_at' => 'datetime',
            'pr_status_synced_at' => 'datetime',
            'fix_leased_by' => 'integer',
            'fix_lease_expires_at' => 'datetime',
            'reviewed_by' => 'integer',
            'last_reviewed_at' => 'datetime',
            'last_review_recommendation' => \App\Enums\ReviewRecommendation::class,
            'claimed_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    /**
     * status_id is the sole stored status (the legacy ENUM column was dropped).
     * The `status` attribute is a convenience layer over it:
     *  - reading `$task->status` derives a canonical TaskStatus from the
     *    organisation status's key (null for a custom status);
     *  - writing `$task->status = …` (or `['status' => …]`, incl. factories) is
     *    resolved to the matching org status_id on save.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            $needsDefault = ! $task->exists && ($task->attributes['status_id'] ?? null) === null;
            if (! $task->hasPendingStatus && ! $needsDefault) {
                return; // nothing status-related to resolve on this save
            }

            $organizationId = $task->project_id
                ? Project::whereKey($task->project_id)->value('organization_id')
                : null;

            if ($task->hasPendingStatus) {
                $task->hasPendingStatus = false;
                if ($organizationId !== null && $task->pendingStatusKey !== null) {
                    $id = OrgStatus::query()
                        ->where('organization_id', $organizationId)
                        ->where('key', $task->pendingStatusKey)
                        ->value('id');
                    if ($id !== null) {
                        $task->attributes['status_id'] = $id;
                    }
                }
            }

            // New task still without a status → default to the PICKABLE-role status.
            if (! $task->exists && ($task->attributes['status_id'] ?? null) === null && $organizationId !== null) {
                $task->attributes['status_id'] = OrgStatus::query()
                    ->where('organization_id', $organizationId)
                    ->where('role', StatusRole::PICKABLE->value)
                    ->value('id');
            }
        });
    }

    /**
     * Read: the canonical TaskStatus derived from the org status's key (null when
     * the task sits in a custom status without a canonical equivalent).
     */
    public function getStatusAttribute(): ?TaskStatus
    {
        return $this->orgStatus ? TaskStatus::tryFrom($this->orgStatus->key) : null;
    }

    /**
     * Write: remember the desired status key (TaskStatus enum or its string);
     * resolved to status_id in the saving hook. UNKNOWN maps to PICKABLE (the
     * retired initial status). Never writes a `status` attribute/column.
     */
    public function setStatusAttribute($value): void
    {
        $key = $value instanceof TaskStatus ? $value->value : ($value === null ? null : (string) $value);
        if ($key === 'UNKNOWN') {
            $key = 'PICKABLE';
        }
        $this->pendingStatusKey = $key;
        $this->hasPendingStatus = true;
    }

    /**
     * The organization's configurable status this task sits in (status_id).
     */
    public function orgStatus(): BelongsTo
    {
        return $this->belongsTo(OrgStatus::class, 'status_id');
    }

    /**
     * The board display-status KEY. `status_id` is the authority: a task in a
     * real (non-waiting) org status shows THAT status' key — including custom
     * statuses like REVIEWBAR/APPROVED that have no canonical TaskStatus enum.
     * Only a *waiting* task is reduced to the gate-derived PICKABLE/BLOCKED
     * placeholder (x_display_status). This mirrors {@see BoardPresenter} and
     * prevents the placeholder from leaking out as "PICKABLE" for a task that
     * is neither pickable nor waiting.
     */
    public function displayStatusKey(): ?string
    {
        if ($this->orgStatus !== null && $this->orgStatus->kind !== 'waiting') {
            return $this->orgStatus->key;
        }

        return ($this->x_display_status ?? $this->status)?->value ?? $this->orgStatus?->key;
    }

    /**
     * The localized label matching {@see displayStatusKey()}.
     */
    public function displayStatusLabel(): ?string
    {
        if ($this->orgStatus !== null && $this->orgStatus->kind !== 'waiting') {
            return $this->orgStatus->label;
        }

        return ($this->x_display_status ?? $this->status)?->label() ?? $this->orgStatus?->label;
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
