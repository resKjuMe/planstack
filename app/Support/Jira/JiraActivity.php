<?php

namespace App\Support\Jira;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Lesender Jira-Zugriff für Daily und Retro, mit dem Jira-Zugang des Betrachters.
 * Liefert Tickets normalisiert: Status, Zuständige, Story Points, Flag, Sprints
 * und die Statuswechsel aus dem Änderungsverlauf.
 */
class JiraActivity
{
    private const PAGE = 100;

    private const MAX_PAGES = 3;

    private const STORY_POINT_FIELDS = ['Story Points', 'Story point estimate', 'Story-Punkte', 'Storypoints'];

    /** Fallback für instanzeigene Namen: Zahlenfeld, das nach Punkten/Schätzung klingt. */
    private const STORY_POINT_PATTERN = '/story.?point|punkte|estimat|schätz|aufwand/i';

    private const FLAG_FIELDS = ['Flagged', 'Markiert', 'Gekennzeichnet'];

    private const SPRINT_SCHEMA = 'com.pyxis.greenhopper.jira:gh-sprint';

    private ?PendingRequest $client = null;

    /** @var array{storyPoints: ?string, flagged: ?string, sprint: ?string}|null */
    private ?array $fields = null;

    public function __construct(
        private readonly JiraStatusSync $sync,
        private readonly User $viewer,
    ) {}

