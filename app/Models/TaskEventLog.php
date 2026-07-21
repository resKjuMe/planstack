<?php

namespace App\Models;

use App\Enums\TaskEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokollzeile eines über POST /api/events gemeldeten Fortschritts-Events
 * (Tabelle `task_events`). Reines Audit-/Fortschrittsprotokoll.
 */
class TaskEventLog extends Model
{
    protected $table = 'task_events';

    protected $fillable = ['task_id', 'actor_id', 'event'];

    protected function casts(): array
    {
        return ['event' => TaskEvent::class];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
