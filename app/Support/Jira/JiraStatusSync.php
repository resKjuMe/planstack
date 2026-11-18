<?php

namespace App\Support\Jira;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatusHistory;
use App\Support\TaskStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Gleicht Status und Assignee verknüpfter Tasks (Jira-Unteraufgaben) in beide
 * Richtungen mit Jira ab — mit dem persönlichen Jira-Zugang des Nutzers, der den
 * Abgleich am Projekt eingeschaltet hat.
 *
 * 3-Wege-Abgleich je Task gegen den zuletzt gemeinsamen Stand (`jira_sync_base`):
 * hat sich nur eine Seite geändert, gewinnt sie; haben sich beide geändert,
 * gewinnt die jüngere Änderung. Ohne gemeinsamen Stand (erster Lauf) gewinnt
 * Planstack. Phasen (Jira-Aufgaben) haben in Planstack keinen eigenen Status und
 * werden nur nach Jira übertragen.
 */
class JiraStatusSync
{
    public const TODO = 'todo';

    public const PROGRESS = 'progress';

    public const REVIEW = 'review';

    public const DONE = 'done';

    private const ROLE_FOR_CLASS = [
        self::TODO => StatusRole::PICKABLE,
        self::PROGRESS => StatusRole::IN_PROGRESS,
        self::REVIEW => StatusRole::IN_REVIEW,
        self::DONE => StatusRole::MERGED,
    ];

    /** @var array{checked: int, transitioned: int, assigned: int, imported: int, errors: array<int, string>} */
    private array $result;

    public function __construct(
        private readonly TaskStatusService $statuses,
        private readonly TaskStatusHistory $history,
    ) {}

    /**
     * @return array{checked: int, transitioned: int, assigned: int, imported: int, errors: array<int, string>}
     */
    public function sync(Project $project): array
    {
        $this->result = ['checked' => 0, 'transitioned' => 0, 'assigned' => 0, 'imported' => 0, 'errors' => []];
        $user = $project->jiraSyncUser;

        if (! $user || ! $user->hasJiraCredentials()) {
            $this->result['errors'][] = __('flash.jira_sync_no_credentials');

            return $this->result;
        }

        $client = $this->client($user);
        $tasks = $project->tasks()->whereNotNull('jira_key')->with(['orgStatus', 'claimer'])->get();
        $phases = $project->phases()->whereNotNull('jira_key')->get();

        if ($tasks->isEmpty() && $phases->isEmpty()) {
            return $this->result;
        }

        try {
            $current = $this->current($client, [...$tasks->pluck('jira_key'), ...$phases->pluck('jira_key')]);

            foreach ($tasks as $task) {
                $this->syncTask($client, $user, $project, $task, $current[$task->jira_key] ?? null);
            }

            $tasks = $project->tasks()->with(['orgStatus', 'claimer'])->get();
            foreach ($phases as $phase) {
                $children = $tasks->where('phase_id', $phase->id);
                $this->pushPhase($client, $user, $phase->jira_key, $children, $current[$phase->jira_key] ?? null);
            }
        } catch (ConnectionException|JiraRequestFailed $e) {
            $this->result['errors'][] = $e->getMessage();
        }

        return $this->result;
    }

    /**
     * @param  array{0: string, 1: string, 2: string|null}|null  $jira  [Statusname, Kategorie, accountId]
     */
    private function syncTask(PendingRequest $client, User $syncUser, Project $project, Task $task, ?array $jira): void
    {
        $key = $task->jira_key;
        if ($jira === null) {
            $this->result['errors'][] = "{$key}: nicht gefunden";

            return;
        }
        $this->result['checked']++;

        [$statusName, $category, $jiraAccount] = $jira;
        $base = $task->jira_sync_base;
        $psClass = self::classOfTask($task);
        $jiraClass = $this->isRejected($statusName) ? null : $this->classify($statusName, $category);
        $psAccount = $task->claimer ? $this->accountId($client, $syncUser, $task->claimer) : null;

        if ($task->claimer && $psAccount === null) {
            $this->result['errors'][] = "{$key}: Jira-Nutzer zu {$task->claimer->name} nicht gefunden";
        }

        // --- Status
        $winner = $this->winner(
            psChanged: $base === null || $psClass !== ($base['class'] ?? null),
            jiraChanged: $base !== null && $jiraClass !== null && $jiraClass !== ($base['class'] ?? null),
            differs: $jiraClass !== null && $psClass !== $jiraClass,
            psAt: fn () => $this->planstackStatusChangedAt($task),
            jiraAt: fn () => $this->jiraChangedAt($client, $key, 'status'),
        );
        $finalClass = $psClass;

        if ($winner === 'planstack' && $jiraClass !== null && $psClass !== $jiraClass) {
            $this->transition($client, $key, $psClass)
                ? $this->result['transitioned']++
                : $this->result['errors'][] = "{$key}: kein Übergang";
            // Jira-Automationen setzen beim Übergang oft den Ausführenden als Assignee.
            $jiraAccount = $this->assigneeOf($client, $key);
        } elseif ($winner === 'jira') {
            $this->importStatus($task, $jiraClass);
            $finalClass = $jiraClass;
            $this->result['imported']++;
        }

        // --- Assignee
        $winner = $this->winner(
            psChanged: $base === null || $psAccount !== ($base['account'] ?? null),
            jiraChanged: $base !== null && $jiraAccount !== ($base['account'] ?? null),
            differs: $psAccount !== $jiraAccount,
            psAt: fn () => $task->claimed_at ? CarbonImmutable::parse($task->claimed_at) : CarbonImmutable::parse($task->updated_at),
            jiraAt: fn () => $this->jiraChangedAt($client, $key, 'assignee'),
        );
        $finalAccount = $psAccount;

        if ($winner === 'planstack' && $psAccount !== $jiraAccount && ! ($task->claimer && $psAccount === null)) {
            $this->assign($client, $key, $psAccount);
        } elseif ($winner === 'jira') {
            $person = $jiraAccount ? $this->userForAccount($client, $syncUser, $project, $jiraAccount) : null;
            if ($jiraAccount && ! $person) {
                $this->result['errors'][] = "{$key}: Jira-Assignee ist kein Planstack-Nutzer der Organisation";
                $finalAccount = $base['account'] ?? $psAccount;
            } else {
                $task->forceFill(['claimed_by_id' => $person?->id, 'claimed_at' => $person ? now() : null])->save();
                $finalAccount = $jiraAccount;
                $this->result['imported']++;
            }
        }

        $task->forceFill(['jira_sync_base' => ['class' => $finalClass, 'account' => $finalAccount]])->saveQuietly();
    }

