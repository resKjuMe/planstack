<?php

namespace App\Http\Controllers\Api;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/projects/{project}/jira-sync — alles, was `/planstack sync` braucht, in
 * einem Aufruf: das Epic des Projekts, die Phasen (→ Jira-Aufgaben) und je Phase
 * die Tasks (→ Unteraufgaben) mit ihren vollständigen Beschreibungen und den
 * bereits verknüpften Jira-Keys. Das Anlegen in Jira übernimmt der Client; die
 * neuen Keys schreibt er über die PATCH-Endpunkte von Phase/Task zurück.
 */
class JiraSyncController extends ApiController
{
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $tasks = $project->tasks()
            ->with(['orgStatus', 'prerequisites:id,name'])
            ->get()
            ->sortBy('name', SORT_NATURAL)
            ->groupBy(fn (Task $task): string => (string) ($task->phase_id ?? ''));

        $phases = $project->phases()->orderBy('position')->get()
            ->map(fn (Phase $phase): array => [
                'id' => $phase->id,
                'name' => $phase->name,
                'position' => $phase->position,
                'jira_key' => $phase->jira_key,
                'tasks' => $this->tasks($tasks->get((string) $phase->id)),
            ])
            ->values();

        return response()->json([
            'project' => [
                'alias' => $project->alias,
                'name' => $project->name,
                'description' => $project->description,
                'github_repo' => $project->github_repo,
                'jira_epic_key' => $project->jira_epic_key,
            ],
            'phases' => $phases,
            'unphased_tasks' => $this->tasks($tasks->get('')),
        ]);
    }

    /**
     * @param  iterable<Task>|null  $tasks
     * @return list<array<string, mixed>>
     */
    private function tasks(?iterable $tasks): array
    {
        return collect($tasks ?? [])->map(fn (Task $task): array => [
            'id' => $task->id,
            'name' => $task->name,
            'jira_key' => $task->jira_key,
            'summary' => $task->summary,
            'description' => $task->description,
            'acceptance_criteria' => $task->description_acceptance_criteria,
            'target_actual' => $task->description_target_actual,
            'test_cases' => $task->description_test_cases,
            'criticality' => $task->criticality?->value,
            'status' => $task->orgStatus?->key,
            'effort' => [
                'man_days' => $task->effort_man_days !== null ? (float) $task->effort_man_days : null,
                'story_points' => $task->effort_story_points,
            ],
            'gate' => $task->prerequisites->pluck('name')->sortBy(fn ($n) => $n, SORT_NATURAL)->values()->all(),
            'pr_number' => $task->pr_number,
        ])->values()->all();
    }
}
