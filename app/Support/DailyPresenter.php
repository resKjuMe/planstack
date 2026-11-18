<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\PullRequestApproval;
use App\Models\PullRequestReview;
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
 * Stand-up-Ansicht (/daily): Fortschritt an EINEM Arbeitstag (Statuswechsel der
 * Jira-Tickets, PRs, Reviews) und der Stand fürs Gespräch: woran heute gearbeitet
 * wird, eigene PRs mit Handlungsbedarf, Blocker.
 *
 * Tickets kommen live aus Jira, mit dem Jira-Zugang des Betrachters (Profil).
 * `me` zeigt nur Einträge mit meiner Beteiligung (Zuständiger, Melder, Auslöser
 * eines Statuswechsels, PR-Autor, Reviewer), `team` dasselbe für alle Mitglieder
 * meiner Teams.
 */
class DailyPresenter
{
    public const TIMEZONE = 'Europe/Berlin';

    private const LIST_LIMIT = 50;

    /** Ab so vielen Tagen ohne Änderung gilt ein Ticket in Arbeit als liegengeblieben. */
    private const STALE_DAYS = 3;

    /** Ab so vielen Stunden ohne ausreichende Freigabe wartet ein PR zu lange auf ein Review. */
    private const REVIEW_WAIT_HOURS = 24;

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

        [$pullRequests, $reviews, $attention] = $this->withTickets(
            $user,
            $this->pullRequests($user, $userIds, $from, $until),
            $this->reviews($user, $userIds, $from, $until),
            $this->attention($user, $userIds),
        );
        $jira = $this->withPullRequests($user, $this->jira($user, $teamIds, $userIds, $day, $scope === 'team'));

