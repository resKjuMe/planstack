<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use App\Models\PullRequestReview;
use App\Models\ReviewFinding;
use App\Models\User;
use App\Support\Jira\JiraActivity;
use App\Support\Jira\JiraRequestFailed;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stand-up-Ansicht (/daily): was ist an EINEM Arbeitstag passiert — Statuswechsel
 * der Jira-Tickets, PRs (geöffnet/gemergt/geschlossen) und Reviews (ps-review und
 * GitHub-Approvals) — plus der aktuelle Stand für das Gespräch (Blocker, Tickets
 * im Review/Test, woran ich heute arbeite).
 *
 * Tickets kommen live aus Jira, mit dem Jira-Zugang des Betrachters (Profil).
 * `me` zeigt nur Einträge mit meiner Beteiligung (Zuständiger, Melder, Auslöser
 * eines Statuswechsels, PR-Autor, Reviewer), `team` dasselbe für alle Mitglieder
 * meiner Teams.
 */
class DailyPresenter
{
    public const TIMEZONE = 'Europe/Berlin';

    private const LIST_LIMIT = 30;

    /** Ab so vielen Tagen ohne Änderung gilt ein Ticket in Arbeit als liegengeblieben. */
    private const STALE_DAYS = 2;

    /** Statusnamen, die als blockiert gelten (zusätzlich zum Jira-Flag). */
    public const BLOCKED_STATUS = '/block|imped|on hold|hold|warte|wait/i';