    /**
     * 'planstack' | 'jira' | null (nichts zu tun).
     */
    private function winner(bool $psChanged, bool $jiraChanged, bool $differs, \Closure $psAt, \Closure $jiraAt): ?string
    {
        if (! $differs) {
            return null;
        }
        if ($psChanged && $jiraChanged) {
            $jira = $jiraAt();

            return $jira !== null && $jira->greaterThan($psAt()) ? 'jira' : 'planstack';
        }
        if ($jiraChanged) {
            return 'jira';
        }

        return 'planstack';
    }

    /**
     * @param  Collection<int, Task>  $children
     * @param  array{0: string, 1: string, 2: string|null}|null  $jira
     */
    private function pushPhase(PendingRequest $client, User $syncUser, string $key, Collection $children, ?array $jira): void
    {
        if ($jira === null) {
            $this->result['errors'][] = "{$key}: nicht gefunden";

            return;
        }
        $this->result['checked']++;
        [$statusName, $category, $jiraAccount] = $jira;

        $classes = $children->map(fn (Task $t) => self::classOfTask($t));
        $wanted = match (true) {
            $classes->isNotEmpty() && $classes->every(fn ($c) => $c === self::DONE) => self::DONE,
            $classes->contains(fn ($c) => $c !== self::TODO) => self::PROGRESS,
            default => self::TODO,
        };

        if (! $this->isRejected($statusName) && $this->classify($statusName, $category) !== $wanted) {
            $this->transition($client, $key, $wanted)
                ? $this->result['transitioned']++
                : $this->result['errors'][] = "{$key}: kein Übergang";
            $jiraAccount = $this->assigneeOf($client, $key);
        }

        $people = $children->pluck('claimer')->filter()->unique('id');
        $account = $people->count() === 1 ? $this->accountId($client, $syncUser, $people->first()) : null;
        if ($account !== $jiraAccount && ! ($people->count() === 1 && $account === null)) {
            $this->assign($client, $key, $account);
        }
    }

    private function importStatus(Task $task, string $class): void
    {
        $status = $task->project?->organization?->statusForRole(self::ROLE_FOR_CLASS[$class]);
        if ($status === null) {
            return;
        }
        // Bewusst ohne Übergangs- und Event-Prüfung: die Änderung kommt aus Jira.
        $claim = ['claimed_by_id' => $task->claimed_by_id, 'claimed_at' => $task->claimed_at];
        $task->update([...$this->statuses->attributesFor($task, $status), ...$claim]);
        $task->load('orgStatus');
    }

    private function assign(PendingRequest $client, string $key, ?string $account): void
    {
        $response = $client->put("/rest/api/3/issue/{$key}/assignee", ['accountId' => $account]);
        $response->successful()
            ? $this->result['assigned']++
            : $this->result['errors'][] = "{$key}: Assignee HTTP {$response->status()}";
    }

    public static function classOfTask(Task $task): string
    {
        $kind = $task->orgStatus?->kind ?? $task->orgStatus?->role?->kind();

        return match ($kind) {
            'done' => self::DONE,
            'review' => self::REVIEW,
            'active' => self::PROGRESS,
            default => self::TODO,
        };
    }

    /**
     * Statusklasse eines Jira-Status aus Kategorie und Name.
     */
    public function classify(string $name, string $category): string
    {
        return match (true) {
            $category === 'done' => self::DONE,
            $category === 'new' => self::TODO,
            str_contains(mb_strtolower($name), 'review') => self::REVIEW,
            default => self::PROGRESS,
        };
    }

