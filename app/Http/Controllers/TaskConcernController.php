<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskConcernRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TaskConcernController extends Controller
{
    public function edit(Project $project, Task $task): InertiaResponse
    {
        $this->authorize('update', $task);

        return Inertia::render('ConcernEdit', [
            'project' => ['alias' => $project->alias],
            'task' => ['name' => $task->name],
            'updateUrl' => route('projects.tasks.concern.update', [$project, $task]),
            'cancelUrl' => route('projects.tasks.show', [$project, $task]),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            // Bestehende Concern-Werte asynchron nachladen (Skeleton währenddessen).
            'formData' => Inertia::defer(function () use ($task) {
                $c = $task->concern()->first();

                return [
                    'values' => [
                        'summary' => $c?->summary ?? '',
                        'description_context' => $c?->description_context ?? '',
                        'description_blocker' => $c?->description_blocker ?? '',
                        'description_misconception' => $c?->description_misconception ?? '',
                        'description_decisions' => $c?->description_decisions ?? '',
                    ],
                ];
            }),
            'strings' => [
                'concern' => __('common.concern'),
                'summary' => __('common.summary_2'),
                'context' => __('concerns.context_collected_background'),
                'blocker' => __('concerns.blocker_why_it_is_blocked'),
                'misconception' => __('concerns.misconception_why_the_planning_was_wrong'),
                'decisions' => __('common.open_decisions'),
                'decisionsHint' => __('concerns.1_decision_per_line_options_as_csv_with'),
                'decisionsExample' => __('concerns.which_way_to_go_option_a_option_b'),
                'cancel' => __('common.cancel'),
                'save' => __('common.save'),
            ],
        ]);
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
