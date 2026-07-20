<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use Auditable, \App\Concerns\OrganizationAuditMetadata {
        \App\Concerns\OrganizationAuditMetadata::getAuditMetadata insteadof Auditable;
    }
    /** @use HasFactory<\Database\Factories\TaskChecklistItemFactory> */
    use HasFactory;

    /** Rollen, die abgehakt werden können (der Rest ist read-only). */
    public const CHECKABLE_ROLES = ['item', 'done_when', 'step', 'expectation'];

    protected $fillable = [
        'task_id',
        'kind',
        'role',
        'position',
        'text',
        'checked',
        'checked_by_id',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked' => 'boolean',
            'checked_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    /**
     * Ob dieses Item abgehakt werden darf (Rolle in der Whitelist).
     */
    public function isCheckable(): bool
    {
        return in_array($this->role, self::CHECKABLE_ROLES, true);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }
}
