<?php

namespace App\Support;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Baut die Projektkarten-Liste (Fortschritt, Status-Segmente, Kategorie,
 * Besitzer/Teams) für die React-Projektübersicht. Wird über den additiven Modus
 * GET /api/projects?view=cards ausgeliefert, den die Seite clientseitig lädt und
 * per entity-changed live aktualisiert.
 */
class ProjectCardPresenter
{
    public function __construct(
        private readonly TaskBoardService $board,
        private readonly StatusSegments $segments,
    ) {}

    /**
     * @return array{projects: array<int, array<string, mixed>>, summaryLine: string}
     */
    public function webPayload(User $user): array
    {
        $userId = $user->id;
        // Der Organisationsgründer sieht alle Projekte seiner Organisation; alle
        // anderen nur eigene bzw. per Team zugängliche.
        $isOrgOwner = $user->organization?->isOwner($user) === true;

        $projects = Project::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $isOrgOwner, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('created_by_id', $userId)
                ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $userId))))
            ->withCount('tasks')
            ->withCount(['tasks as closed_tasks_count' => fn (Builder $q) => $q->whereHas(
                'orgStatus', fn (Builder $s) => $s->whereIn('role', [StatusRole::COMPLETED->value, StatusRole::MERGED->value])
            )])
            ->withSum('tasks as total_sp', 'effort_story_points')
            ->withSum(['tasks as done_sp' => fn (Builder $q) => $q->whereHas(
                'orgStatus', fn (Builder $s) => $s->whereIn('role', [StatusRole::COMPLETED->value, StatusRole::MERGED->value])
            )], 'effort_story_points')
            ->with(['owner', 'teams:id,name'])
            ->latest()
            ->get();

        // Kopfzeilen-Statistik bezieht sich auf aktive (nicht archivierte) Projekte.
        $activeProjects = $projects->whereNull('archived_at');
        $activeCount = $activeProjects->count();
        $openTasks = (int) $activeProjects->sum(fn (Project $p) => $p->tasks_count - $p->closed_tasks_count);
        $totalSp = (int) $activeProjects->sum('total_sp');

        $summaryLine = ($activeCount === 1 ? '1 '.__('projects.project') : $activeCount.' '.__('common.projects'))
            .' · '.__('projects.count_open_tasks', ['count' => number_format($openTasks, 0, ',', '.')])
            .' · '.__('projects.count_story_points', ['count' => number_format($totalSp, 0, ',', '.')]);

        return [
            'projects' => $projects->map(fn (Project $p) => $this->card($p, $userId))->all(),
            'summaryLine' => $summaryLine,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Project $project, ?int $userId): array
    {
        $sp = (int) $project->total_sp;
        $pct = $sp > 0 ? (int) $project->done_sp / $sp * 100 : 0;
        $isCompleted = $project->completed_at !== null;
        // Abgeschlossene Projekte tragen die Kategorie „completed" und überschreiben
        // die berechnete Kategorie.
        $category = $isCompleted
            ? 'completed'
            : ($pct <= 0 ? 'nicht_gestartet' : ($pct >= 80 ? 'fast_fertig' : 'in_arbeit'));

        $categoryLabel = [
            'nicht_gestartet' => __('projects.not_started'),
            'in_arbeit' => __('projects.in_progress'),
            'fast_fertig' => __('projects.almost_done'),
            'completed' => __('projects.completed'),
        ][$category];
        $badgeClass = [
            'nicht_gestartet' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
            'in_arbeit' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
            'fast_fertig' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
            'completed' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
        ][$category];
        $barClass = [
            'nicht_gestartet' => 'bg-gray-300',
            'in_arbeit' => 'bg-indigo-600',
            'fast_fertig' => 'bg-green-500',
            'completed' => 'bg-blue-500',
        ][$category];

        $initials = collect(preg_split('/\s+/', trim($project->owner?->name ?? '?')))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $avatarPalette = ['bg-emerald-600', 'bg-indigo-600', 'bg-rose-600', 'bg-amber-600', 'bg-sky-600', 'bg-fuchsia-600'];

        $segments = collect($this->segments->segments($project, $this->board->decorate($project)))
            ->map(fn (array $s) => array_merge($s, ['pctLabel' => number_format((float) $s['width'], 1, ',', '')]))
            ->all();

        return [
            'alias' => $project->alias,
            'name' => $project->name,
            'descriptionHtml' => filled($project->description) ? Str::markdown($project->description, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'renderer' => ['soft_break' => "<br>\n"],
            ]) : null,
            'diagramUrl' => route('projects.diagram', $project),
            'category' => $category,
            'categoryLabel' => $categoryLabel,
            'badgeClass' => $badgeClass,
            'barClass' => $barClass,
            'pct' => round($pct, 1),
            'pctLabel' => number_format($pct, 1, ',', ''),
            'segments' => $segments,
            'ownerName' => $project->owner?->name,
            'initials' => $initials,
            'avatarClass' => $avatarPalette[($project->created_by_id ?? 0) % count($avatarPalette)],
            'teams' => $project->teams->pluck('name')->all(),
            'tasksCount' => $project->tasks_count,
            'tasksLabel' => __('projects.count_tasks', ['count' => $project->tasks_count]),
            'sp' => $sp,
            'mine' => $project->created_by_id === $userId,
            'archived' => $project->archived_at !== null,
            'searchText' => Str::lower($project->alias.' '.$project->name),
        ];
    }
}
