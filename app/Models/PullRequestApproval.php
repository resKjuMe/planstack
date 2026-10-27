<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PullRequestApproval extends Model
{
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

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
