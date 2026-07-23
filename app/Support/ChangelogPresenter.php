<?php

namespace App\Support;

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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Baut den kombinierten, paginierten Changelog-Feed (UNION ALL über die
 * per-Entity-Audit-Tabellen) als JSON-sicheres Payload. Ausgelagert aus dem
 * (früheren Blade-)Controller, damit die React-Changelog-Seite die Einträge über
 * einen paginierten API-Endpunkt bekommt — die schwere Aufbereitung (Zusammen-
 * fassen von Concern/PR-Statuswechseln, Headline-Segmente, Diff) bleibt serverseitig.
 */
class ChangelogPresenter
{
    private const USER_REF_FIELDS = ['created_by_id', 'claimed_by_id', 'reviewed_by', 'user_id'];

    private const PER_PAGE = 25;

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function payload(Project $project, int $page = 1): array
    {
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
            Project::class => ['label' => __('changelog.source_project'), 'ids' => collect([$project->id])],
            Task::class => ['label' => __('changelog.source_task'), 'ids' => $allTaskIds],
            Phase::class => ['label' => __('changelog.source_phase'), 'ids' => $allPhaseIds],
            TaskConcern::class => ['label' => __('changelog.source_concern'), 'ids' => $allConcernIds],
            TaskRequirement::class => ['label' => __('changelog.source_dependency'), 'ids' => $allRequirementIds],
            ProjectMembership::class => ['label' => __('changelog.source_membership'), 'ids' => $allMembershipIds],
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
            return [
                'items' => [],
                'pagination' => ['total' => 0, 'currentPage' => 1, 'lastPage' => 1, 'hasMore' => false],
            ];
        }

        $changes = $query->orderByDesc('created_at')->paginate(self::PER_PAGE, ['*'], 'page', $page);
        $logs = $changes->getCollection();

        $mergedStatus = $this->mergeConcernStatusFlips($logs, $concernTaskById);
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

        $items = $logs->map(function ($log, $key) use ($refs, $lookups, $mergedStatus) {
            $merged = $mergedStatus[$key] ?? null;

            $sections = [[
                'label' => $merged ? "{$log->entity_label} ".$this->auditActionVerb($log->action) : null,
                'rows' => $this->auditDiff($log, $refs),
            ]];
            if ($merged) {
                array_unshift($sections, [
                    'label' => __('changelog.source_task').' '.$this->auditActionVerb('updated'),
                    'rows' => [[
                        'field' => $this->auditFieldLabel('status'),
                        'old' => $this->statusLabel($merged['old']),
                        'new' => $this->statusLabel($merged['new']),
                    ]],
                ]);
            }

            $when = Carbon::parse($log->created_at)->setTimezone('Europe/Berlin');
            $causer = $log->causer_id ? ($refs['users'][$log->causer_id] ?? __('changelog.user_ref', ['id' => $log->causer_id])) : __('changelog.system');

            return [
                'dateLabel' => $when->format('d.m.Y'),
                'timeLabel' => $when->format('H:i'),
                'headline' => $this->auditHeadline($log, ...$lookups, merged: $merged),
                'causer' => $causer,
                'causerShort' => $log->causer_id ? $this->shortName($causer) : __('changelog.system'),
                'sections' => array_map(fn ($s) => [
                    'label' => $s['label'],
                    'visible' => array_slice($s['rows'], 0, 3),
                    'hidden' => array_slice($s['rows'], 3),
                ], $sections),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $changes->total(),
                'currentPage' => $changes->currentPage(),
                'lastPage' => $changes->lastPage(),
                'hasMore' => $changes->hasMorePages(),
            ],
        ];
    }