    /** Statusnamen für Review/Test. */
    public const REVIEW_STATUS = '/review|test|qa|abnahme|prüf|pruef|verif|approval/i';

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, CarbonImmutable $day, string $scope): array
    {
        $teamIds = self::teamUserIds($user);
        $userIds = $scope === 'team' ? $teamIds : [$user->id];

        $from = $day->startOfDay()->utc();
        $until = $day->endOfDay()->utc();

        $pullRequests = $this->pullRequests($user, $userIds, $from, $until);
        $reviews = $this->reviews($user, $userIds, $from, $until);
        $jira = $this->jira($user, $teamIds, $userIds, $day);
        $issueChanges = $jira['issueChanges'];

        $done = collect($issueChanges)->where('isDone', true);

        return [
            'jira' => Arr::only($jira, ['connected', 'error']),
            'kpis' => [
                'doneTasks' => $done->count(),
                'doneSp' => (float) $done->sum('sp'),
                'toReview' => collect($issueChanges)->where('toReview', true)->count(),
                'prsMerged' => collect($pullRequests)->where('event', 'merged')->count(),
                'prsOpen' => collect($pullRequests)->where('state', 'OPEN')->count(),
                'additions' => (int) collect($pullRequests)->sum('additions'),
                'deletions' => (int) collect($pullRequests)->sum('deletions'),
                'reviewsGiven' => count($reviews),
                'reviewsReceived' => $this->reviewsReceived($user, $userIds, $from, $until),
                'waiting' => count($jira['waiting']),
                'blockers' => count($jira['blockers']),
                'oldestBlockerDays' => collect($jira['blockers'])->max('days'),
            ],
            'issueChanges' => $issueChanges,
            'pullRequests' => $pullRequests,
            'reviews' => $reviews,
            'heatmap' => $this->heatmap($issueChanges, $pullRequests, $reviews),
            'blockers' => $jira['blockers'],
            'waiting' => $jira['waiting'],
            'forPo' => $this->forPo($pullRequests),
            'forUx' => $this->forUx($pullRequests),
            'today' => $jira['today'],
            'team' => $this->team($user, $teamIds, $jira['dayIssues'], $jira['accounts'], $from, $until),
        ];
    }

    /**
     * Alles aus Jira: Statuswechsel des Tages (eine Abfrage für das ganze Team,
     * danach auf den Umfang gefiltert) und die offenen Tickets des Umfangs.
     *
     * @param  array<int, int>  $teamIds
     * @param  array<int, int>  $userIds
     * @return array<string, mixed>
     */
    private function jira(User $user, array $teamIds, array $userIds, CarbonImmutable $day): array
    {
        $empty = [
            'connected' => $user->hasJiraCredentials(),
            'error' => null,
            'issueChanges' => [],
            'blockers' => [],
            'waiting' => [],
            'today' => [],
            'dayIssues' => collect(),
            'accounts' => [],
        ];
        if (! $user->hasJiraCredentials()) {
            return $empty;
        }

        try {
            $jira = JiraActivity::for($user);
            $accounts = $jira->accountIds(User::query()->whereIn('id', $teamIds)->get());
            $scopeAccounts = array_values(Arr::only($accounts, $userIds));
            if ($scopeAccounts === []) {
                return $empty;
            }

            $start = $day->startOfDay()->format('Y-m-d H:i');
            $end = $day->addDay()->startOfDay()->format('Y-m-d H:i');
            $list = fn (array $ids) => implode(', ', array_map(fn ($id) => '"'.$id.'"', $ids));
            $changedBy = collect($accounts)->map(fn ($id) => "status CHANGED BY \"{$id}\" DURING (\"{$start}\", \"{$end}\")")->implode(' OR ');

            $dayIssues = $jira->search(
                "updated >= \"{$start}\" AND (assignee in ({$list(array_values($accounts))}) OR reporter in ({$list(array_values($accounts))}) OR {$changedBy}) ORDER BY updated DESC"
            );
            $open = $jira->search(
                "assignee in ({$list($scopeAccounts)}) AND statusCategory != Done ORDER BY updated ASC",
                changelog: false,
            );
        } catch (JiraRequestFailed|ConnectionException $e) {
            return [...$empty, 'error' => $e->getMessage()];
        }

        $from = $day->startOfDay();
        $until = $day->endOfDay();

        return [
            'connected' => true,
            'error' => null,
            'issueChanges' => $this->issueChanges($dayIssues, $scopeAccounts, $from, $until),
            'blockers' => $this->blockers($open),
            'waiting' => $this->waiting($open),
            'today' => $this->today($open),
            'dayIssues' => $dayIssues,
            'accounts' => $accounts,
        ];
    }

    /**
     * Statuswechsel des Tages je Ticket (erster alter → letzter neuer Status).
     *
     * @param  Collection<int, array<string, mixed>>  $issues
     * @param  array<int, string>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function issueChanges(Collection $issues, array $accounts, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $issues
            ->map(function (array $issue) use ($accounts, $from, $until) {
                $changes = collect($issue['transitions'])->filter(fn (array $t) => $t['at']->between($from, $until))->values();
                if ($changes->isEmpty()) {
                    return null;
                }

                $involved = in_array($issue['assignee'], $accounts, true)
                    || in_array($issue['reporter'], $accounts, true)
                    || $changes->contains(fn (array $t) => in_array($t['author'], $accounts, true));
                if (! $involved) {
                    return null;
                }

                $last = $changes->last();
                $toCategory = $last['to'] === $issue['status'] ? $issue['category'] : 'indeterminate';

                return [
                    ...$this->issueRef($issue),
                    'from' => $changes->first()['from'],
                    'to' => $last['to'],
                    'toCategory' => $toCategory,
                    'isDone' => $toCategory === 'done',
                    'toReview' => preg_match(self::REVIEW_STATUS, (string) $last['to']) === 1,
                    'changes' => $changes->count(),
                    'at' => $last['at']->toIso8601String(),
                    'hours' => $changes->map(fn (array $t) => $this->hour($t['at']))->all(),
                ];
            })
            ->filter()
            ->sortByDesc('at')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Offene Tickets mit Flag oder in einem Blockiert-Status.
     *
     * @param  Collection<int, array<string, mixed>>  $open
     * @return array<int, array<string, mixed>>
     */
    private function blockers(Collection $open): array
    {
        return $open
            ->filter(fn (array $issue) => $this->isBlocked($issue))
            ->map(fn (array $issue) => [
                ...$this->issueRef($issue),
                'flagged' => $issue['flagged'],
                'days' => $issue['updated'] !== null ? round(abs($issue['updated']->diffInHours(now())) / 24, 1) : null,
            ])
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Offene Tickets im Review oder Test.
     *
     * @param  Collection<int, array<string, mixed>>  $open
     * @return array<int, array<string, mixed>>
     */
    private function waiting(Collection $open): array
    {
        return $open
            ->reject(fn (array $issue) => $this->isBlocked($issue))
            ->filter(fn (array $issue) => preg_match(self::REVIEW_STATUS, (string) $issue['status']) === 1)
            ->map(fn (array $issue) => [
                ...$this->issueRef($issue),
                'since' => $issue['updated']?->toIso8601String(),
            ])
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Tickets in Arbeit; ohne Änderung seit STALE_DAYS gelten sie als liegengeblieben.
     *
     * @param  Collection<int, array<string, mixed>>  $open
     * @return array<int, array<string, mixed>>
     */
    private function today(Collection $open): array
    {
        $staleBefore = now()->subDays(self::STALE_DAYS);

        return $open
            ->reject(fn (array $issue) => $this->isBlocked($issue))
            ->reject(fn (array $issue) => preg_match(self::REVIEW_STATUS, (string) $issue['status']) === 1)
            ->where('category', 'indeterminate')
            ->sortByDesc(fn (array $issue) => $issue['updated']?->getTimestamp() ?? 0)
            ->map(fn (array $issue) => [
                ...$this->issueRef($issue),
                'isStale' => $issue['updated'] !== null && $issue['updated']->lt($staleBefore),
                'updatedAt' => $issue['updated']?->toIso8601String(),
            ])
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Was hat jedes Teammitglied (außer mir) am Tag bewegt.
     *
     * @param  array<int, int>  $teamIds
     * @param  Collection<int, array<string, mixed>>  $dayIssues
     * @param  array<int, string>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function team(User $user, array $teamIds, Collection $dayIssues, array $accounts, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $others = array_values(array_diff($teamIds, [$user->id]));
        if ($others === []) {
            return [];
        }

        $local = fn (CarbonImmutable $at) => $at->between($from, $until);
        $transitions = $dayIssues->flatMap(fn (array $issue) => collect($issue['transitions'])
            ->filter(fn (array $t) => $local($t['at']))
            ->map(fn (array $t) => [...$t, 'key' => $issue['key'], 'done' => $t['to'] === $issue['status'] && $issue['category'] === 'done']));

        $merged = PullRequest::query()->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $others)->whereBetween('merged_at', [$from, $until])->get(['id', 'author_id']);
        $opened = PullRequest::query()->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $others)->whereBetween('opened_at', [$from, $until])->get(['id', 'author_id']);
        $reviews = PullRequestReview::query()->whereIn('reviewer_id', $others)
            ->whereBetween('review_finished_at', [$from, $until])->get(['id', 'reviewer_id']);
        $approvals = PullRequestApproval::query()->whereIn('user_id', $others)
            ->whereBetween('submitted_at', [$from, $until])->get(['id', 'user_id']);

        return User::query()->whereIn('id', $others)->orderBy('name')->get(['id', 'name'])
            ->map(function (User $member) use ($accounts, $transitions, $merged, $opened, $reviews, $approvals) {
                $account = $accounts[$member->id] ?? null;
                $own = $account !== null ? $transitions->where('author', $account) : collect();
                $row = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'initials' => Str::of($member->name)->explode(' ')->filter()->take(2)
                        ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))->implode(''),
                    'statusChanges' => $own->count(),
                    'done' => $own->where('done', true)->pluck('key')->unique()->count(),
                    'issues' => $own->pluck('key')->unique()->count(),
                    'merged' => $merged->where('author_id', $member->id)->count(),
                    'opened' => $opened->where('author_id', $member->id)->count(),
                    'reviews' => $reviews->where('reviewer_id', $member->id)->count()
                        + $approvals->where('user_id', $member->id)->count(),
                ];
                $row['active'] = $row['statusChanges'] + $row['merged'] + $row['opened'] + $row['reviews'] > 0;

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
    private function heatmap(array $issueChanges, array $pullRequests, array $reviews): array
    {
        $rows = [
            'statusChanges' => collect($issueChanges)->flatMap(fn (array $change) => $change['hours']),
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
     * @param  array<string, mixed>  $issue
     */
    public static function isBlocked(array $issue): bool
    {
        return $issue['flagged'] || preg_match(self::BLOCKED_STATUS, (string) $issue['status']) === 1;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    public static function issueRef(array $issue): array
    {
        return [
            'key' => $issue['key'],
            'summary' => $issue['summary'],
            'url' => $issue['url'],
            'type' => $issue['type'],
            'status' => $issue['status'],
            'category' => $issue['category'],
            'assigneeName' => $issue['assigneeName'],
            'sp' => $issue['sp'],
        ];
    }

    private function hour(mixed $at): int
    {
        return (int) CarbonImmutable::parse($at)->setTimezone(self::TIMEZONE)->format('G');
    }

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
     * Mitglieder aller Teams, in denen ich bin (inkl. mir). Ohne Team: nur ich.
     *
     * @return array<int, int>
     */
    public static function teamUserIds(User $user): array
    {
        return $user->teams()->with('members:id')->get()
            ->flatMap(fn ($team) => $team->members->pluck('id'))
            ->push($user->id)
            ->unique()
            ->values()
            ->all();
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
}