    public static function for(User $viewer): self
    {
        return new self(app(JiraStatusSync::class), $viewer);
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->viewer->jira_base_url, '/');
    }

    /**
     * Jira-Accounts der Nutzer (gecacht wie beim Status-Sync); ohne Treffer fehlt
     * der Nutzer im Ergebnis.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, string> Planstack-User-ID => accountId
     */
    public function accountIds(Collection $users): array
    {
        return $users
            ->mapWithKeys(fn (User $user) => [$user->id => $this->sync->accountId($this->client(), $this->viewer, $user)])
            ->filter()
            ->all();
    }

    /**
     * Jira-Avatar (48px) eines Planstack-Nutzers; einen Tag gecacht, null ohne
     * Jira-Account.
     */
    public function avatar(User $person): ?string
    {
        $cacheKey = 'jira-avatar:'.md5($this->baseUrl().'|'.$person->id);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $account = $this->sync->accountId($this->client(), $this->viewer, $person);
        $avatar = null;
        if ($account !== null) {
            $response = $this->client()->get('/rest/api/3/user', ['accountId' => $account]);
            $avatar = $response->successful() ? $response->json('avatarUrls.48x48') : null;
        }

        Cache::put($cacheKey, $avatar ?? '', now()->addDay());

        return $avatar;
    }

    /**
     * Titel zu Jira-Keys (je Key eine Stunde gecacht). Unbekannte Keys fehlen im
     * Ergebnis; scheitert die Sammelabfrage an einem ungültigen Key, wird einzeln
     * nachgefragt.
     *
     * @param  array<int, string>  $keys
     * @return array<string, string> Key => Titel
     */
    public function summaries(array $keys): array
    {
        $cacheKey = fn (string $key) => 'jira-summary:'.md5($this->baseUrl().'|'.$key);
        $result = [];
        $missing = [];
        foreach (array_unique(array_map('strtoupper', $keys)) as $key) {
            $cached = Cache::get($cacheKey($key));
            if ($cached === null) {
                $missing[] = $key;
            } elseif ($cached !== '') {
                $result[$key] = $cached;
            }
        }

        foreach (array_chunk($missing, 100) as $chunk) {
            $response = $this->client()->post('/rest/api/3/search/jql', [
                'jql' => 'key in ('.implode(',', $chunk).')',
                'fields' => ['summary'],
                'maxResults' => 100,
            ]);
            $found = [];
            if ($response->successful()) {
                foreach ($response->json('issues', []) as $issue) {
                    $found[strtoupper($issue['key'])] = (string) data_get($issue, 'fields.summary', '');
                }
            } else {
                foreach ($chunk as $key) {
                    $single = $this->client()->get("/rest/api/3/issue/{$key}", ['fields' => 'summary']);
                    if ($single->successful()) {
                        $found[$key] = (string) $single->json('fields.summary', '');
                    }
                }
            }

            foreach ($chunk as $key) {
                Cache::put($cacheKey($key), $found[$key] ?? '', now()->addHour());
                if (isset($found[$key])) {
                    $result[$key] = $found[$key];
                }
            }
        }

        return $result;
    }

    /**
     * Tickets per JQL, inklusive Änderungsverlauf.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $jql, bool $changelog = true): Collection
    {
        $fields = $this->fields();
        $wanted = array_values(array_filter([
            'summary', 'status', 'assignee', 'reporter', 'issuetype', 'updated', 'created', 'resolutiondate',
            $fields['storyPoints'], $fields['flagged'], $fields['sprint'],
        ]));

        $issues = collect();
        $token = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->client()->post('/rest/api/3/search/jql', array_filter([
                'jql' => $jql,
                'fields' => $wanted,
                'expand' => $changelog ? 'changelog' : null,
                'maxResults' => self::PAGE,
                'nextPageToken' => $token,
            ]));
            $this->assertOk($response);

            $issues = $issues->concat(collect($response->json('issues', []))->map(fn (array $issue) => $this->normalize($issue)));
            $token = $response->json('nextPageToken');
            if (! $token || $response->json('isLast') === true) {
                break;
            }
        }

        return $issues->values();
    }

    /**
     * Ein Sprint per ID (Agile-API).
     *
     * @return array<string, mixed>
     */
    public function sprintById(int $id): array
    {
        $response = $this->client()->get("/rest/agile/1.0/sprint/{$id}");
        $this->assertOk($response);

        return $this->sprint($response->json());
    }

    /**
     * Abgeschlossene Sprints eines Boards, ältester zuerst.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function closedSprints(int $boardId): Collection
    {
        return collect(Cache::remember('jira-sprints:'.md5($this->baseUrl().'|'.$boardId), now()->addHour(), function () use ($boardId) {
            $sprints = collect();
            for ($startAt = 0, $page = 0; $page < 10; $page++, $startAt += 50) {
                $response = $this->client()->get("/rest/agile/1.0/board/{$boardId}/sprint", [
                    'state' => 'closed',
                    'startAt' => $startAt,
                    'maxResults' => 50,
                ]);
                $this->assertOk($response);
                $sprints = $sprints->concat($response->json('values', []));
                if ($response->json('isLast', true)) {
                    break;
                }
            }

            return $sprints
                ->map(fn (array $sprint) => $this->sprint($sprint))
                ->filter(fn (array $sprint) => $sprint['end'] !== null)
                ->sortBy('end')
                ->values()
                ->all();
        }));
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    private function normalize(array $issue): array
    {
        $fields = $this->fields();
        $f = $issue['fields'] ?? [];
        $sp = $fields['storyPoints'] !== null ? ($f[$fields['storyPoints']] ?? null) : null;

        $histories = collect(data_get($issue, 'changelog.histories', []))->sortBy('created')->values();
        $transitions = $histories->flatMap(fn (array $history) => collect($history['items'] ?? [])
            ->where('field', 'status')
            ->map(fn (array $item) => [
                'at' => CarbonImmutable::parse($history['created']),
                'author' => data_get($history, 'author.accountId'),
                'from' => $item['fromString'] ?? null,
                'to' => $item['toString'] ?? null,
            ]))->values();
        $flags = $histories->flatMap(fn (array $history) => collect($history['items'] ?? [])
            ->filter(fn (array $item) => in_array($item['field'] ?? '', self::FLAG_FIELDS, true))
            ->map(fn (array $item) => [
                'at' => CarbonImmutable::parse($history['created']),
                'set' => filled($item['toString'] ?? null),
            ]))->values();

        return [
            'key' => $issue['key'],
            'summary' => $f['summary'] ?? '',
            'url' => $this->baseUrl().'/browse/'.$issue['key'],
            'type' => data_get($f, 'issuetype.name'),
            'status' => data_get($f, 'status.name'),
            'category' => data_get($f, 'status.statusCategory.key'),
            'assignee' => data_get($f, 'assignee.accountId'),
            'assigneeName' => data_get($f, 'assignee.displayName'),
            'reporter' => data_get($f, 'reporter.accountId'),
            'sp' => is_numeric($sp) ? (float) $sp : null,
            'flagged' => $fields['flagged'] !== null && filled($f[$fields['flagged']] ?? null),
            'sprints' => collect($fields['sprint'] !== null ? ($f[$fields['sprint']] ?? []) : [])
                ->filter(fn ($sprint) => is_array($sprint))
                ->map(fn (array $sprint) => $this->sprint($sprint))
                ->values()
                ->all(),
            'updated' => isset($f['updated']) ? CarbonImmutable::parse($f['updated']) : null,
            'resolved' => isset($f['resolutiondate']) ? CarbonImmutable::parse($f['resolutiondate']) : null,
            'transitions' => $transitions->all(),
            'flags' => $flags->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $sprint
     * @return array<string, mixed>
     */
    private function sprint(array $sprint): array
    {
        $end = $sprint['completeDate'] ?? $sprint['endDate'] ?? null;

        return [
            'id' => (int) $sprint['id'],
            'name' => (string) ($sprint['name'] ?? ''),
            'state' => $sprint['state'] ?? null,
            'goal' => filled($sprint['goal'] ?? null) ? $sprint['goal'] : null,
            'boardId' => isset($sprint['boardId']) ? (int) $sprint['boardId'] : (isset($sprint['originBoardId']) ? (int) $sprint['originBoardId'] : null),
            'start' => isset($sprint['startDate']) ? CarbonImmutable::parse($sprint['startDate'])->toIso8601String() : null,
            'end' => $end !== null ? CarbonImmutable::parse($end)->toIso8601String() : null,
        ];
    }

    /**
     * Feld-IDs für Story Points, Flag und Sprint (instanzabhängige Custom Fields).
     *
     * @return array{storyPoints: ?string, flagged: ?string, sprint: ?string}
     */
    private function fields(): array
    {
        return $this->fields ??= Cache::remember('jira-fields:v2:'.md5($this->baseUrl()), now()->addDay(), function () {
            $response = $this->client()->get('/rest/api/3/field');
            $this->assertOk($response);
            $all = collect($response->json());

            return [
                'storyPoints' => ($all->first(fn ($f) => in_array($f['name'] ?? '', self::STORY_POINT_FIELDS, true))
                    ?? $all->first(fn ($f) => data_get($f, 'schema.type') === 'number'
                        && str_starts_with((string) ($f['id'] ?? ''), 'customfield_')
                        && preg_match(self::STORY_POINT_PATTERN, (string) ($f['name'] ?? '')) === 1))['id'] ?? null,
                'flagged' => $all->first(fn ($f) => in_array($f['name'] ?? '', self::FLAG_FIELDS, true))['id'] ?? null,
                'sprint' => $all->first(fn ($f) => data_get($f, 'schema.custom') === self::SPRINT_SCHEMA)['id'] ?? null,
            ];
        });
    }

    private function client(): PendingRequest
    {
        return $this->client ??= $this->sync->client($this->viewer);
    }

    private function assertOk(Response $response): void
    {
        if (! $response->successful()) {
            throw new JiraRequestFailed(match ($response->status()) {
                401 => __('flash.jira_sync_unauthorized'),
                403 => __('flash.jira_sync_forbidden'),
                default => 'Jira HTTP '.$response->status(),
            });
        }
    }
}
