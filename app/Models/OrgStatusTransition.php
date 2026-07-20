<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An allowed status transition (from → to) for an organization. Table
 * `task_status_transitions`. Org scope is implied by the two status FKs.
 */
class OrgStatusTransition extends Model
{
    protected $table = 'task_status_transitions';

    protected $fillable = ['from_status_id', 'to_status_id'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(OrgStatus::class, 'from_status_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(OrgStatus::class, 'to_status_id');
    }
}
