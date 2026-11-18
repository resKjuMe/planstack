<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\User;
use App\Support\Jira\JiraActivity;
use App\Support\Jira\JiraRequestFailed;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Verbindung zwischen PRs und Jira-Tickets über den Jira-Key in Titel oder Branch:
 * - {@see forKeys()}: je Ticket die PRs samt Ersteller (Name + Jira-Avatar);
 * - {@see ticketsFor()}: je PR die Tickets mit Jira-Titel, damit Listen das
 *   Ticket statt des PR-Titels zeigen.
 */
class TicketPullRequests
{
    /** Nur PRs aus diesem Zeitraum werden durchsucht. */
    private const LOOKBACK_DAYS = 180;

    /** Jira-Key, z. B. MN-1234 (auch klein geschrieben im Branch). */
    private const KEY = '/(?<![A-Za-z0-9])([A-Za-z][A-Za-z0-9]+-\d+)(?![0-9])/';

    /**
     * @param  array<int, string>  $keys
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function forKeys(User $viewer, array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        if ($keys === []) {
            return [];
        }

        $pattern = '/(?<![A-Z0-9])('.implode('|', array_map(fn (string $key) => preg_quote($key, '/'), $keys)).')(?![0-9])/i';

        // Erst nur Titel/Branch durchsuchen, dann die Treffer vollständig laden.
        $idsByKey = [];
        PullRequest::query()
            ->where('organization_id', $viewer->organization_id)
            ->where(fn ($q) => $q->where('opened_at', '>=', now()->subDays(self::LOOKBACK_DAYS))->orWhereNull('opened_at'))
            ->orderByDesc('opened_at')
            ->get(['id', 'title', 'branch'])
            ->each(function (PullRequest $pr) use ($pattern, &$idsByKey) {
                if (preg_match_all($pattern, $pr->title.' '.$pr->branch, $matches) === 0) {
                    return;
                }
                foreach (array_unique(array_map('strtoupper', $matches[1])) as $key) {
                    $idsByKey[$key][] = $pr->id;
                }
            });

        $pullRequests = PullRequest::query()->whereIn('id', collect($idsByKey)->flatten()->unique()->all())->get();
        self::prepare($pullRequests);
        $jira = self::jira($viewer);
        $entries = $pullRequests->mapWithKeys(fn (PullRequest $pr) => [$pr->id => self::entry($pr, $jira)]);

        return collect($idsByKey)
            ->map(fn (array $ids) => collect($ids)->map(fn (int $id) => $entries[$id])->values()->all())
            ->all();
    }

    /**
     * Je PR-ID die Tickets (Key, Jira-Titel, Link) und der PR-Eintrag mit Ersteller.
     * Keys, die Jira nicht kennt, fallen weg; ohne Jira-Zugang bleibt `tickets` leer.
     *
     * @param  Collection<int, PullRequest>  $pullRequests  mit geladenem `author`
     * @return array<int, array{tickets: array<int, array<string, string>>, pr: array<string, mixed>}>
     */
    public static function ticketsFor(User $viewer, Collection $pullRequests): array
    {
        if ($pullRequests instanceof EloquentCollection) {
            self::prepare($pullRequests);
        }
        $jira = self::jira($viewer);
        $keysById = $pullRequests->mapWithKeys(function (PullRequest $pr) {
            preg_match_all(self::KEY, $pr->title.' '.$pr->branch, $matches);

            return [$pr->id => array_values(array_unique(array_map('strtoupper', $matches[1])))];
        });

        $summaries = [];
        if ($jira !== null) {
            try {
                $summaries = $jira->summaries($keysById->flatten()->all());
            } catch (JiraRequestFailed|ConnectionException) {
                $summaries = [];
            }
        }

        return $pullRequests->mapWithKeys(fn (PullRequest $pr) => [$pr->id => [
            'tickets' => collect($keysById[$pr->id])
                ->filter(fn (string $key) => isset($summaries[$key]))
                ->map(fn (string $key) => [
                    'key' => $key,
                    'summary' => $summaries[$key],
                    'url' => $jira->baseUrl().'/browse/'.$key,
                ])
                ->values()
                ->all(),
            'pr' => self::entry($pr, $jira),
        ]])->all();
    }

    /**
     * PR-Eintrag mit Ersteller (Name, Initialen, Jira-Avatar) und, sofern die
     * Relationen geladen sind, den Spalten der PR-Liste unter `details`.
     *
     * @return array<string, mixed>
     */
    public static function entry(PullRequest $pr, ?JiraActivity $jira): array
    {
        $name = $pr->author?->name ?? (string) $pr->author_login;
        $avatar = null;
        if ($jira !== null && $pr->author !== null) {
            try {
                $avatar = $jira->avatar($pr->author);
            } catch (JiraRequestFailed|ConnectionException) {
                $avatar = null;
            }
        }

        return [
            'number' => $pr->pull_request_id,
            'url' => route('pull-requests.show', $pr),
            'state' => $pr->state->value,
            'author' => $name,
            'initials' => Str::of($name)->explode(' ')->filter()->take(2)
                ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))->implode(''),
            'avatar' => $avatar,
            'details' => $pr->relationLoaded('latestReview') && $pr->relationLoaded('approvals') ? PullRequestRows::full($pr) : null,
        ];
    }

    /**
     * @param  EloquentCollection<int, PullRequest>  $pullRequests
     */
    private static function prepare(EloquentCollection $pullRequests): void
    {
        $pullRequests->loadMissing(PullRequestRows::RELATIONS);
        PullRequestRows::withPoCounts($pullRequests);
    }

    private static function jira(User $viewer): ?JiraActivity
    {
        return $viewer->hasJiraCredentials() ? JiraActivity::for($viewer) : null;
    }
}
