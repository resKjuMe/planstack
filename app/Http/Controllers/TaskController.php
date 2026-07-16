<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function create(Project $project): View
    {
        $this->authorize('contribute', $project);

        $project->load('phases');
        $candidates = $project->tasks()->orderBy('name')->get(['id', 'name', 'summary']);

        return view('tasks.create', compact('project', 'candidates'));
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $prerequisites = $data['prerequisites'] ?? [];
        unset($data['prerequisites']);

        $task = $project->tasks()->create([
            ...$data,
            'created_by_id' => $request->user()->id,
            'status' => $data['status'] ?? TaskStatus::UNKNOWN->value,
        ]);

        $task->prerequisites()->sync($prerequisites);

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', "Task \"{$task->name}\" wurde angelegt.");
    }

    public function show(Project $project, Task $task): View
    {
        $this->authorize('view', $task);

        $task->load(['creator', 'claimer', 'phase', 'concern.creator', 'prerequisites', 'dependents']);

        return view('tasks.show', compact('project', 'task'));
    }

    public function edit(Project $project, Task $task): View
    {
        $this->authorize('update', $task);

        $project->load('phases');
        $candidates = $project->tasks()->whereKeyNot($task->id)->orderBy('name')->get(['id', 'name', 'summary']);
        $selected = $task->prerequisites()->pluck('tasks.id')->all();

        return view('tasks.edit', compact('project', 'task', 'candidates', 'selected'));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $prerequisites = $data['prerequisites'] ?? [];
        unset($data['prerequisites']);

        // Stamp merged_at the first time a task reaches MERGED.
        if (($data['status'] ?? null) === TaskStatus::MERGED->value && $task->merged_at === null) {
            $data['merged_at'] = now();
        }

        $task->update($data);
        $task->prerequisites()->sync($prerequisites);

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Task aktualisiert.');
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Task gelöscht.');
    }

    /**
     * Toggle claim/release of a task by the current user.
     */
    public function claim(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('claim', $task);

        if ($task->claimed_by_id === null) {
            $task->update([
                'claimed_by_id' => request()->user()->id,
                'claimed_at' => now(),
                'status' => TaskStatus::CLAIMED->value,
            ]);
            $message = "Task \"{$task->name}\" beansprucht.";
        } else {
            $task->update([
                'claimed_by_id' => null,
                'claimed_at' => null,
                'status' => TaskStatus::PICKABLE->value,
            ]);
            $message = "Task \"{$task->name}\" freigegeben.";
        }

        return back()->with('status', $message);
    }

    /**
     * Claim the review of a task: stamp the current user as reviewer. Only
     * possible while the task is in review, has no reviewer yet, and the user is
     * not its own assignee (you don't review your own work).
     */
    public function reviewClaim(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $user = request()->user();

        if ($task->status !== TaskStatus::IN_REVIEW
            || $task->reviewed_by !== null
            || $task->claimed_by_id === $user->id) {
            return back()->with('status', 'Review kann für diesen Task nicht übernommen werden.');
        }

        $task->update(['reviewed_by' => $user->name]);

        return back()->with('status', "Du reviewst jetzt Task \"{$task->name}\".");
    }
}
