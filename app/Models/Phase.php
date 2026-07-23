<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phase extends Model
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }
    use \App\Concerns\BroadcastsEntityChange;
    /** @use HasFactory<\Database\Factories\PhaseFactory> */
    use HasFactory;

    /**
     * @return array{entity: string, id: int, organization_id: int|null, project_id: int|null, project_alias: string|null}|null
     */
    public function entityChangeScope(): ?array
    {
        $project = $this->project;

        return [
            'entity' => 'phase',
            'id' => $this->id,
            'organization_id' => $project?->organization_id,
            'project_id' => $project?->id,
            'project_alias' => $project?->alias,
        ];
    }

    protected $fillable = [
        'project_id',
        'name',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
