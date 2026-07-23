<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskEvent;
use App\Models\Task;
use App\Support\NotificationBroadcaster;
use App\Support\TaskEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /api/events — nimmt Fortschritts-Events des /planstack-Skills (und des
 * "Sync"-Buttons) entgegen (siehe docs/event-api.md) und wendet die je Event in
 * der Organisation hinterlegte Automation auf die Aufgabe an.
 */
class EventController extends ApiController
{
    public function __construct(
        private readonly TaskEventService $events,
        private readonly NotificationBroadcaster $broadcaster,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'event' => ['required', 'string', Rule::enum(TaskEvent::class)],
        ]);

        $task = Task::findOrFail($data['task_id']);
        $this->authorize('update', $task);

        $event = TaskEvent::from($data['event']);
        $result = $this->events->record($task, $event, $request->user());

        // Zusätzlich zu den Automations-Ergebnissen die Anzeige-Daten für die
        // Header-Glocke mitgeben: Projekt-/Task-Name und das Icon des (ggf. neu
        // gesetzten) Status. So kann die Glocke „Projekt › Task: Event"
        // lesbar darstellen, statt die rohe Nutzlast zu zeigen. $task->orgStatus
        // spiegelt nach record() den aktuellen (evtl. gewechselten) Status.
        $payload = [
            'task_id' => $task->id,
            'task_name' => $task->name,
            'project_name' => $task->project?->name,
            'event' => $event->value,
            ...$result,
            'status_icon' => $task->orgStatus?->icon,
        ];

        // Ereignis via Pusher an den Organisations-Channel senden (Header-Glocke).
        // Best effort — Fehler brechen die Antwort nicht ab. organization_id
        // fährt zusätzlich in der Nutzlast mit (der Channel ist ohnehin je Org).
        $this->broadcaster->broadcast($request->user()->organization_id, [
            ...$payload,
            'organization_id' => $request->user()->organization_id,
        ]);

        return response()->json($payload);
    }
}
