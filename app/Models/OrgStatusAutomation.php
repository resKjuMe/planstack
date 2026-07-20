<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wired action's target status + field side effects for an organization. Table
 * `task_status_automations`. `action` is one of the fixed action keywords
 * (claim, release, analyze, in_progress, in_review, done_with_pr,
 * done_without_pr, merge, split_parent, concern, resolve_claimed,
 * resolve_unclaimed). `effects` is a list of {field, value, only_if_empty?}
 * where value may be a token (@actor, @now, @clear). Consumed from Phase 4.
 */
class OrgStatusAutomation extends Model
{
    protected $table = 'task_status_automations';

    protected $fillable = ['organization_id', 'action', 'target_status_id', 'effects'];

    protected function casts(): array
    {
        return ['effects' => 'array'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function targetStatus(): BelongsTo
    {
        return $this->belongsTo(OrgStatus::class, 'target_status_id');
    }
}
