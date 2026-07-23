<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskPullRequest extends Model
{
    use \App\Concerns\BroadcastsEntityChange;

    /**
     * Ein PR-Datensatz wird als sein Eltern-Task gemeldet (der Client lädt den Task
     * neu). So spiegeln sich PR-Änderungen in der Board-/Summary-Ansicht.
     *
     * @return array{entity: string, id: int, organization_id: int|null, project_id: int|null, project_alias: string|null}|null
     */
    public function entityChangeScope(): ?array
    {
        $project = $this->task?->project;

        return [
            'entity' => 'task',
            'id' => $this->task_id,
            'organization_id' => $project?->organization_id,
            'project_id' => $project?->id,
            'project_alias' => $project?->alias,
        ];
    }

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
