<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRequirement extends Model
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }
    protected $fillable = [
        'task_id',
        'parent_id',
    ];

    /**
     * The dependent task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * The prerequisite task.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }
}
