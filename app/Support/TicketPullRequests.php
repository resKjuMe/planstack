<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\User;
use App\Support\Jira\JiraActivity;
use App\Support\Jira\JiraRequestFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

/**
 * PRs zu Jira-Tickets: ein PR gehört zu einem Ticket, wenn Titel oder Branch den
 * Jira-Key enthalten. Liefert je Key die PRs mit Ersteller (Name + Jira-Avatar).
 */
class TicketPullRequests
{
    /** Nur PRs aus diesem Zeitraum werden durchsucht. */
    private const LOOKBACK_DAYS = 180;

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

        $byKey = [];
        PullRequest::query()
            ->where('organization_id', $viewer->organization_id)
            ->where(fn ($q) => $q->where('opened_at', '>=', now()->subDays(self::LOOKBACK_DAYS))->orWhereNull('opened_at'))
            ->with('author:id,name,email')
            ->orderByDesc('opened_at')
            ->get(['id', 'repository', 'pull_request_id', 'title', 'branch', 'state', 'author_id', 'author_login', 'opened_at'])
            ->each(function (PullRequest $pr) use ($pattern, &$byKey) {
                if (preg_match_all($pattern, $pr->title.' '.$pr->branch, $matches) === 0) {
                    return;
                }
                foreach (array_unique(array_map('strtoupper', $matches[1])) as $key) {
                    $byKey[$key][] = $pr;
                }
            });

        if ($byKey === []) {
            return [];
        }

        $jira = $viewer->hasJiraCredentials() ? JiraActivity::for($viewer) : null;
        $avatars = [];
        $avatarOf = function (?User $author) use ($jira, &$avatars): ?string {
            if ($author === null || $jira === null) {
                return null;
            }
            if (! array_key_exists($author->id, $avatars)) {
                try {
                    $avatars[$author->id] = $jira->avatar($author);
                } catch (JiraRequestFailed|ConnectionException) {
                    $avatars[$author->id] = null;
                }
            }

            return $avatars[$author->id];
        };

        return collect($byKey)
            ->map(fn (array $prs) => collect($prs)->map(fn (PullRequest $pr) => [
                'number' => $pr->pull_request_id,
                'url' => route('pull-requests.show', $pr),
                'state' => $pr->state->value,
                'author' => $pr->author?->name ?? $pr->author_login,
                'initials' => Str::of($pr->author?->name ?? (string) $pr->author_login)
                    ->explode(' ')->filter()->take(2)
                    ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))->implode(''),
                'avatar' => $avatarOf($pr->author),
            ])->values()->all())
            ->all();
    }
}
