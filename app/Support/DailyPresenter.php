<?php

namespace App\Support;

use App\Models\OrgStatus;
use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use App\Models\PullRequestReview;
use App\Models\ReviewFinding;
use App\Models\Task;
use App\Models\TaskEventLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stand-up-Ansicht (/daily): was ist an EINEM Arbeitstag passiert — Statuswechsel
 * der Tasks (Audit-Log), PRs (geöffnet/gemergt/geschlossen), Reviews (ps-review
 * und GitHub-Approvals) — plus der aktuelle Stand für das Gespräch (Blocker, was
 * auf mich wartet, woran ich heute arbeite).
 *
 * `me` zeigt nur Einträge mit meiner Beteiligung (Ersteller, Beanspruchender,
 * Reviewer, Auslöser, PR-Autor), `team` dasselbe für alle Mitglieder meiner Teams.
 * Sichtbar sind nur Projekte, die auch das Dashboard zeigt.
 */
class DailyPresenter
{
    public const TIMEZONE = 'Europe/Berlin';

    private const LIST_LIMIT = 30;

    /** Ab so vielen Tagen ohne Änderung gilt ein laufender Task als liegengeblieben. */
    private const STALE_DAYS = 2;

    /**
     * Letzter Arbeitstag vor `$today` (Mo → Fr, Wochenende → Fr).
     */
    public static function previousWorkday(CarbonImmutable $today): CarbonImmutable
    {
        $day = $today->subDay();
        while ($day->isWeekend()) {
            $day = $day->subDay();
        }

        return $day;
    }

    public static function nextWorkday(CarbonImmutable $day): CarbonImmutable
    {
        $next = $day->addDay();
        while ($next->isWeekend()) {
            $next = $next->addDay();
        }

        return $next;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, CarbonImmutable $day, string $scope): array
    {
        $projectIds = VisibleProjects::idsFor($user);
        $teamIds = $this->teamUserIds($user);
        $userIds = $scope === 'team' ? $teamIds : [$user->id];

        $from = $day->startOfDay()->utc();
        $until = $day->endOfDay()->utc();

        if ($projectIds === []) {
            return ['hasProjects' => false];
        }

        $statuses = OrgStatus::query()->where('organization_id', $user->organization_id)->get()->keyBy('id');
        $taskChanges = $this->taskChanges($projectIds, $userIds, $statuses, $from, $until);
        $pullRequests = $this->pullRequests($user, $userIds, $from, $until);
        $reviews = $this->reviews($user, $userIds, $from, $until);
        $received = $this->reviewsReceived($user, $userIds, $from, $until);
        $blockers = $this->blockers($projectIds, $userIds);
        $waiting = $this->waitingForMe($projectIds, $userIds);
        $today = $this->today($projectIds, $userIds);

        $done = collect($taskChanges)->where('isDone', true);

        return [
            'hasProjects' => true,
            'kpis' => [
                'doneTasks' => $done->count(),
                'doneSp' => (int) $done->sum('sp'),
                'toReview' => collect($taskChanges)->where('toKind', 'review')->count(),
                'prsMerged' => collect($pullRequests)->where('event', 'merged')->count(),
                'prsOpen' => collect($pullRequests)->where('state', 'OPEN')->count(),
                'additions' => (int) collect($pullRequests)->sum('additions'),
                'deletions' => (int) collect($pullRequests)->sum('deletions'),
                'reviewsGiven' => count($reviews),
                'reviewsReceived' => $received,
                'waiting' => count($waiting),
                'blockers' => count($blockers),
                'oldestBlockerDays' => collect($blockers)->max('days'),
            ],
            'taskChanges' => $taskChanges,
            'pullRequests' => $pullRequests,
            'reviews' => $reviews,
            'heatmap' => $this->heatmap($taskChanges, $pullRequests, $reviews),
            'blockers' => $blockers,
            'waiting' => $waiting,
            'forPo' => $this->forPo($pullRequests),
            'forUx' => $this->forUx($pullRequests),
            'today' => $today,
            'team' => $this->team($user, $teamIds, $projectIds, $statuses, $from, $until),
        ];
    }

