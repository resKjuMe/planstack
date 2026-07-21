<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskEvent;
use App\Models\Task;
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
    public function __construct(private readonly TaskEventService $events) {}

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

        return response()->json([
            'task_id' => $task->id,
            'event' => $event->value,
            ...$result,
        ]);
    }
}