        return [
            'jira' => Arr::only($jira, ['connected', 'error']),
            'issueChanges' => $jira['issueChanges'],
            'pullRequests' => $pullRequests,
            'reviews' => $reviews,
            'attention' => $attention,
            'blockers' => $jira['blockers'],
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
    private function jira(User $user, array $teamIds, array $userIds, CarbonImmutable $day, bool $assignedOnly = false): array
    {
        $empty = [
            'connected' => $user->hasJiraCredentials(),
            'error' => null,
            'issueChanges' => [],
            'blockers' => [],
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
            'issueChanges' => $this->issueChanges($dayIssues, $scopeAccounts, $from, $until, $assignedOnly),
            'blockers' => $this->blockers($open),
            'today' => $this->today($open),
            'dayIssues' => $dayIssues,
            'accounts' => $accounts,
        ];
    }

    /**
     * PR- und Review-Zeilen zeigen das Ticket statt des PR-Titels: je Zeile die
     * Tickets (Key + Jira-Titel) und der PR mit Ersteller (Jira-Avatar).
     *
     * @param  array<int, array<string, mixed>>  $pullRequests
     * @param  array<int, array<string, mixed>>  $reviews
     * @param  array<int, array<string, mixed>>  $attention
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function withTickets(User $user, array $pullRequests, array $reviews, array $attention): array
    {
        $ids = array_unique([...array_column($pullRequests, 'id'), ...array_column($reviews, 'prId'), ...array_column($attention, 'id')]);
        $resolved = TicketPullRequests::ticketsFor($user, PullRequest::query()->whereIn('id', $ids)->with('author:id,name,email')->get());
        $attach = fn (array $row, int $id) => [...$row, 'tickets' => $resolved[$id]['tickets'] ?? [], 'pr' => $resolved[$id]['pr'] ?? null];

        return [
            array_map(fn (array $row) => $attach($row, $row['id']), $pullRequests),
            array_map(fn (array $row) => $attach($row, $row['prId']), $reviews),
            array_map(fn (array $row) => $attach($row, $row['id']), $attention),
        ];
    }

    /**
     * Hängt an jedes Ticket die zugehörigen PRs samt Ersteller (Jira-Avatar).
     *
     * @param  array<string, mixed>  $jira
     * @return array<string, mixed>
     */
    private function withPullRequests(User $user, array $jira): array
    {
        $lists = ['issueChanges', 'blockers', 'today'];
        $keys = collect($lists)->flatMap(fn (string $list) => array_column($jira[$list], 'key'))->all();
        $prs = TicketPullRequests::forKeys($user, $keys);

        foreach ($lists as $list) {
            $jira[$list] = array_map(fn (array $row) => [...$row, 'prs' => $prs[strtoupper($row['key'])] ?? []], $jira[$list]);
        }

        return $jira;
    }

    /**
     * Statuswechsel des Tages je Ticket (erster alter → letzter neuer Status).
     * Tickets ohne Netto-Wechsel (z. B. nur angelegt) zählen nicht als Fortschritt.
     *
     * @param  Collection<int, array<string, mixed>>  $issues
     * @param  array<int, string>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function issueChanges(Collection $issues, array $accounts, CarbonImmutable $from, CarbonImmutable $until, bool $assignedOnly = false): array
    {
        return $issues
            ->map(function (array $issue) use ($accounts, $from, $until, $assignedOnly) {
                $changes = collect($issue['transitions'])->filter(fn (array $t) => $t['at']->between($from, $until))->values();
                if ($changes->isEmpty()) {
                    return null;
                }

                // Team: nur Tickets, die einem Teammitglied zugewiesen sind (Karten je Zuständigem).
                if ($assignedOnly && ! in_array($issue['assignee'], $accounts, true)) {
                    return null;
                }

                $involved = in_array($issue['assignee'], $accounts, true)
                    || in_array($issue['reporter'], $accounts, true)
                    || $changes->contains(fn (array $t) => in_array($t['author'], $accounts, true));
                if (! $involved) {
                    return null;
                }

                $first = $changes->first();
                $last = $changes->last();
                if ($first['from'] === null || $first['from'] === $last['to']) {
                    return null;
                }
                $toCategory = $last['to'] === $issue['status'] ? $issue['category'] : 'indeterminate';

                return [
                    ...$this->issueRef($issue),
                    'from' => $first['from'],
                    'to' => $last['to'],
                    'toCategory' => $toCategory,
                    'isDone' => $toCategory === 'done',
                    'at' => $last['at']->toIso8601String(),
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
            'parent' => $issue['parent'] ?? null,
            'status' => $issue['status'],
            'category' => $issue['category'],
            'assigneeName' => $issue['assigneeName'],
            'sp' => $issue['sp'],
        ];
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
     * PRs, die am Tag geöffnet, gemergt oder geschlossen wurden.
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
                ->orWhere($inDay('closed_at')))
            ->with('author:id,name')
            ->get()
            ->map(function (PullRequest $pr) use ($from, $until) {
                $between = fn ($at) => $at !== null && $at->between($from, $until);
                [$event, $at] = match (true) {
                    $between($pr->merged_at) => ['merged', $pr->merged_at],
                    $between($pr->closed_at) => ['closed', $pr->closed_at],
                    default => ['opened', $pr->opened_at],
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
                    'authorName' => $pr->author?->name,
                ];
            })
            ->sortByDesc(fn (array $pr) => $pr['at'] ?? '')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Offene PRs, an denen etwas zu tun ist: rote CI, Merge-Konflikt, angeforderte
     * Änderungen, ungelöste Kommentare oder zu lange ohne ausreichende Freigabe.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function attention(User $user, array $userIds): array
    {
        $waitingSince = now()->subHours(self::REVIEW_WAIT_HOURS);

        return PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $userIds)
            ->where('state', 'OPEN')
            ->with(['author:id,name', 'approvals'])
            ->get()
            ->map(function (PullRequest $pr) use ($waitingSince) {
                $approval = PullRequestApprovals::status($pr, $pr->approvals);
                $reasons = array_keys(array_filter([
                    'ciFailed' => $pr->ci_state === 'failed',
                    'conflict' => $pr->mergeable === 'CONFLICTING',
                    'changesRequested' => $approval['changesRequested'] > 0,
                    'threads' => (int) $pr->unresolved_threads > 0,
                    'waitingReview' => ! $approval['fulfilled'] && $approval['changesRequested'] === 0
                        && ! $pr->in_merge_queue && $pr->opened_at?->lt($waitingSince),
                ]));
                if ($reasons === []) {
                    return null;
                }

                return [
                    'id' => $pr->id,
                    'number' => $pr->pull_request_id,
                    'title' => $pr->title,
                    'url' => $pr->url(),
                    'authorName' => $pr->author?->name,
                    'reasons' => $reasons,
                    'ciFailedChecks' => $pr->ci_failed_checks ?? [],
                    'openedAt' => $pr->opened_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->sortBy('openedAt')
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
                'prId' => $review->pull_request_id,
                'source' => 'ps-review',
                'number' => $review->pullRequest->pull_request_id,
                'title' => $review->pullRequest->title,
                'url' => route('pull-requests.show', $review->pullRequest),
                'decision' => $review->decision?->value,
                'reviewerName' => $review->reviewer?->name,
                'at' => $review->review_finished_at->toIso8601String(),
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
                'prId' => $approval->pull_request_id,
                'source' => 'github',
                'number' => $approval->pullRequest->pull_request_id,
                'title' => $approval->pullRequest->title,
                'url' => route('pull-requests.show', $approval->pullRequest),
                'decision' => match ($approval->state) {
                    'APPROVED' => 'APPROVE',
                    'CHANGES_REQUESTED' => 'REQUEST_CHANGES',
                    default => null,
                },
                'reviewerName' => $approval->user?->name,
                'at' => $approval->submitted_at->toIso8601String(),
            ]);

        // Je PR nur das letzte Review: Re-Reviews desselben PRs zählen nicht doppelt.
        return $psReviews->concat($approvals)
            ->sortByDesc('at')
            ->unique('prId')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }
}
