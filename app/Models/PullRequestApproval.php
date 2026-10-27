<?php

namespace App\Models;

use App\Concerns\BroadcastsEntityChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PullRequestApproval extends Model
{
    use BroadcastsEntityChange;

    public const APPROVED = 'APPROVED';

    public const CHANGES_REQUESTED = 'CHANGES_REQUESTED';

    public $timestamps = false;

    protected $fillable = [
        'pull_request_id',
        'login',
        'user_id',
        'state',
        'submitted_at',
        'commit_sha',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Gemeldet als der zugehörige `pull-request`.
     *
     * @return array{entity: string, id: int, organization_id: int|null}
     */
    public function entityChangeScope(): ?array
    {
        return [
            'entity' => 'pull-request',
            'id' => $this->pull_request_id,
            'organization_id' => $this->pullRequest?->organization_id,
        ];
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