    /**
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
     * @return array<int|string, array{old: ?string, new: ?string}>
     */
    private function mergeConcernStatusFlips(Collection $logs, Collection $concernTaskById): array
    {
        $statusFlips = [];
        foreach ($logs as $key => $log) {
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
        return TaskStatus::tryFrom((string) $value)?->badgeClasses() ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
    }

    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) < 2) {
            return $name;
        }

        return mb_substr($parts[0], 0, 1).'. '.array_pop($parts);
    }

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
        $taskLabel = fn (mixed $taskId) => $taskId ? ($tasksById[$taskId] ?? __('changelog.task_ref', ['id' => $taskId])) : '?';
        $text = fn (string $v) => ['t' => 'text', 'v' => $v];
        $tag = fn (string $v) => ['t' => 'tag', 'v' => $v];

        if ($log->entity_class === Task::class) {
            $new = $log->new_values ?? [];
            if ($log->action === 'updated' && array_key_exists('status', $new)) {
                $old = $log->old_values['status'] ?? null;
                $statusLabel = $this->statusLabel($new['status']);
                if ($new['status'] === TaskStatus::CLAIMED->value && ! empty($new['claimed_by_id'])) {
                    $claimer = $usersById[$new['claimed_by_id']] ?? null;
                    if ($claimer) {
                        $statusLabel .= ' ('.$this->initials($claimer).')';
                    }
                }
                $segments = [$tag($tasksById[$id] ?? ($values['name'] ?? __('changelog.task_ref', ['id' => $id]))), $text(' · ')];
                if ($old !== null) {
                    $segments[] = ['t' => 'status', 'v' => $this->statusLabel($old), 'cls' => $this->statusBadge($old)];
                    $segments[] = $text(' → ');
                }
                $segments[] = ['t' => 'status', 'v' => $statusLabel, 'cls' => $this->statusBadge($new['status'])];
                if (! empty($new['pr_number'])) {
                    $segments[] = $text(' ');
                    $segments[] = ['t' => 'status', 'v' => '#'.$new['pr_number'], 'cls' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'];
                }

                return $segments;
            }

            return [$tag($tasksById[$id] ?? ($values['name'] ?? __('changelog.task_ref', ['id' => $id]))), $text(' · '.$verb)];
        }

        if ($log->entity_class === TaskConcern::class) {
            $taskId = $values['task_id'] ?? $concernTaskById[$id] ?? null;
            $segments = [$text(__('changelog.concern_prefix')), $tag($taskLabel($taskId))];

            if ($merged) {
                $segments[] = $text(__('changelog.status_arrow'));
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
            Project::class => [$text(__('changelog.project_prefix')), $tag($project->alias), $text(' '.$verb)],
            Phase::class => [$text(__('changelog.phase_prefix')), $tag($phasesById[$id] ?? ($values['name'] ?? __('changelog.entity_ref', ['id' => $id]))), $text(' '.$verb)],
            TaskRequirement::class => [
                $tag($taskLabel($values['task_id'] ?? $requirementsById[$id]->task_id ?? null)),
                $text(' ← '),
                $tag($taskLabel($values['parent_id'] ?? $requirementsById[$id]->parent_id ?? null)),
                $text(' '.$verb),
            ],
            ProjectMembership::class => [
                $text(__('changelog.membership_prefix')),
                $tag((function () use ($values, $membershipUserById, $id, $usersById) {
                    $userId = $values['user_id'] ?? $membershipUserById[$id] ?? null;

                    return $userId ? ($usersById[$userId] ?? __('changelog.user_ref', ['id' => $userId])) : __('changelog.entity_ref', ['id' => $id]);
                })()),
                $text(' '.$verb),
            ],
            default => [$text(__('changelog.entity_ref', ['id' => $id]).' '.$verb)],
        };
    }

    private function auditActionVerb(string $action): string
    {
        return match ($action) {
            'created' => __('changelog.action_created'),
            'updated' => __('changelog.action_updated'),
            'deleted' => __('changelog.action_deleted'),
            'restored' => __('changelog.action_restored'),
            default => $action,
        };
    }

    private function auditFieldLabel(string $key): string
    {
        return match ($key) {
            'name' => __('changelog.field_name'),
            'alias' => __('changelog.field_alias'),
            'summary' => __('changelog.field_summary'),
            'description' => __('changelog.field_description'),
            'description_acceptance_criteria' => __('changelog.field_acceptance_criteria'),
            'github_repo' => __('changelog.field_github_repo'),
            'skill_description' => __('changelog.field_skill_description'),
            'status' => __('changelog.field_status'),
            'phase_id' => __('changelog.field_phase'),
            'position' => __('changelog.field_position'),
            'effort_story_points' => __('changelog.field_story_points'),
            'effort_man_days' => __('changelog.field_man_days'),
            'effort_tokens' => __('changelog.field_tokens'),
            'affected_files' => __('changelog.field_files'),
            'pr_number' => __('changelog.field_pr_number'),
            'reviewed_by' => __('changelog.field_reviewed_by'),
            'claimed_by_id' => __('changelog.field_claimed_by'),
            'claimed_at' => __('changelog.field_claimed_at'),
            'merged_at' => __('changelog.field_merged_at'),
            'created_by_id' => __('changelog.field_created_by'),
            'task_id' => __('changelog.field_task'),
            'parent_id' => __('changelog.field_parent'),
            'role' => __('changelog.field_role'),
            'user_id' => __('changelog.field_user'),
            'project_id' => __('changelog.field_project'),
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
            return $value ? __('changelog.value_yes') : __('changelog.value_no');
        }

        if (is_array($value)) {
            return json_encode($value) ?: '—';
        }

        if (in_array($key, self::USER_REF_FIELDS, true)) {
            return $refs['users'][$value] ?? __('changelog.user_ref', ['id' => $value]);
        }

        if (in_array($key, ['task_id', 'parent_id'], true)) {
            return $refs['tasks'][$value] ?? __('changelog.task_ref', ['id' => $value]);
        }

        if ($key === 'phase_id') {
            return $refs['phases'][$value] ?? __('changelog.phase_ref', ['id' => $value]);
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