    private function isRejected(string $name): bool
    {
        return in_array(mb_strtolower($name), ['abgelehnt', 'rejected', "won't do", 'declined'], true);
    }

    private function planstackStatusChangedAt(Task $task): CarbonImmutable
    {
        $events = $this->history->changeEvents(collect([$task->id]));

        return $events !== [] ? end($events)['at'] : CarbonImmutable::parse($task->updated_at);
    }

    /**
     * Zeitpunkt der letzten Änderung von $field (status|assignee) laut Changelog.
     */
    private function jiraChangedAt(PendingRequest $client, string $key, string $field): ?CarbonImmutable
    {
        $response = $client->get("/rest/api/3/issue/{$key}/changelog", ['maxResults' => 100, 'startAt' => 0]);
        if (! $response->successful()) {
            return null;
        }
        $total = (int) $response->json('total', 0);
        if ($total > 100) {
            $response = $client->get("/rest/api/3/issue/{$key}/changelog", ['maxResults' => 100, 'startAt' => $total - 100]);
        }

        $latest = null;
        foreach ($response->json('values', []) as $entry) {
            foreach ($entry['items'] ?? [] as $item) {
                if (($item['field'] ?? '') === $field) {
                    $at = CarbonImmutable::parse($entry['created']);
                    $latest = $latest === null || $at->greaterThan($latest) ? $at : $latest;
                }
            }
        }

        return $latest;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    private function current(PendingRequest $client, array $keys): array
    {
        $current = [];

        foreach (array_chunk($keys, 100) as $chunk) {
            $response = $client->post('/rest/api/3/search/jql', [
                'jql' => 'key in ('.implode(',', $chunk).')',
                'fields' => ['status', 'assignee'],
                'maxResults' => 100,
            ]);
            $this->assertOk($response);

            foreach ($response->json('issues', []) as $issue) {
                $current[$issue['key']] = [
                    (string) data_get($issue, 'fields.status.name'),
                    (string) data_get($issue, 'fields.status.statusCategory.key'),
                    data_get($issue, 'fields.assignee.accountId'),
                ];
            }
        }

        return $current;
    }

    private function transition(PendingRequest $client, string $key, string $wanted): bool
    {
        // Höchstens drei Schritte; ohne direkten Übergang führt der Weg über „In Arbeit".
        for ($i = 0; $i < 3; $i++) {
            $response = $client->get("/rest/api/3/issue/{$key}/transitions");
            $this->assertOk($response);
            $transitions = collect($response->json('transitions', []));

            $direct = $transitions->first(fn ($t) => $this->classify($t['to']['name'], $t['to']['statusCategory']['key']) === $wanted
                && ! $this->isRejected($t['to']['name']));
            $step = $direct ?? $transitions->first(fn ($t) => $this->classify($t['to']['name'], $t['to']['statusCategory']['key']) === self::PROGRESS);

            if (! $step) {
                return false;
            }

            $this->assertOk($client->post("/rest/api/3/issue/{$key}/transitions", ['transition' => ['id' => $step['id']]]));

            if ($direct) {
                return true;
            }
        }

        return false;
    }

    private function assigneeOf(PendingRequest $client, string $key): ?string
    {
        $response = $client->get("/rest/api/3/issue/{$key}", ['fields' => 'assignee']);
        $this->assertOk($response);

        return $response->json('fields.assignee.accountId');
    }

    /**
     * Jira-Account zu einem Planstack-Nutzer: erst per E-Mail, dann per Anzeigename
     * (genau ein aktiver Treffer). Einen Tag gecacht.
     */
    public function accountId(PendingRequest $client, User $syncUser, User $person): ?string
    {
        $cacheKey = 'jira-account:'.md5($syncUser->jira_base_url.'|'.$person->id);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $found = null;
        foreach (array_filter([$person->email, $person->name]) as $query) {
            $response = $client->get('/rest/api/3/user/search', ['query' => $query]);
            if (! $response->successful()) {
                continue;
            }
            $hits = collect($response->json())->filter(fn ($u) => ($u['active'] ?? false) && ($u['accountType'] ?? 'atlassian') === 'atlassian');
            if ($query === $person->name) {
                $hits = $hits->filter(fn ($u) => ($u['displayName'] ?? '') === $person->name);
            }
            if ($hits->count() === 1) {
                $found = $hits->first()['accountId'];
                break;
            }
        }

        Cache::put($cacheKey, $found ?? '', now()->addDay());

        return $found;
    }

    /**
     * Planstack-Nutzer der Organisation zu einem Jira-Account.
     */
    private function userForAccount(PendingRequest $client, User $syncUser, Project $project, string $account): ?User
    {
        $members = User::query()->where('organization_id', $project->organization_id)->get();

        return $members->first(fn (User $u) => $this->accountId($client, $syncUser, $u) === $account);
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

    public function client(User $user): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $user->jira_base_url, '/'))
            ->withBasicAuth((string) $user->jira_email, (string) $user->jira_api_token)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
