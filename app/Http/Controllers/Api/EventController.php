<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskEvent;
use App\Models\Project;
use App\Models\Task;
use App\Support\NotificationBroadcaster;
use App\Support\TaskEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Nimmt Fortschritts-Events des /planstack-Skills (und des "Sync"-Buttons)
 * entgegen (siehe docs/event-api.md) und wendet die je Event in der Organisation
 * hinterlegte Automation auf die Aufgabe an. Zwei Einstiege mit identischer
 * Wirkung: die top-level Route (`POST /api/events`, Task per numerischer id im
 * Body) und die projekt-gebundene Route (`POST /api/projects/{project}/tasks/
 * {task}/events`, Task per Name **oder** id im Pfad — bequem für den Skill, der
 * projektübergreifend über REST arbeitet und den Task-Namen ohnehin kennt).
 */
class EventController extends ApiController
{
    public function __construct(
        private readonly TaskEventService $events,
        private readonly NotificationBroadcaster $broadcaster,
    ) {}

    /**
     * POST /api/events — Task per numerischer id im Body.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'event' => ['required', 'string', Rule::enum(TaskEvent::class)],
        ]);

        $task = Task::findOrFail($data['task_id']);

        return $this->emit($request, $task, TaskEvent::from($data['event']));
    }

    /**
     * POST /api/projects/{project}/tasks/{task}/events — projekt-gebunden, Task
     * per Name oder id im Pfad (scopeBindings hält ihn aufs Projekt beschränkt).
     * So kann jedes Projekt Events über plain REST melden, ohne den (an ein
     * einzelnes Projekt gebundenen) MCP-Server zu benötigen.
     */
    public function storeForTask(Request $request, Project $project, Task $task): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', Rule::enum(TaskEvent::class)],
        ]);

        return $this->emit($request, $task, TaskEvent::from($data['event']));
    }

    /**
     * Gemeinsame Logik: Event protokollieren, Automation anwenden, Header-Glocke
     * benachrichtigen und die maßgebliche Nutzlast zurückgeben.
     */
    private function emit(Request $request, Task $task, TaskEvent $event): JsonResponse
    {
        $this->authorize('update', $task);

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
