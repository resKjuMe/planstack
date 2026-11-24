<?php

namespace App\Support;

use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Planstack-Task aus dem PR-Titel nach der Konvention „<PROJEKT>-<TASK>: …“
 * (z. B. „CYA-A1: …“ oder „EGF-S1c: …“), wie die Task-Pill des GitHub-Userscripts.
 */
class PullRequestTaskRefs
{
    private const KEY = '/^\s*([A-Z0-9]+)-([A-Za-z0-9]+):/';

    /** Jira-Projekte, deren Keys dasselbe Muster haben. */
    private const JIRA_PROJECTS = ['MN', 'AR', 'CON', 'EI'];

    /** Status-Key → Farbe der Pill (wie im Userscript). */
    private const TONES = [
        'PICKABLE' => 'new',
        'CLAIMED' => 'active',
        'ANALYZING' => 'active',
        'IN_PROGRESS' => 'active',
        'BEREINIGEN' => 'active',
        'REVIEWBAR' => 'review',
        'IN_REVIEW' => 'review',
        'APPROVED' => 'done',
        'MERGED' => 'done',
        'COMPLETED' => 'done',
        'BLOCKED' => 'blocked',
        'CONCERNED' => 'blocked',
    ];

    /** Fallback für eigene Status: Farbe nach Art. */
    private const KIND_TONES = [
        'waiting' => 'new',
        'active' => 'active',
        'review' => 'review',
        'done' => 'done',
        'exception' => 'blocked',
    ];

    /**
     * Task je PR-ID; nur Tasks aus Projekten, die der Nutzer sehen darf.
     *
     * @param  iterable<PullRequest>  $pullRequests
     * @return array<int, array{key: string, url: string, status: string|null, summary: string|null, tone: string}>
     */
    public static function forPullRequests(iterable $pullRequests, User $user): array
    {
        $refs = collect($pullRequests)
            ->mapWithKeys(fn (PullRequest $pr) => [$pr->id => self::parse((string) $pr->title)])
            ->filter();
        if ($refs->isEmpty()) {
            return [];
        }

        $projects = Project::query()
            ->whereIn('alias', $refs->pluck('project')->unique()->values())
            ->get()
            ->filter(fn (Project $p) => $user->can('view', $p))
            ->keyBy('alias');
        if ($projects->isEmpty()) {
            return [];
        }

        $tasks = Task::query()
            ->with('orgStatus')
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereIn('name', $refs->pluck('task')->unique()->values())
            ->get()
            ->groupBy('project_id');

        return $refs
            ->map(fn (array $ref) => self::present($ref, $projects->get($ref['project']), $tasks))
            ->filter()
            ->all();
    }

    /**
     * @return array{project: string, task: string}|null
     */
    private static function parse(string $title): ?array
    {
        if (! preg_match(self::KEY, $title, $m) || in_array($m[1], self::JIRA_PROJECTS, true)) {
            return null;
        }

        return ['project' => $m[1], 'task' => $m[2]];
    }

    /**
     * @param  array{project: string, task: string}  $ref
     * @param  Collection<int, Collection<int, Task>>  $tasks
     * @return array{key: string, url: string, status: string|null, summary: string|null, tone: string}|null
     */
    private static function present(array $ref, ?Project $project, Collection $tasks): ?array
    {
        $task = $project ? $tasks->get($project->id)?->firstWhere('name', $ref['task']) : null;
        if ($task === null) {
            return null;
        }

        $status = $task->orgStatus;

        return [
            'key' => $ref['project'].'-'.$ref['task'],
            'url' => route('projects.tasks.show', [$project, $task]),
            'status' => $status?->label,
            'summary' => $task->summary,
            'tone' => self::TONES[$status?->key] ?? self::KIND_TONES[$status?->kind] ?? 'new',
        ];
    }
}
