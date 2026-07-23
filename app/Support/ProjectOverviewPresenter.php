<?php

namespace App\Support;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kompakte Aggregat-Übersicht für die Projektliste: pro Projekt Zähler, SP-Summen
 * und Status-Segment-Buckets — per DB-Gruppierung, OHNE alle Task-Rows zu laden
 * oder zu dekorieren. Der Gate-Split der Waiting-Tasks (blockiert vs. pickbar)
 * kommt über Relations-Subqueries (unerfüllte Voraussetzung), nicht über
 * per-Task-PHP. Die Präsentation (Kategorie, Styling, Balkenbreiten) leitet der
 * Client aus diesen Zahlen + der org-weiten status-config ab.
 */
class ProjectOverviewPresenter
{
    /**
     * @return array{projects: array<int, array<string, mixed>>}
     */
    public function payload(User $user): array
    {
        $userId = $user->id;
        $isOrgOwner = $user->organization?->isOwner($user) === true;
        $doneRoles = [StatusRole::COMPLETED->value, StatusRole::MERGED->value];

        $projects = Project::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $isOrgOwner, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('created_by_id', $userId)
                ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $userId))))
            ->withCount('tasks')
            ->withCount(['tasks as done_count' => fn (Builder $q) => $q->whereHas(
                'orgStatus', fn (Builder $s) => $s->whereIn('role', $doneRoles)
            )])
            ->withSum('tasks as total_sp', 'effort_story_points')
            ->withSum(['tasks as done_sp' => fn (Builder $q) => $q->whereHas(
                'orgStatus', fn (Builder $s) => $s->whereIn('role', $doneRoles)
            )], 'effort_story_points')
            ->with(['owner:id,name', 'teams:id,name'])
            ->latest()
            ->get();

        $ids = $projects->pluck('id')->all();
        if (empty($ids)) {
            return ['projects' => []];
        }

        $statuses = $user->organization->statuses()->get();
        $keyById = $statuses->pluck('key', 'id');
        $roleKey = $statuses->whereNotNull('role')->mapWithKeys(fn ($s) => [$s->role->value => $s->key])->all();
        $blockedKey = $roleKey[StatusRole::BLOCKED->value] ?? 'BLOCKED';
        $pickableKey = $roleKey[StatusRole::PICKABLE->value] ?? 'PICKABLE';

        // Nicht-Waiting: direkt nach status_id gruppieren (Anzeigeschlüssel = Status-Key).
        $nonWaiting = Task::query()
            ->whereIn('project_id', $ids)
            ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', '!=', 'waiting'))
            ->selectRaw('project_id, status_id, count(*) as cnt, coalesce(sum(effort_story_points), 0) as sp')
            ->groupBy('project_id', 'status_id')
            ->get()
            ->groupBy('project_id');

        // Eine Voraussetzung gilt als unerfüllt, wenn sie weder einen PR trägt noch
        // in einem „done"-Status steht. Waiting-Task MIT solcher Voraussetzung →
        // blockiert, sonst pickbar.
        $undelivered = fn (Builder $q) => $q->whereNull('pr_number')
            ->whereDoesntHave('orgStatus', fn (Builder $s) => $s->where('counts_as_done', true));

        $blocked = Task::query()
            ->whereIn('project_id', $ids)
            ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'waiting'))
            ->whereHas('prerequisites', $undelivered)
            ->selectRaw('project_id, count(*) as cnt, coalesce(sum(effort_story_points), 0) as sp')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $pickable = Task::query()
            ->whereIn('project_id', $ids)
            ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'waiting'))
            ->whereDoesntHave('prerequisites', $undelivered)
            ->selectRaw('project_id, count(*) as cnt, coalesce(sum(effort_story_points), 0) as sp')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $cards = $projects->map(function (Project $p) use ($nonWaiting, $blocked, $pickable, $keyById, $blockedKey, $pickableKey) {
            $buckets = [];
            foreach ($nonWaiting->get($p->id, collect()) as $row) {
                $key = $keyById->get($row->status_id);
                if ($key === null) {
                    continue;
                }
                $buckets[$key] = ['count' => (int) $row->cnt, 'sp' => (int) $row->sp];
            }
            if ($b = $blocked->get($p->id)) {
                $buckets[$blockedKey] = ['count' => (int) $b->cnt, 'sp' => (int) $b->sp];
            }
            if ($pk = $pickable->get($p->id)) {
                $prev = $buckets[$pickableKey] ?? ['count' => 0, 'sp' => 0];
                $buckets[$pickableKey] = ['count' => $prev['count'] + (int) $pk->cnt, 'sp' => $prev['sp'] + (int) $pk->sp];
            }

            $segments = [];
            foreach ($buckets as $key => $v) {
                if ($v['count'] > 0) {
                    $segments[] = ['key' => $key, 'count' => $v['count'], 'sp' => $v['sp']];
                }
            }

            return [
                'id' => $p->id,
                'alias' => $p->alias,
                'name' => $p->name,
                'description' => $p->description,
                'created_by_id' => $p->created_by_id,
                'archived_at' => $p->archived_at,
                'completed_at' => $p->completed_at,
                'owner' => ['name' => $p->owner?->name],
                'teams' => $p->teams->pluck('name')->values()->all(),
                'tasks_count' => (int) $p->tasks_count,
                'done_count' => (int) $p->done_count,
                'total_sp' => (int) $p->total_sp,
                'done_sp' => (int) $p->done_sp,
                'segments' => $segments,
            ];
        })->all();

        return ['projects' => $cards];
    }
}
