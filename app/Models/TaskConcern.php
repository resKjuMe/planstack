<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskConcern extends Model
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }
    use \App\Concerns\BroadcastsEntityChange;

    /**
     * Ein Concern wird als sein Eltern-Task gemeldet: der Client lädt den Task neu
     * (der die Concern-Relation trägt) statt einen eigenen Concern-Typ zu kennen.
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
