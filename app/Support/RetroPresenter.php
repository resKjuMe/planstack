<?php

namespace App\Support;

use App\Enums\ReviewFindingLevel;
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

/**
 * Retro-Ansicht (/retro): Vorschläge für die Fragen der Sprint-Retro — Bewertung
 * in %, was lief hervorragend/gut/schlecht/grausam, was können wir besser machen,
 * was haben wir gelernt.
 *
 * Grundlage sind die Jira-Tickets des letzten abgeschlossenen Sprints (Jira-Zugang
 * des Betrachters) plus PRs und Reviews aus GitHub im Sprint-Zeitraum. Ohne
 * Jira-Sprint gilt der Wochen-Zeitraum aus dem Controller. Die Einordnung folgt
 * festen Regeln; sie ist ein Gesprächsangebot, keine Wertung.
 */
class RetroPresenter
{
    /** Ab so vielen Zeilen gilt ein PR als groß. */
    private const LARGE_PR = 1000;

    /** Ab so vielen Tagen gilt ein offener PR als liegengeblieben. */
    private const STUCK_DAYS = 7;

    /** Ab so vielen Sprints „schleppt" sich ein Ticket. */
    private const MANY_SPRINTS = 3;

    /** Bis zu so vielen Tagen Durchlaufzeit gilt ein Ticket als schnell erledigt. */
    private const FAST_DAYS = 2;

    private const LIST_LIMIT = 8;