    /**
     * Mitglieder aller Teams, in denen ich bin (inkl. mir). Ohne Team: nur ich.
     *
     * @return array<int, int>
     */
    private function teamUserIds(User $user): array
    {
        return $user->teams()->with('members:id')->get()
            ->flatMap(fn ($team) => $team->members->pluck('id'))
            ->push($user->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Statuswechsel des Tages, je Task zusammengefasst (erster alter → letzter
     * neuer Status). Beteiligt ist, wer den Wechsel ausgelöst hat oder den Task
     * erstellt, beansprucht oder reviewt.
     *
     * @param  array<int, int>  $projectIds
     * @param  array<int, int>  $userIds
     * @param  Collection<int, OrgStatus>  $statuses
     * @return array<int, array<string, mixed>>
     */
    private function taskChanges(array $projectIds, array $userIds, Collection $statuses, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $logs = $this->statusLogs($projectIds, $from, $until);
        if ($logs->isEmpty()) {
            return [];
        }

        $tasks = Task::query()
            ->whereIn('id', $logs->pluck('entity_id')->unique())
            ->with(['project:id,alias,name', 'concern:id,task_id,summary'])
            ->get()
            ->keyBy('id');

        return $logs->groupBy('entity_id')
            ->map(function (Collection $changes, $taskId) use ($tasks, $userIds, $statuses) {
                $task = $tasks->get($taskId);
                if ($task === null) {
                    return null;
                }

                $involved = $changes->contains(fn ($log) => in_array((int) $log->causer_id, $userIds, true))
                    || $this->involves($task, $userIds);
                if (! $involved) {
                    return null;
                }

                $first = $changes->first();
                $last = $changes->last();
                $fromStatus = $statuses->get($first->old_values['status_id'] ?? null);
                $toStatus = $statuses->get($last->new_values['status_id'] ?? null);

                return [
                    ...$this->taskRef($task),
                    'from' => $fromStatus !== null ? $this->statusLabel($fromStatus) : null,
                    'to' => $toStatus !== null ? $this->statusLabel($toStatus) : null,
                    'toBadge' => StatusPalette::badge($toStatus?->color_token),
                    'toKind' => $toStatus?->kind,
                    'isDone' => (bool) $toStatus?->counts_as_done,
                    'changes' => $changes->count(),
                    'at' => CarbonImmutable::parse($last->created_at)->toIso8601String(),
                    'hours' => $changes->map(fn ($log) => $this->hour($log->created_at))->all(),
                ];
            })
            ->filter()
            ->sortByDesc('at')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Audit-Zeilen mit einem Statuswechsel, chronologisch.
     *
     * @param  array<int, int>  $projectIds
     * @return Collection<int, EloquentAuditLog>
     */
    private function statusLogs(array $projectIds, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        if (! Schema::hasTable(EloquentAuditLog::forEntity(Task::class)->getTable())) {
            return collect();
        }

        return EloquentAuditLog::forEntity(Task::class)
            ->whereIn('entity_id', Task::query()->whereIn('project_id', $projectIds)->select('id'))
            ->whereIn('action', ['created', 'updated'])
            ->whereBetween('created_at', [$from, $until])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn ($log) => array_key_exists('status_id', $log->new_values ?? []))
            ->values();
    }

    /**
     * PRs, die am Tag geöffnet, gemergt oder geschlossen wurden, plus offene mit
     * roter CI — die gehören in jedes Stand-up.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function pullRequests(User $user, array $userIds, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $inDay = fn (string $column) => fn (Builder $q) => $q->whereBetween($column, [$from, $until]);

        return PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $userIds)
            ->where(fn (Builder $q) => $q
                ->where($inDay('opened_at'))
                ->orWhere($inDay('merged_at'))
                ->orWhere($inDay('closed_at'))
                ->orWhere(fn (Builder $red) => $red->where('state', 'OPEN')->where('ci_state', 'failed')))
            ->with(['author:id,name', 'approvals'])
            ->get()
            ->map(function (PullRequest $pr) use ($from, $until) {
                $between = fn ($at) => $at !== null && $at->between($from, $until);
                [$event, $at] = match (true) {
                    $between($pr->merged_at) => ['merged', $pr->merged_at],
                    $between($pr->closed_at) => ['closed', $pr->closed_at],
                    $between($pr->opened_at) => ['opened', $pr->opened_at],
                    default => ['ciFailed', null],
                };

                return [
                    'id' => $pr->id,
                    'number' => $pr->pull_request_id,
                    'repository' => $pr->repository,
                    'title' => $pr->title,
                    'url' => "https://github.com/{$pr->repository}/pull/{$pr->pull_request_id}",
                    'detailUrl' => route('pull-requests.show', $pr),
                    'state' => $pr->state->value,
                    'event' => $event,
                    'at' => $at?->toIso8601String(),
                    'hour' => $at !== null ? $this->hour($at) : null,
                    'authorName' => $pr->author?->name,
                    'additions' => (int) $pr->additions,
                    'deletions' => (int) $pr->deletions,
                    'ciState' => $pr->ci_state,
                    'ciFailedChecks' => $pr->ci_failed_checks ?? [],
                    'unresolvedThreads' => (int) $pr->unresolved_threads,
                    'approvals' => $pr->approvals->where('state', 'APPROVED')->count(),
                    'uiFiles' => $pr->ui_files,
                    'textFiles' => $pr->text_files,
                    'customerView' => self::customerView($pr->description),
                ];
            })
            ->sortByDesc(fn (array $pr) => $pr['at'] ?? '')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Abgegebene Reviews: ps-reviews (mit Befunden) und GitHub-Reviews.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function reviews(User $user, array $userIds, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $psReviews = PullRequestReview::query()
            ->whereIn('reviewer_id', $userIds)
            ->whereBetween('review_finished_at', [$from, $until])
            ->whereHas('pullRequest', fn (Builder $q) => $q->where('organization_id', $user->organization_id))
            ->with(['pullRequest', 'reviewer:id,name'])
            ->get()
            ->map(fn (PullRequestReview $review) => [
                'key' => 'ps-'.$review->id,
                'source' => 'ps-review',
                'number' => $review->pullRequest->pull_request_id,
                'title' => $review->pullRequest->title,
                'url' => route('pull-requests.show', $review->pullRequest),
                'decision' => $review->decision?->value,
                'critical' => (int) $review->findings_critical,
                'important' => (int) $review->findings_important,
                'hints' => (int) $review->findings_hint,
                'poLikelihood' => $review->po_review_likelihood,
                'reviewerName' => $review->reviewer?->name,
                'at' => $review->review_finished_at->toIso8601String(),
                'hour' => $this->hour($review->review_finished_at),
            ]);

        $covered = $psReviews->pluck('number')->all();

        $approvals = PullRequestApproval::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('submitted_at', [$from, $until])
            ->whereHas('pullRequest', fn (Builder $q) => $q->where('organization_id', $user->organization_id))
            ->with(['pullRequest', 'user:id,name'])
            ->get()
            // Ein ps-review, das am PR gepostet wurde, steht schon oben.
            ->reject(fn (PullRequestApproval $approval) => in_array($approval->pullRequest->pull_request_id, $covered, true))
            ->map(fn (PullRequestApproval $approval) => [
                'key' => 'gh-'.$approval->id,
                'source' => 'github',
                'number' => $approval->pullRequest->pull_request_id,
                'title' => $approval->pullRequest->title,
                'url' => route('pull-requests.show', $approval->pullRequest),
                'decision' => match ($approval->state) {
                    'APPROVED' => 'APPROVE',
                    'CHANGES_REQUESTED' => 'REQUEST_CHANGES',
                    default => null,
                },
                'critical' => null,
                'important' => null,
                'hints' => null,
                'poLikelihood' => null,
                'reviewerName' => $approval->user?->name,
                'at' => $approval->submitted_at->toIso8601String(),
                'hour' => $this->hour($approval->submitted_at),
            ]);

        return $psReviews->concat($approvals)
            ->sortByDesc('at')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function reviewsReceived(User $user, array $userIds, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $ownPr = fn (Builder $q) => $q->where('organization_id', $user->organization_id)->whereIn('author_id', $userIds);

        return PullRequestReview::query()
            ->whereBetween('review_finished_at', [$from, $until])
            ->whereNotIn('reviewer_id', $userIds)
            ->whereHas('pullRequest', $ownPr)
            ->count()
            + PullRequestApproval::query()
                ->whereBetween('submitted_at', [$from, $until])
                ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $userIds))
                ->whereHas('pullRequest', $ownPr)
                ->count();
    }

    /**
     * Aktuelle Blocker: eigene Tasks in einer Ausnahme (Concern/blockiert) und
     * eigene Lieferungen im Review, für die sich noch kein Reviewer gefunden hat.
     *
     * @param  array<int, int>  $projectIds
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function blockers(array $projectIds, array $userIds): array
    {
        return Task::query()
            ->whereIn('project_id', $projectIds)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $exception) => $exception
                    ->whereIn('claimed_by_id', $userIds)
                    ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'exception')))
                ->orWhere(fn (Builder $review) => $review
                    ->where(fn (Builder $own) => $own->whereIn('claimed_by_id', $userIds)->orWhereIn('created_by_id', $userIds))
                    ->whereNull('reviewed_by')
                    ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'review'))))
            ->with(['project:id,alias,name', 'orgStatus', 'concern', 'claimer:id,name'])
            ->get()
            ->map(function (Task $task) {
                $isException = $task->orgStatus?->kind === 'exception';
                $since = $isException ? ($task->concern?->created_at ?? $task->updated_at) : $task->updated_at;

                return [
                    ...$this->taskRef($task),
                    'kind' => $isException ? 'exception' : 'noReviewer',
                    'statusLabel' => $task->orgStatus !== null ? $this->statusLabel($task->orgStatus) : null,
                    'statusBadge' => StatusPalette::badge($task->orgStatus?->color_token),
                    'concern' => $task->concern?->summary,
                    'claimerName' => $task->claimer?->name,
                    'since' => $since?->toIso8601String(),
                    'days' => $since !== null ? round(abs($since->diffInHours(now())) / 24, 1) : null,
                ];
            })
            ->sortBy([['kind', 'asc'], ['since', 'asc']])
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Reviews fremder Arbeit, die mir (bzw. dem Team) zugewiesen sind.
     *
     * @param  array<int, int>  $projectIds
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function waitingForMe(array $projectIds, array $userIds): array
    {
        return Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereIn('reviewed_by', $userIds)
            ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'review'))
            ->with(['project:id,alias,name', 'claimer:id,name', 'creator:id,name', 'reviewer:id,name'])
            ->orderBy('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Task $task) => [
                ...$this->taskRef($task),
                'authorName' => $task->claimer?->name ?? $task->creator?->name,
                'reviewerName' => $task->reviewer?->name,
                'since' => $task->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Laufende eigene Arbeitsschritte; ohne Änderung seit STALE_DAYS gelten sie
     * als liegengeblieben.
     *
     * @param  array<int, int>  $projectIds
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function today(array $projectIds, array $userIds): array
    {
        $staleBefore = now()->subDays(self::STALE_DAYS);

        return Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereIn('claimed_by_id', $userIds)
            ->whereHas('orgStatus', fn (Builder $s) => $s->where('kind', 'active'))
            ->with(['project:id,alias,name', 'orgStatus', 'claimer:id,name'])
            ->orderByDesc('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Task $task) => [
                ...$this->taskRef($task),
                'statusLabel' => $task->orgStatus !== null ? $this->statusLabel($task->orgStatus) : null,
                'statusBadge' => StatusPalette::badge($task->orgStatus?->color_token),
                'claimerName' => $task->claimer?->name,
                'progress' => $task->progress_detail,
                'isStale' => $task->updated_at !== null && $task->updated_at->lt($staleBefore),
                'updatedAt' => $task->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Für den PO: die Kundensicht der PRs des Tages und offene PO-Entscheidungen
     * aus den Review-Befunden.
     *
     * @param  array<int, array<string, mixed>>  $pullRequests
     * @return array<int, array<string, mixed>>
     */
    private function forPo(array $pullRequests): array
    {
        $prs = collect($pullRequests)->whereIn('event', ['merged', 'opened']);
        if ($prs->isEmpty()) {
            return [];
        }

        $poDecisions = ReviewFinding::query()
            ->where('requires_po_decision', true)
            ->whereNull('fix_decision')
            ->whereHas('review', fn (Builder $q) => $q->whereIn('pull_request_id', $prs->pluck('id')))
            ->with('review:id,pull_request_id')
            ->get()
            ->groupBy(fn (ReviewFinding $finding) => $finding->review->pull_request_id);

        return $prs
            ->map(fn (array $pr) => [
                'number' => $pr['number'],
                'title' => $pr['title'],
                'url' => $pr['url'],
                'event' => $pr['event'],
                'customerView' => $pr['customerView'],
                'poDecisions' => $poDecisions->get($pr['id'], collect())
                    ->map(fn (ReviewFinding $finding) => $finding->title)
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $row) => $row['customerView'] !== null || $row['poDecisions'] !== [])
            ->values()
            ->all();
    }

    /**
     * Für UX: PRs des Tages mit geänderten Frontend- oder Textdateien.
     *
     * @param  array<int, array<string, mixed>>  $pullRequests
     * @return array<int, array<string, mixed>>
     */
    private function forUx(array $pullRequests): array
    {
        return collect($pullRequests)
            ->filter(fn (array $pr) => ($pr['uiFiles'] ?? 0) > 0 || ($pr['textFiles'] ?? 0) > 0)
            ->map(fn (array $pr) => Arr::only($pr, ['number', 'title', 'url', 'event', 'state', 'uiFiles', 'textFiles']))
            ->values()
            ->all();
    }

    /**
     * Was hat jedes Teammitglied (außer mir) am Tag bewegt.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $projectIds
     * @param  Collection<int, OrgStatus>  $statuses
     * @return array<int, array<string, mixed>>
     */
    private function team(User $user, array $teamIds, array $projectIds, Collection $statuses, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $others = array_values(array_diff($teamIds, [$user->id]));
        if ($others === []) {
            return [];
        }

        $logs = $this->statusLogs($projectIds, $from, $until);
        $doneIds = $statuses->where('counts_as_done', true)->keys()->all();
        $merged = PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $others)
            ->whereBetween('merged_at', [$from, $until])
            ->get(['id', 'author_id', 'pull_request_id']);
        $opened = PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $others)
            ->whereBetween('opened_at', [$from, $until])
            ->get(['id', 'author_id']);
        $reviews = PullRequestReview::query()
            ->whereIn('reviewer_id', $others)
            ->whereBetween('review_finished_at', [$from, $until])
            ->get(['id', 'reviewer_id']);
        $approvals = PullRequestApproval::query()
            ->whereIn('user_id', $others)
            ->whereBetween('submitted_at', [$from, $until])
            ->get(['id', 'user_id']);
        $events = TaskEventLog::query()
            ->whereIn('actor_id', $others)
            ->whereBetween('created_at', [$from, $until])
            ->whereHas('task', fn (Builder $q) => $q->whereIn('project_id', $projectIds))
            ->get(['id', 'actor_id', 'task_id']);

        return User::query()->whereIn('id', $others)->orderBy('name')->get(['id', 'name'])
            ->map(function (User $member) use ($logs, $doneIds, $merged, $opened, $reviews, $approvals, $events) {
                $own = $logs->filter(fn ($log) => (int) $log->causer_id === $member->id);
                $row = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'initials' => Str::of($member->name)->explode(' ')->filter()->take(2)
                        ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))->implode(''),
                    'statusChanges' => $own->count(),
                    'done' => $own->filter(fn ($log) => in_array((int) ($log->new_values['status_id'] ?? 0), $doneIds, true))->count(),
                    'merged' => $merged->where('author_id', $member->id)->count(),
                    'opened' => $opened->where('author_id', $member->id)->count(),
                    'reviews' => $reviews->where('reviewer_id', $member->id)->count()
                        + $approvals->where('user_id', $member->id)->count(),
                    'tasks' => $events->where('actor_id', $member->id)->pluck('task_id')->unique()->count(),
                ];
                $row['active'] = $row['statusChanges'] + $row['merged'] + $row['opened'] + $row['reviews'] + $row['tasks'] > 0;

