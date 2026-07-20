<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A collapse group (e.g. "In Arbeit") that folds consecutive collapsed columns
 * on the board into one bar. Table `task_status_groups`.
 */
class OrgStatusGroup extends Model
{
    protected $table = 'task_status_groups';

    protected $fillable = ['organization_id', 'key', 'label', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(OrgStatus::class, 'group_id');
    }
}
