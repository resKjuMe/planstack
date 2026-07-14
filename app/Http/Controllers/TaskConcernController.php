<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskConcernRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskConcernController extends Controller
{
    public function edit(Project $project, Task $task): View
    {
        $this->authorize('update', $task);

        $task->load('concern');

        return view('concerns.edit', compact('project', 'task'));
    }

    public function update(StoreTaskConcernRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->concern()->updateOrCreate(
            ['task_id' => $task->id],
            [
                ...$request->validated(),
                'created_by_id' => $task->concern?->created_by_id ?? $request->user()->id,
            ],
        );

        // Surface the concern on the board.
        if ($task->status !== TaskStatus::MERGED) {
            $task->update(['status' => TaskStatus::CONCERNED->value]);
        }

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Concern gespeichert.');
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->concern()->delete();

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Concern entfernt.');
    }
}
