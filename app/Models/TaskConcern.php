<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskConcern extends Model
{
    protected $fillable = [
        'task_id',
        'created_by_id',
        'summary',
        'description_context',
        'description_blocker',
        'description_misconception',
        'description_decisions',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