                return $row;
            })
            ->sortByDesc('active')
            ->values()
            ->all();
    }

    /**
     * Stunden-Heatmap (Ortszeit) je Quelle.
     *
     * @return array{hours: array<int, int>, rows: array<int, array{key: string, cells: array<int, int>}>}
     */
    private function heatmap(array $taskChanges, array $pullRequests, array $reviews): array
    {
        $rows = [
            'statusChanges' => collect($taskChanges)->flatMap(fn (array $change) => $change['hours']),
            'pullRequests' => collect($pullRequests)->pluck('hour')->filter(fn ($hour) => $hour !== null),
            'reviews' => collect($reviews)->pluck('hour'),
        ];

        $all = collect($rows)->flatten();
        $first = min(8, (int) ($all->min() ?? 8));
        $last = max(18, (int) ($all->max() ?? 18));
        $hours = range($first, $last);

        return [
            'hours' => $hours,
            'rows' => collect($rows)->map(fn (Collection $values, string $key) => [
                'key' => $key,
                'cells' => array_map(fn (int $hour) => $values->filter(fn ($value) => (int) $value === $hour)->count(), $hours),
            ])->values()->all(),
        ];
    }

    /**
     * „📣 Kundensicht“ aus dem PR-Body: angekreuzte Sichtbarkeit und der Satz
     * unter „Was ändert sich?“. null ohne Block.
     *
     * @return array{visibility: ?string, change: ?string}|null
     */
    public static function customerView(?string $body): ?array
    {
        if ($body === null || ! preg_match('/<!-- KUNDENSICHT-START[^>]*-->(.*?)<!-- KUNDENSICHT-END -->/s', $body, $match)) {
            return null;
        }

        $block = preg_replace('/<!--.*?-->/s', '', $match[1]);
        $lines = preg_split('/\R/', (string) $block);
        $clean = fn (string $line) => trim(preg_replace('/^[>\-*\s]+|\*\*|__|`/', '', $line));

        $visibility = collect($lines)
            ->first(fn (string $line) => preg_match('/^\s*[-*]\s*\[[xX]\]/', $line) === 1);
        $visibility = $visibility !== null ? $clean(preg_replace('/\[[xX]\]/', '', $visibility, 1)) : null;

        $change = null;
        foreach ($lines as $index => $line) {
            if (Str::contains($line, ['Was ändert sich', 'What changes'])) {
                $rest = trim(Str::after($line, '?'));
                $candidates = $rest !== '' && ! Str::startsWith($rest, ['*', '_']) ? [$rest] : array_slice($lines, $index + 1);
                foreach ($candidates as $candidate) {
                    $text = $clean($candidate);
                    if ($text === '' || Str::startsWith(trim($candidate), '#')) {
                        continue;
                    }
                    if (Str::contains($candidate, ['Was sagen wir', 'What do we tell'])) {
                        break;
                    }
                    $change = $text;
                    break;
                }
                break;
            }
        }

        if ($visibility === null && $change === null) {
            return null;
        }

        return ['visibility' => $visibility ?: null, 'change' => $change];
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function involves(Task $task, array $userIds): bool
    {
        return collect([$task->claimed_by_id, $task->created_by_id, $task->reviewed_by])
            ->filter()
            ->contains(fn ($id) => in_array((int) $id, $userIds, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRef(Task $task): array
    {
        return [
            'id' => $task->id,
            'name' => $task->name,
            'summary' => $task->summary,
            'sp' => $task->effort_story_points,
            'jiraKey' => $task->jira_key,
            'projectAlias' => $task->project?->alias,
            'projectName' => $task->project?->name,
            'url' => $task->project !== null ? route('projects.tasks.show', [$task->project, $task->id]) : null,
            'boardUrl' => $task->project !== null ? route('projects.show', $task->project) : null,
        ];
    }

    private function hour(mixed $at): int
    {
        return (int) CarbonImmutable::parse($at)->setTimezone(self::TIMEZONE)->format('G');
    }

    private function statusLabel(OrgStatus $status): string
    {
        return Str::startsWith(app()->getLocale(), 'en') && $status->label_en
            ? $status->label_en
            : $status->label;
    }
}
