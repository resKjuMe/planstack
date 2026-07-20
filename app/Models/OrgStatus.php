<?php

namespace App\Models;

use App\Enums\StatusRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single, per-organization configurable task status (table `task_statuses`).
 * Named OrgStatus to avoid clashing with the legacy App\Enums\TaskStatus enum
 * while both coexist during the migration.
 */
class OrgStatus extends Model
{
    protected $table = 'task_statuses';

    protected $fillable = [
        'organization_id', 'role', 'key', 'label', 'label_en', 'kind',
        'color_token', 'icon', 'position', 'is_column', 'default_expanded', 'wip_limit',
        'counts_as_done', 'counts_as_delivered', 'group_id', 'on_enter_effects',
    ];

    protected function casts(): array
    {
        return [
            'role' => StatusRole::class,
            'position' => 'integer',
            'is_column' => 'boolean',
            'default_expanded' => 'boolean',
            'wip_limit' => 'integer',
            'counts_as_done' => 'boolean',
            'counts_as_delivered' => 'boolean',
            'on_enter_effects' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OrgStatusGroup::class, 'group_id');
    }

    /**
     * Allowed target statuses when leaving this status.
     */
    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(OrgStatusTransition::class, 'from_status_id');
    }

    public function isDone(): bool
    {
        return $this->kind === 'done';
    }

    public function isException(): bool
    {
        return $this->kind === 'exception';
    }
}
