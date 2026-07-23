<?php

namespace App\Observers;

use App\Models\Task;
use App\Support\NotificationBroadcaster;

/**
 * Broadcastet jede Änderung an einer Aufgabe (created/updated/deleted) generisch
 * über {@see NotificationBroadcaster::broadcastEntity()} an den Org-Channel. Der
 * geteilte React-Store (resources/js/data/projectStore.js) lädt daraufhin genau
 * diese eine Aufgabe partiell nach — statt das ganze Board neu zu holen.
 *
 * Bewusst auf dem Model-Event (nicht nur im Controller), damit REST-CRUD,
 * MCP-Writes und der Drag-and-drop-Move gleichermaßen erfasst werden. "Best
 * effort": der Broadcaster loggt nur, er wirft nie.
 */
class TaskObserver
{
    public function __construct(private readonly NotificationBroadcaster $broadcaster) {}

    public function created(Task $task): void
    {
        $this->emit($task, 'created');
    }

    public function updated(Task $task): void
    {
        $this->emit($task, 'updated');
    }

    public function deleted(Task $task): void
    {
        $this->emit($task, 'deleted');
    }

    private function emit(Task $task, string $action): void
    {
        $project = $task->project;

        if ($project === null) {
            return;
        }

        $this->broadcaster->broadcastEntity($project->organization_id, [
            'type' => 'entity-changed',
            'entity' => 'task',
            'id' => $task->id,
            'action' => $action,
            'project_id' => $project->id,
            'project_alias' => $project->alias,
            'organization_id' => $project->organization_id,
        ]);
    }
}
