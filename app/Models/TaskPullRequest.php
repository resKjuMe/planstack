<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskPullRequest extends Model
{
    protected $fillable = [
        'task_id',
        'pull_request_id',
        'changed_files',
        'additions',
        'deletions',
        'commits',
        'comments',
        'review_comments',
        'opened_at',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'pull_request_id' => 'integer',
            'changed_files' => 'integer',
            'additions' => 'integer',
            'deletions' => 'integer',
            'commits' => 'integer',
            'comments' => 'integer',
            'review_comments' => 'integer',
            'opened_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
