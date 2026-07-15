<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Task;
use App\Models\TaskConcern;
use App\Models\TaskRequirement;
use App\Models\User;
use Carbon\Carbon;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ProjectChangelogController extends Controller
{
    /**
     * Changelog diff fields that hold a user id — resolved to a name via a
     * single batched lookup instead of showing the raw id.
     */
    private const USER_REF_FIELDS = ['created_by_id', 'claimed_by_id', 'user_id'];

    /**
     * Combined, paginated changelog across the project itself and everything
     * hanging off it (tasks, phases, concerns, dependencies, memberships).
     * Each of those models writes to its own audit table, so the feed is
     * built as a UNION ALL across the tables that actually have rows for
     * this project, then sorted and paginated as one result set.
     */
    public function __invoke(Project $project): View
    {
        $this->authorize('view', $project);

        // Live tables only know about records that still exist — a deleted
        // task simply isn't there anymore. Its "deleted" audit row always
        // carries the full old snapshot though, so its id (and its own
        // deleted concerns/dependencies, scoped through it) is recovered from
        // there instead, or the delete itself would never show up.
        $tasksById = $project->tasks()->pluck('name', 'id');
        $allTaskIds = $tasksById->keys()
            ->merge($this->deletedEntityIds(Task::class, 'project_id', $project->id))
            ->unique()->values();

        $phasesById = $project->phases()->pluck('name', 'id');
        $allPhaseIds = $phasesById->keys()
            ->merge($this->deletedEntityIds(Phase::class, 'project_id', $project->id))
            ->unique()->values();

        $concernTaskById = TaskConcern::whereIn('task_id', $allTaskIds)->pluck('task_id', 'id');
        $allConcernIds = $concernTaskById->keys()
            ->merge($this->deletedEntityIds(TaskConcern::class, 'task_id', $allTaskIds))
            ->unique()->values();

        $requirementsById = TaskRequirement::whereIn('task_id', $allTaskIds)->get(['id', 'task_id', 'parent_id'])->keyBy('id');
        $allRequirementIds = $requirementsById->keys()
            ->merge($this->deletedEntityIds(TaskRequirement::class, 'task_id', $allTaskIds))
            ->unique()->values();

        $membershipUserById = $project->memberships()->pluck('user_id', 'id');
        $allMembershipIds = $membershipUserById->keys()
            ->merge($this->deletedEntityIds(ProjectMembership::class, 'project_id', $project->id))
            ->unique()->values();

        $sources = [
            Project::class => ['label' => 'Projekt', 'ids' => collect([$project->id])],
            Task::class => ['label' => 'Task', 'ids' => $allTaskIds],
            Phase::class => ['label' => 'Phase', 'ids' => $allPhaseIds],
            TaskConcern::class => ['label' => 'Concern', 'ids' => $allConcernIds],
            TaskRequirement::class => ['label' => 'Abhängigkeit', 'ids' => $allRequirementIds],
            ProjectMembership::class => ['label' => 'Mitgliedschaft', 'ids' => $allMembershipIds],
        ];

        $query = null;
        foreach ($sources as $class => $meta) {
            if ($meta['ids']->isEmpty() || ! Schema::hasTable(EloquentAuditLog::forEntity($class)->getTable())) {
                continue;
            }

            $sub = EloquentAuditLog::forEntity($class)
                ->whereIn('entity_id', $meta['ids'])
                ->selectRaw(
                    '? as entity_label, ? as entity_class, entity_id, action, old_values, new_values, causer_type, causer_id, source, created_at',
                    [$meta['label'], $class]
                );

            $query = $query ? $query->unionAll($sub) : $sub;
        }

        if ($query === null) {
            $changes = new LengthAwarePaginator([], 0, 25);
        } else {
            $changes = $query->orderByDesc('created_at')->paginate(25)->withQueryString();
            $logs = $changes->getCollection();

            // Filing/updating a concern flips the task to CONCERNED in the same
            // request — that's one edit from the user's perspective, so the
            // status change is folded into the concern row instead of showing
            // as its own "Task aktualisiert" line right next to it.
            $mergedStatus = $this->mergeConcernStatusFlips($logs, $concernTaskById);

            // Setting a PR number is likewise a separate request from the status
            // flip it triggers (e.g. IN_REVIEW once a PR exists) — fold the
            // PR-number-only row into the accompanying status row.
            $this->mergeTaskPrNumberIntoStatusChange($logs);

            $userRefIds = $logs->flatMap(fn ($log) => [
                ...array_values(Arr::only($log->old_values ?? [], self::USER_REF_FIELDS)),
                ...array_values(Arr::only($log->new_values ?? [], self::USER_REF_FIELDS)),
            ]);
            $userIds = $logs->pluck('causer_id')
                ->merge($membershipUserById->values())
                ->merge($userRefIds)
                ->filter()
                ->unique();
            $usersById = User::whereIn('id', $userIds)->pluck('name', 'id');
            $refs = ['users' => $usersById, 'tasks' => $tasksById, 'phases' => $phasesById];
            $lookups = [$project, $tasksById, $phasesById, $concernTaskById, $requirementsById, $membershipUserById, $usersById];

            $changes->setCollection($logs->map(function ($log, $key) use ($refs, $lookups, $mergedStatus) {
                $merged = $mergedStatus[$key] ?? null;

                $sections = [[
                    'label' => $merged ? "{$log->entity_label} ".$this->auditActionVerb($log->action) : null,
                    'rows' => $this->auditDiff($log, $refs),
                ]];
                if ($merged) {
                    array_unshift($sections, [
                        'label' => 'Task '.$this->auditActionVerb('updated'),
                        'rows' => [[
                            'field' => $this->auditFieldLabel('status'),
                            'old' => $this->statusLabel($merged['old']),
                            'new' => $this->statusLabel($merged['new']),
                        ]],
                    ]);
                }

                return [
                    'when' => Carbon::parse($log->created_at)->setTimezone('Europe/Berlin'),
                    'headline' => $this->auditHeadline($log, ...$lookups, merged: $merged),
                    'causer' => $log->causer_id ? ($refs['users'][$log->causer_id] ?? "Nutzer #{$log->causer_id}") : 'System',
                    'causer_short' => $log->causer_id ? $this->shortName($refs['users'][$log->causer_id] ?? "Nutzer #{$log->causer_id}") : 'System',
                    'sections' => array_map(fn ($s) => [
                        'label' => $s['label'],
                        'visible' => array_slice($s['rows'], 0, 3),
                        'hidden' => array_slice($s['rows'], 3),
                    ], $sections),
                ];
            })->values());
        }

        return view('status.changelog', [
            'project' => $project,
            'active' => 'changelog',
            'changes' => $changes,
        ]);
    }

    /**
     * Ids of entities of $class that were later deleted, but whose "deleted"
     * audit row's snapshot (old_values, always the full attribute set) still
     * points at $scopeValue via $scopeColumn — e.g. a deleted task's own
     * project_id, or a deleted concern's task_id being one of this project's
     * (possibly also-deleted) tasks.
     *
     * @param  int|\Illuminate\Support\Collection<int, int>  $scopeValue
     */
    private function deletedEntityIds(string $class, string $scopeColumn, $scopeValue): Collection
    {
        if (! Schema::hasTable(EloquentAuditLog::forEntity($class)->getTable())) {
            return collect();
        }

        $query = EloquentAuditLog::forEntity($class)->where('action', 'deleted');

        if ($scopeValue instanceof Collection) {
            if ($scopeValue->isEmpty()) {
                return collect();
            }
            $query->whereIn("old_values->{$scopeColumn}", $scopeValue);
        } else {
            $query->where("old_values->{$scopeColumn}", $scopeValue);
        }

        return $query->pluck('entity_id')->map(fn ($id) => (int) $id);
    }

    /**
     * Finds Task rows whose *only* change is a flip to CONCERNED and pairs
     * each with the TaskConcern row that caused it (same task, same instant).
     * The Task row is dropped from $logs (by reference); the raw old/new
     * status values come back keyed by the concern row's key in $logs, for
     * the caller to fold into that row's headline and diff sections.
     *
     * @return array<int|string, array{old: ?string, new: ?string}>
     */
    private function mergeConcernStatusFlips(Collection $logs, Collection $concernTaskById): array
    {
        $statusFlips = [];
        foreach ($logs as $key => $log) {
            // getChanges() (and thus new_values) always carries 'updated_at'
            // alongside the actual change, so that's ignored for the "only
            // the status changed" check.
            $new = Arr::except($log->new_values ?? [], ['updated_at']);
            if ($log->entity_class === Task::class && $log->action === 'updated'
                && array_keys($new) === ['status'] && ($new['status'] ?? null) === TaskStatus::CONCERNED->value) {
                $statusFlips[(int) $log->entity_id][] = $key;
            }
        }

        if (! $statusFlips) {
            return [];
        }

        $merged = [];
        foreach ($logs as $key => $log) {
            if ($log->entity_class !== TaskConcern::class) {
                continue;
            }

            $taskId = (int) ($log->new_values['task_id'] ?? $log->old_values['task_id'] ?? $concernTaskById[(int) $log->entity_id] ?? 0);
            if (! $taskId || empty($statusFlips[$taskId])) {
                continue;
            }

            foreach ($statusFlips[$taskId] as $i => $taskLogKey) {
                $taskLog = $logs[$taskLogKey];
                if (Carbon::parse($taskLog->created_at)->diffInSeconds(Carbon::parse($log->created_at), true) > 5) {
                    continue;
                }

                $merged[$key] = [
                    'old' => $taskLog->old_values['status'] ?? null,
                    'new' => $taskLog->new_values['status'] ?? null,
                ];
                $logs->forget($taskLogKey);
                unset($statusFlips[$taskId][$i]);

                break;
            }
        }

        return $merged;
    }

    /**
     * A PR number is usually set via its own request, moments before or after
     * the task's status flips (e.g. to IN_REVIEW) — two Task 'updated' audit
     * rows for the same task. Rather than list them separately, the
     * PR-number-only row is merged straight into the status row's own
     * old/new values (by reference, on the shared model instance) and then
     * dropped from $logs, so it simply shows up as another field in that
     * row's diff and can be surfaced in its headline.
     */
    private function mergeTaskPrNumberIntoStatusChange(Collection $logs): void
    {
        $prOnlyByTask = [];
        $statusByTask = [];

        foreach ($logs as $key => $log) {
            if ($log->entity_class !== Task::class || $log->action !== 'updated') {
                continue;
            }

            $new = Arr::except($log->new_values ?? [], ['updated_at']);
            $taskId = (int) $log->entity_id;

            if (array_keys($new) === ['pr_number']) {
                $prOnlyByTask[$taskId][] = $key;
            } elseif (array_key_exists('status', $new)) {
                $statusByTask[$taskId][] = $key;
            }
        }

        foreach ($prOnlyByTask as $taskId => $prKeys) {
            if (empty($statusByTask[$taskId])) {
                continue;
            }

            foreach ($prKeys as $prKey) {
                $prLog = $logs[$prKey];

                foreach ($statusByTask[$taskId] as $i => $statusKey) {
                    $statusLog = $logs[$statusKey];
                    if (Carbon::parse($prLog->created_at)->diffInSeconds(Carbon::parse($statusLog->created_at), true) > 5) {
                        continue;
                    }

                    $statusLog->old_values = [...($statusLog->old_values ?? []), 'pr_number' => $prLog->old_values['pr_number'] ?? null];
                    $statusLog->new_values = [...($statusLog->new_values ?? []), 'pr_number' => $prLog->new_values['pr_number'] ?? null];
                    $logs->forget($prKey);
                    unset($statusByTask[$taskId][$i]);

                    break;
                }
            }
        }
    }

    private function statusLabel(?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        return TaskStatus::tryFrom($value)?->label() ?? $value;
    }

    private function statusBadge(?string $value): string
    {
        return TaskStatus::tryFrom((string) $value)?->badgeClasses() ?? 'bg-gray-100 text-gray-600';
    }

    /**
     * "Christian Mietze" → "C. Mietze", for the compact causer chip.
     */
    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) < 2) {
            return $name;
        }

        return mb_substr($parts[0], 0, 1).'. '.array_pop($parts);
    }

    /**
     * "Jonas Grobe" → "JG", for the compact claimer suffix on the status arrow.
     */
    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));

        return implode('', array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts));
    }

    /**
     * @param  array{users: Collection, tasks: Collection, phases: Collection}  $refs
     * @return array<int, array{field: string, old: ?string, new: ?string}>
     */
    private function auditDiff(EloquentAuditLog $log, array $refs): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $skip = ['id', 'created_at', 'updated_at'];

        $rows = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (in_array($key, $skip, true)) {
                continue;
            }

            $rows[] = [
                'field' => $this->auditFieldLabel($key),
                'old' => array_key_exists($key, $old) ? $this->auditFormatValue($key, $old[$key], $refs) : null,
                'new' => array_key_exists($key, $new) ? $this->auditFormatValue($key, $new[$key], $refs) : null,
            ];
        }

        return $rows;
    }

    /**
     * Compact, one-line "sentence" for the row header: a plain description
     * for most entities ("Phase X aktualisiert"), but a richer one for the
     * two cases worth reading at a glance — a task's status arrow and a
     * concern (optionally folded together with the status change it caused).
     *
     * @return array<int, array{t: string, v: string, cls?: string}>
     */
    private function auditHeadline(
        EloquentAuditLog $log,
        Project $project,
        Collection $tasksById,
        Collection $phasesById,
        Collection $concernTaskById,
        Collection $requirementsById,
        Collection $membershipUserById,
        Collection $usersById,
        ?array $merged = null,
    ): array {
        $id = (int) $log->entity_id;
        $values = $log->new_values ?: ($log->old_values ?: []);
        $verb = $this->auditActionVerb($log->action);
        $taskLabel = fn (mixed $taskId) => $taskId ? ($tasksById[$taskId] ?? "Task #{$taskId}") : '?';
        $text = fn (string $v) => ['t' => 'text', 'v' => $v];
        $tag = fn (string $v) => ['t' => 'tag', 'v' => $v];

        if ($log->entity_class === Task::class) {
            $new = $log->new_values ?? [];
            // Any status change gets the arrow headline, even when other
            // fields changed alongside it (e.g. MERGED also sets merged_at) —
            // those still show up in the expandable diff below.
            if ($log->action === 'updated' && array_key_exists('status', $new)) {
                $old = $log->old_values['status'] ?? null;
                $statusLabel = $this->statusLabel($new['status']);
                if ($new['status'] === TaskStatus::CLAIMED->value && ! empty($new['claimed_by_id'])) {
                    $claimer = $usersById[$new['claimed_by_id']] ?? null;
                    if ($claimer) {
                        $statusLabel .= ' ('.$this->initials($claimer).')';
                    }
                }
                $segments = [$tag($tasksById[$id] ?? ($values['name'] ?? "Task #{$id}")), $text(' · ')];
                if ($old !== null) {
                    $segments[] = ['t' => 'status', 'v' => $this->statusLabel($old), 'cls' => $this->statusBadge($old)];
                    $segments[] = $text(' → ');
                }
                $segments[] = ['t' => 'status', 'v' => $statusLabel, 'cls' => $this->statusBadge($new['status'])];
                if (! empty($new['pr_number'])) {
                    $segments[] = $text(' ');
                    $segments[] = ['t' => 'status', 'v' => '#'.$new['pr_number'], 'cls' => 'bg-gray-100 text-gray-600'];
                }

                return $segments;
            }

            return [$tag($tasksById[$id] ?? ($values['name'] ?? "Task #{$id}")), $text(' · '.$verb)];
        }

        if ($log->entity_class === TaskConcern::class) {
            $taskId = $values['task_id'] ?? $concernTaskById[$id] ?? null;
            $segments = [$text('Concern zu '), $tag($taskLabel($taskId))];

            if ($merged) {
                $segments[] = $text(' · Status → ');
                $segments[] = ['t' => 'status', 'v' => $this->statusLabel($merged['new']), 'cls' => $this->statusBadge($merged['new'])];
            } else {
                $segments[] = $text(' '.$verb);
            }

            $summary = $values['summary'] ?? null;
            if ($summary) {
                $segments[] = $text(' · ');
                $segments[] = ['t' => 'quote', 'v' => mb_strlen($summary) > 60 ? mb_substr($summary, 0, 60).'…' : $summary];
            }

            return $segments;
        }

        return match ($log->entity_class) {
            Project::class => [$text('Projekt '), $tag($project->alias), $text(' '.$verb)],
            Phase::class => [$text('Phase '), $tag($phasesById[$id] ?? ($values['name'] ?? "#{$id}")), $text(' '.$verb)],
            TaskRequirement::class => [
                $tag($taskLabel($values['task_id'] ?? $requirementsById[$id]->task_id ?? null)),
                $text(' ← '),
                $tag($taskLabel($values['parent_id'] ?? $requirementsById[$id]->parent_id ?? null)),
                $text(' '.$verb),
            ],
            ProjectMembership::class => [
                $text('Mitgliedschaft: '),
                $tag((function () use ($values, $membershipUserById, $id, $usersById) {
                    $userId = $values['user_id'] ?? $membershipUserById[$id] ?? null;

                    return $userId ? ($usersById[$userId] ?? "Nutzer #{$userId}") : "#{$id}";
                })()),
                $text(' '.$verb),
            ],
            default => [$text("#{$id} ".$verb)],
        };
    }

    private function auditActionVerb(string $action): string
    {
        return match ($action) {
            'created' => 'erstellt',
            'updated' => 'aktualisiert',
            'deleted' => 'gelöscht',
            'restored' => 'wiederhergestellt',
            default => $action,
        };
    }

    private function auditFieldLabel(string $key): string
    {
        return match ($key) {
            'name' => 'Name',
            'alias' => 'Kürzel',
            'summary' => 'Zusammenfassung',
            'description' => 'Beschreibung',
            'description_acceptance_criteria' => 'Akzeptanzkriterien',
            'github_repo' => 'GitHub-Repo',
            'skill_description' => 'Skill-Beschreibung',
            'status' => 'Status',
            'phase_id' => 'Phase',
            'position' => 'Position',
            'effort_story_points' => 'Story Points',
            'effort_man_days' => 'Personentage',
            'effort_tokens' => 'Tokens',
            'affected_files' => 'Dateien',
            'pr_number' => 'PR-Nummer',
            'claimed_by_id' => 'Beansprucht von',
            'claimed_at' => 'Beansprucht am',
            'merged_at' => 'Gemergt am',
            'created_by_id' => 'Erstellt von',
            'task_id' => 'Task',
            'parent_id' => 'Abhängig von',
            'role' => 'Rolle',
            'user_id' => 'Benutzer',
            'project_id' => 'Projekt',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @param  array{users: Collection, tasks: Collection, phases: Collection}  $refs
     */
    private function auditFormatValue(string $key, mixed $value, array $refs): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Ja' : 'Nein';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '—';
        }

        if (in_array($key, self::USER_REF_FIELDS, true)) {
            return $refs['users'][$value] ?? "Nutzer #{$value}";
        }

        if (in_array($key, ['task_id', 'parent_id'], true)) {
            return $refs['tasks'][$value] ?? "Task #{$value}";
        }

        if ($key === 'phase_id') {
            return $refs['phases'][$value] ?? "Phase #{$value}";
        }

        if (str_ends_with($key, '_at')) {
            try {
                return Carbon::parse($value)->setTimezone('Europe/Berlin')->format('d.m.Y H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($key === 'status') {
            return TaskStatus::tryFrom((string) $value)?->label() ?? (string) $value;
        }

        $str = (string) $value;

        return mb_strlen($str) > 120 ? mb_substr($str, 0, 120).'…' : $str;
    }
}
