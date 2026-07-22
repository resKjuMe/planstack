<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokollzeile eines eingehenden GitHub-Webhooks (POST /hooks/git). Vorerst
 * reines Audit-Protokoll (Tabelle `git_webhook_events`) – die eigentliche
 * Ereignisverarbeitung folgt später.
 */
class GitWebhookEvent extends Model
{
    protected $fillable = [
        'event',
        'action',
        'delivery_id',
        'repository',
        'pr_number',
        'project_id',
        'task_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'pr_number' => 'integer',
            'payload' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