    /** Statusnamen, aus denen ein Rücksprung nach „in Arbeit" eine Nacharbeit ist. */
    private const LATE_STATUS = '/done|erledigt|fertig|closed|geschlossen|resolved|gelöst|review|test|qa|abnahme|prüf|verif/i';

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, string $scope, ?int $sprintId, CarbonImmutable $fallbackFrom, CarbonImmutable $fallbackUntil): array
    {
        $userIds = $scope === 'team' ? DailyPresenter::teamUserIds($user) : [$user->id];
        $jira = $this->jira($user, $userIds, $sprintId, $fallbackFrom, $fallbackUntil);

        $from = $jira['from'];
        $until = $jira['until'];
        $issues = $jira['issues'];

        $merged = $this->mergedPullRequests($user, $userIds, $from->utc(), $until->utc());
        $stuckPrs = $this->stuckPullRequests($user, $userIds, $until->utc());
        $critical = $this->criticalFindings($user, $userIds, $from->utc(), $until->utc());
        $reviewsGiven = PullRequestReview::query()
            ->whereIn('reviewer_id', $userIds)
            ->whereBetween('review_finished_at', [$from->utc(), $until->utc()])
            ->count()
            + PullRequestApproval::query()
                ->whereIn('user_id', $userIds)
                ->whereBetween('submitted_at', [$from->utc(), $until->utc()])
                ->count();

        $done = $issues->where('done', true);
        $stats = [
            'committed' => $issues->count(),
            'committedSp' => (float) $issues->sum('sp'),
            'done' => $done->count(),
            'doneSp' => (float) $done->sum('sp'),
            'carryOver' => $issues->where('done', false)->count(),
            'reopened' => $issues->where('reopened', true)->count(),
            'flagged' => $issues->where('wasFlagged', true)->count(),
            'blocked' => $issues->where('blocked', true)->count(),
            'manySprints' => $issues->where('sprintCount', '>=', self::MANY_SPRINTS)->count(),
            'merged' => $merged->count(),
            'firstPass' => $merged->where('changesRequested', false)->count(),
            'changesRequested' => $merged->where('changesRequested', true)->count(),
            'avgOpenDays' => $merged->whereNotNull('openDays')->avg('openDays'),
            'largePrs' => $merged->where('large', true)->count(),
            'reviewsGiven' => $reviewsGiven,
            'stuckPrs' => $stuckPrs->count(),
            'criticalFindings' => $critical->count(),
        ];

        return [
            'jira' => Arr::only($jira, ['connected', 'error']),
            'sprint' => $jira['sprint'],
            'period' => ['from' => $from->toDateString(), 'until' => $until->toDateString()],
            'stats' => $stats,
            'rating' => $this->rating($stats, $done),
            'categories' => $this->categories($stats, $issues, $merged, $stuckPrs),
            'improve' => $this->improvements($stats),
            'learned' => $this->learned($issues, $critical),
        ];
    }

    /**
     * Sprint bestimmen (angefragt, sonst der zuletzt beendete mit meiner bzw. der
     * Team-Beteiligung) und seine Tickets laden.
     *
     * @param  array<int, int>  $userIds
     * @return array<string, mixed>
     */
    private function jira(User $user, array $userIds, ?int $sprintId, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $result = [
            'connected' => $user->hasJiraCredentials(),
            'error' => null,
            'sprint' => null,
            'from' => $from,
            'until' => $until,
            'issues' => collect(),
        ];
        if (! $user->hasJiraCredentials()) {
            return $result;
        }

        try {
            $jira = JiraActivity::for($user);
            $accounts = array_values($jira->accountIds(User::query()->whereIn('id', $userIds)->get()));
            if ($accounts === []) {
                return $result;
            }
            $list = implode(', ', array_map(fn ($id) => '"'.$id.'"', $accounts));

            $sprint = $sprintId !== null
                ? $jira->sprintById($sprintId)
                : $jira->search("assignee in ({$list}) AND sprint in closedSprints() ORDER BY updated DESC", changelog: false)
                    ->flatMap(fn (array $issue) => $issue['sprints'])
                    ->where('state', 'closed')
                    ->filter(fn (array $s) => $s['end'] !== null)
                    ->sortByDesc('end')
                    ->first();

            if ($sprint === null || $sprint['start'] === null || $sprint['end'] === null) {
                $issues = $jira->search("assignee in ({$list}) AND updated >= \"{$from->format('Y-m-d H:i')}\" ORDER BY updated DESC");

                return [...$result, 'issues' => $this->analyse($issues, $from, $until)];
            }

            $from = CarbonImmutable::parse($sprint['start'])->setTimezone(DailyPresenter::TIMEZONE);
            $until = CarbonImmutable::parse($sprint['end'])->setTimezone(DailyPresenter::TIMEZONE);
            $issues = $jira->search("sprint = {$sprint['id']} AND assignee in ({$list}) ORDER BY rank");

            return [
                ...$result,
                'sprint' => [...$sprint, ...$this->neighbours($jira, $sprint)],
                'from' => $from,
                'until' => $until,
                'issues' => $this->analyse($issues, $from, $until),
            ];
        } catch (JiraRequestFailed|ConnectionException $e) {
            return [...$result, 'error' => $e->getMessage()];
        }
    }

    /**
     * Vorheriger und nächster abgeschlossener Sprint desselben Boards.
     *
     * @param  array<string, mixed>  $sprint
     * @return array{prevId: ?int, nextId: ?int}
     */
    private function neighbours(JiraActivity $jira, array $sprint): array
    {
        if ($sprint['boardId'] === null) {
            return ['prevId' => null, 'nextId' => null];
        }

        try {
            $ids = $jira->closedSprints($sprint['boardId'])->pluck('id')->values();
        } catch (JiraRequestFailed|ConnectionException) {
            return ['prevId' => null, 'nextId' => null];
        }
        $index = $ids->search($sprint['id']);

        return [
            'prevId' => $index !== false && $index > 0 ? $ids[$index - 1] : null,
            'nextId' => $index !== false ? ($ids[$index + 1] ?? null) : null,
        ];
    }

    /**
     * Merkmale je Ticket: erledigt im Zeitraum, Rücksprünge, Flags, Durchlaufzeit,
     * Zahl der Sprints.
     *
     * @param  Collection<int, array<string, mixed>>  $issues
     * @return Collection<int, array<string, mixed>>
     */
    private function analyse(Collection $issues, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        $end = $until->addHours(12);

        return $issues->map(function (array $issue) use ($from, $end) {
            $transitions = collect($issue['transitions']);
            $inPeriod = $transitions->filter(fn (array $t) => $t['at']->between($from, $end));
            $done = $issue['category'] === 'done' && ($issue['resolved'] === null || $issue['resolved']->lte($end));
            $started = $transitions->first()['at'] ?? null;
            $finished = $issue['resolved'] ?? ($done ? ($transitions->last()['at'] ?? null) : null);

            return [
                ...DailyPresenter::issueRef($issue),
                'done' => $done,
                'reopened' => $inPeriod->contains(fn (array $t) => preg_match(self::LATE_STATUS, (string) $t['from']) === 1
                    && preg_match(self::LATE_STATUS, (string) $t['to']) !== 1
                    && preg_match(DailyPresenter::BLOCKED_STATUS, (string) $t['to']) !== 1),
                'wasFlagged' => collect($issue['flags'])->contains(fn (array $f) => $f['set'] && $f['at']->between($from, $end))
                    || ($issue['flagged'] && ! $done),
                'blocked' => ! $done && DailyPresenter::isBlocked($issue),
                'sprintCount' => count($issue['sprints']),
                'cycleDays' => $done && $started !== null && $finished !== null
                    ? round(abs($started->diffInHours($finished)) / 24, 1)
                    : null,
            ];
        })->values();
    }

    /**
     * Im Zeitraum gemergte PRs mit Nacharbeits-, Größen- und Laufzeit-Merkmalen.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function mergedPullRequests(User $user, array $userIds, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $userIds)
            ->whereBetween('merged_at', [$from, $until])
            ->withExists(['reviews as ps_changes' => fn (Builder $q) => $q->where('decision', 'REQUEST_CHANGES')])
            ->withExists(['approvals as gh_changes' => fn (Builder $q) => $q->where('state', 'CHANGES_REQUESTED')])
            ->get()
            ->map(fn (PullRequest $pr) => [
                ...$this->prRef($pr),
                'changesRequested' => (bool) ($pr->ps_changes || $pr->gh_changes),
                'large' => (int) $pr->additions + (int) $pr->deletions >= self::LARGE_PR,
                'openDays' => $pr->opened_at !== null ? round(abs($pr->opened_at->diffInHours($pr->merged_at)) / 24, 1) : null,
            ]);
    }

    /**
     * Eigene PRs, die zum Sprint-Ende schon länger als STUCK_DAYS offen sind.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function stuckPullRequests(User $user, array $userIds, CarbonImmutable $until): Collection
    {
        return PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('author_id', $userIds)
            ->where('state', 'OPEN')
            ->where('opened_at', '<', $until->subDays(self::STUCK_DAYS))
            ->orderBy('opened_at')
            ->get()
            ->map(fn (PullRequest $pr) => $this->prRef($pr));
    }

    /**
     * Kritische Befunde aus Reviews an eigenen PRs im Zeitraum.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function criticalFindings(User $user, array $userIds, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return ReviewFinding::query()
            ->where('level', ReviewFindingLevel::CRITICAL)
            ->whereHas('review', fn (Builder $q) => $q
                ->whereBetween('review_finished_at', [$from, $until])
                ->whereHas('pullRequest', fn (Builder $pr) => $pr
                    ->where('organization_id', $user->organization_id)
                    ->whereIn('author_id', $userIds)))
            ->with('review.pullRequest:id,pull_request_id,repository,title')
            ->get()
            ->map(fn (ReviewFinding $finding) => [
                'id' => 'f'.$finding->id,
                'label' => '#'.$finding->review->pullRequest->pull_request_id,
                'title' => $finding->title,
                'url' => route('pull-requests.show', $finding->review->pullRequest),
            ]);
    }

    /**
     * Vorschlag für die Sprint-Bewertung: Mittel aus Lieferquote (erledigte SP
     * gegenüber eingeplanten, ohne SP nach Anzahl), Erstwurf-Quote (PRs ohne
     * angeforderte Änderungen) und Fluss (erledigte Tickets ohne Rücksprung und
     * ohne Flag). Fehlt eine Grundlage, zählt die Komponente nicht mit.
     *
     * @param  array<string, mixed>  $stats
     * @param  Collection<int, array<string, mixed>>  $done
     * @return array<string, mixed>
     */
    private function rating(array $stats, Collection $done): array
    {
        $components = [
            'delivery' => match (true) {
                $stats['committedSp'] > 0 => $stats['doneSp'] / $stats['committedSp'],
                $stats['committed'] > 0 => $stats['done'] / $stats['committed'],
                default => null,
            },
            'quality' => $stats['merged'] > 0 ? $stats['firstPass'] / $stats['merged'] : null,
            'flow' => $done->isNotEmpty()
                ? $done->where('reopened', false)->where('wasFlagged', false)->count() / $done->count()
                : null,
        ];
        $known = array_filter($components, fn ($value) => $value !== null);

        return [
            'percent' => $known !== [] ? (int) round(array_sum($known) / count($known) * 100) : null,
            'components' => array_map(fn ($value) => $value !== null ? (int) round($value * 100) : null, $components),
        ];
    }

    /**
     * Einordnung in hervorragend/gut/schlecht/grausam. Jede Zeile trägt einen
     * Schlüssel (für die Übersetzung) und ihre Belege.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function categories(array $stats, Collection $issues, Collection $merged, Collection $stuckPrs): array
    {
        $done = $issues->where('done', true);
        $fastIssues = $done->where('reopened', false)->where('wasFlagged', false)
            ->filter(fn (array $issue) => $issue['cycleDays'] !== null && $issue['cycleDays'] <= self::FAST_DAYS);
        $fastPrs = $merged->where('changesRequested', false)->filter(fn (array $pr) => $pr['openDays'] !== null && $pr['openDays'] <= 1);
        $firstPass = $merged->where('changesRequested', false)->diffKeys($fastPrs);
        $slow = $merged->filter(fn (array $pr) => $pr['openDays'] !== null && $pr['openDays'] > 5);

        $item = fn (string $key, array $params, Collection $refs) => [
            'key' => $key,
            'params' => $params,
            'refs' => $refs->map(fn (array $ref) => Arr::only($ref, ['id', 'label', 'title', 'url']))->take(self::LIST_LIMIT)->values()->all(),
            'more' => max(0, $refs->count() - self::LIST_LIMIT),
        ];
        $ticket = fn (Collection $rows) => $rows->map(fn (array $issue) => [
            'id' => $issue['key'], 'label' => $issue['key'], 'title' => $issue['summary'], 'url' => $issue['url'],
        ]);
        $sp = fn (float $value) => rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');

        return [
            'excellent' => array_values(array_filter([
                $fastIssues->isNotEmpty() ? $item('fast_issues', ['count' => $fastIssues->count(), 'days' => self::FAST_DAYS], $ticket($fastIssues)) : null,
                $fastPrs->isNotEmpty() ? $item('fast_first_pass', ['count' => $fastPrs->count()], $fastPrs) : null,
                $stats['committed'] > 0 && $stats['carryOver'] === 0
                    ? $item('all_done', ['count' => $stats['committed']], collect())
                    : null,
            ])),
            'good' => array_values(array_filter([
                $done->isNotEmpty() ? $item('delivered', ['count' => $done->count(), 'sp' => $sp($stats['doneSp'])], $ticket($done->sortByDesc('sp'))) : null,
                $firstPass->isNotEmpty() ? $item('first_pass', ['count' => $firstPass->count()], $firstPass) : null,
                $stats['reviewsGiven'] > 0 ? $item('reviews_given', ['count' => $stats['reviewsGiven']], collect()) : null,
            ])),
            'bad' => array_values(array_filter([
                $stats['changesRequested'] > 0
                    ? $item('changes_requested', ['count' => $stats['changesRequested'], 'total' => $stats['merged']], $merged->where('changesRequested', true))
                    : null,
                $stats['reopened'] > 0 ? $item('reopened', ['count' => $stats['reopened']], $ticket($issues->where('reopened', true))) : null,
                $done->where('wasFlagged', true)->isNotEmpty()
                    ? $item('flagged_resolved', ['count' => $done->where('wasFlagged', true)->count()], $ticket($done->where('wasFlagged', true)))
                    : null,
                $stats['largePrs'] > 0 ? $item('large_prs', ['count' => $stats['largePrs'], 'lines' => self::LARGE_PR], $merged->where('large', true)) : null,
                $slow->isNotEmpty() ? $item('slow_merge', ['count' => $slow->count()], $slow->sortByDesc('openDays')) : null,
            ])),
            'terrible' => array_values(array_filter([
                $stats['carryOver'] > 0
                    ? $item('carry_over', ['count' => $stats['carryOver'], 'total' => $stats['committed']], $ticket($issues->where('done', false)))
                    : null,
                $stats['blocked'] > 0 ? $item('still_blocked', ['count' => $stats['blocked']], $ticket($issues->where('blocked', true))) : null,
                $stats['manySprints'] > 0
                    ? $item('many_sprints', ['count' => $stats['manySprints'], 'sprints' => self::MANY_SPRINTS], $ticket($issues->where('sprintCount', '>=', self::MANY_SPRINTS)))
                    : null,
                $stuckPrs->isNotEmpty() ? $item('stuck_prs', ['count' => $stuckPrs->count(), 'days' => self::STUCK_DAYS], $stuckPrs) : null,
                $stats['criticalFindings'] > 0 ? $item('critical_findings', ['count' => $stats['criticalFindings']], collect()) : null,
            ])),
        ];
    }

    /**
     * Verbesserungsvorschläge aus auffälligen Signalen, jeweils mit Beleg.
     *
     * @param  array<string, mixed>  $stats
     * @return array<int, array{key: string, params: array<string, mixed>}>
     */
    private function improvements(array $stats): array
    {
        $delivery = $stats['committedSp'] > 0
            ? $stats['doneSp'] / $stats['committedSp']
            : ($stats['committed'] > 0 ? $stats['done'] / $stats['committed'] : 1);
        $number = fn (?float $value) => $value !== null ? rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',') : '0';

        $rules = [
            'plan_less' => $delivery < 0.7,
            'definition_of_done' => $stats['reopened'] > 0,
            'split_tickets' => $stats['manySprints'] > 0,
            'clarify_tickets' => $stats['flagged'] > 0,
            'self_review' => $stats['merged'] > 0 && $stats['changesRequested'] / $stats['merged'] > 0.3,
            'faster_reviews' => ($stats['avgOpenDays'] ?? 0) > 2,
            'smaller_prs' => $stats['largePrs'] > 0,
            'finish_stuck' => $stats['stuckPrs'] > 0,
            'critical_checklist' => $stats['criticalFindings'] > 0,
        ];

        $params = [
            'done' => $stats['committedSp'] > 0 ? $number($stats['doneSp']) : $stats['done'],
            'committed' => $stats['committedSp'] > 0 ? $number($stats['committedSp']) : $stats['committed'],
            'unit' => $stats['committedSp'] > 0 ? 'SP' : 'Tickets',
            'reopened' => $stats['reopened'],
            'many' => $stats['manySprints'],
            'sprints' => self::MANY_SPRINTS,
            'flagged' => $stats['flagged'],
            'changes' => $stats['changesRequested'],
            'merged' => $stats['merged'],
            'days' => $number($stats['avgOpenDays']),
            'large' => $stats['largePrs'],
            'lines' => self::LARGE_PR,
            'stuck' => $stats['stuckPrs'],
            'critical' => $stats['criticalFindings'],
        ];

        return collect($rules)
            ->filter()
            ->keys()
            ->map(fn (string $key) => ['key' => $key, 'params' => $params])
            ->values()
            ->all();
    }

    /**
     * Was wir gelernt haben: Rückläufer, Blockaden, Tickets, die deutlich länger
     * als üblich gedauert haben, und kritische Review-Befunde.
     *
     * @param  Collection<int, array<string, mixed>>  $issues
     * @param  Collection<int, array<string, mixed>>  $critical
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function learned(Collection $issues, Collection $critical): array
    {
        $cycles = $issues->pluck('cycleDays')->filter(fn ($days) => $days !== null)->sort()->values();
        $median = $cycles->isNotEmpty() ? $cycles->median() : null;
        $ref = fn (array $issue) => ['id' => $issue['key'], 'label' => $issue['key'], 'title' => $issue['summary'], 'url' => $issue['url']];

        return [
            'reopened' => $issues->where('reopened', true)->map($ref)->take(self::LIST_LIMIT)->values()->all(),
            'flagged' => $issues->where('wasFlagged', true)->map($ref)->take(self::LIST_LIMIT)->values()->all(),
            'slow' => $median !== null && $cycles->count() >= 3
                ? $issues->filter(fn (array $issue) => $issue['cycleDays'] !== null && $issue['cycleDays'] >= 3 && $issue['cycleDays'] > 2 * $median)
                    ->map(fn (array $issue) => [...$ref($issue), 'days' => $issue['cycleDays']])
                    ->take(self::LIST_LIMIT)->values()->all()
                : [],
            'findings' => $critical->take(self::LIST_LIMIT)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prRef(PullRequest $pr): array
    {
        return [
            'id' => 'p'.$pr->id,
            'label' => '#'.$pr->pull_request_id,
            'title' => $pr->title,
            'url' => route('pull-requests.show', $pr),
        ];
    }
}
