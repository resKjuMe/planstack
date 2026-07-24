<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Pollt je konfiguriertem GitHub-Repo die 100 zuletzt aktualisierten OFFENEN PRs
 * über die GitHub-GraphQL-API und spiegelt ihren Zustand (CI-Rollup, Anzahl
 * unresolved Review-Threads, Review-Entscheidung, PR-Titel, Zeitpunkt des letzten
 * Commits) direkt auf die passenden Tasks (Abgleich über project.github_repo +
 * task.pr_number, je Angabe eine Spalte). Gedacht für den minütlichen Cronjob
 * (planstack:sync-pr-status); liefert die serverseitige Grundlage für die
 * „fix"-Erkennung des next-action-Resolvers.
 *
 * Nur die 100 jüngsten offenen PRs je Repo werden abgefragt: Tasks, deren PR nicht
 * in diesem Fenster liegt (oder bereits geschlossen ist), bleiben unangetastet.
 * Der Schreibvorgang läuft „quiet" (kein entity-changed-Broadcast) — es ist ein
 * reiner Hintergrund-Abgleich in die DB.
 */
class GitHubPrStatusSync
{
    /** PRs je GraphQL-Seite. Klein halten: 100 PRs mit contexts+threads in einem
     *  Request lassen GitHub timeouten (HTTP 504) — daher in Seiten paginieren. */
    private const PAGE_SIZE = 25;

    /** Obergrenze: die N zuletzt aktualisierten offenen PRs je Repo. */
    private const MAX_PRS = 100;

    private const QUERY = <<<'GQL'
    query($owner: String!, $repo: String!, $first: Int!, $after: String) {
      repository(owner: $owner, name: $repo) {
        pullRequests(first: $first, after: $after, states: OPEN, orderBy: {field: UPDATED_AT, direction: DESC}) {
          pageInfo { hasNextPage endCursor }
          nodes {
            id
            number
            title
            reviewDecision
            isInMergeQueue
            mergeQueueEntry { state }
            commits(last: 1) {
              nodes {
                commit {
                  committedDate
                  statusCheckRollup {
                    state
                    contexts(first: 50) {
                      nodes {
                        __typename
                        ... on CheckRun { status conclusion }
                        ... on StatusContext { state }
                      }
                    }
                  }
                }
              }
            }
            reviewThreads(first: 50) {
              nodes { isResolved }
            }
          }
        }
      }
    }
    GQL;

    /**
     * Alle Projekte mit github_repo (repo-übergreifend dedupliziert): pro
     * eindeutigem Repo eine GraphQL-Abfrage.
     *
     * @return array{repos: int, prs: int, errors: int, tokenMissing: bool, failures: array<int, string>}
     */
    public function syncAll(): array
    {
        $repos = Project::query()
            ->whereNotNull('github_repo')
            ->pluck('github_repo')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->syncRepos($repos);
    }

    /**
     * @param  array<int, string>  $repos  Liste "owner/name"
     * @return array{repos: int, prs: int, errors: int, tokenMissing: bool, failures: array<int, string>}
     */
    public function syncRepos(array $repos): array
    {
        $token = config('planstack.github_token');
        $result = ['repos' => 0, 'prs' => 0, 'errors' => 0, 'tokenMissing' => empty($token), 'failures' => []];

        if ($repos === []) {
            return $result;
        }

        // GraphQL erlaubt keine anonymen Anfragen — ohne Token gar nicht erst starten.
        if (empty($token)) {
            $result['failures'][] = __('flash.github_token_missing');

            return $result;
        }

        $client = $this->client($token);

        foreach ($repos as $repo) {
            [$owner, $name] = array_pad(explode('/', (string) $repo, 2), 2, null);
            if (! $owner || ! $name) {
                $result['errors']++;
                $result['failures'][] = "{$repo}: ungültiges Repo-Format (erwartet owner/name)";

                continue;
            }

            $result['repos']++;

            // In Seiten paginieren (siehe PAGE_SIZE) und die Knoten sammeln, bis
            // MAX_PRS erreicht sind oder keine weitere Seite existiert. Ein Fehler
            // auf einer Seite bricht nur dieses Repo ab; bereits geholte Knoten
            // werden trotzdem angewandt.
            $nodes = [];
            $after = null;

            while (count($nodes) < self::MAX_PRS) {
                $first = min(self::PAGE_SIZE, self::MAX_PRS - count($nodes));

                try {
                    $response = $client->post('/graphql', [
                        'query' => self::QUERY,
                        'variables' => ['owner' => $owner, 'repo' => $name, 'first' => $first, 'after' => $after],
                    ]);
                } catch (ConnectionException $e) {
                    $result['errors']++;
                    $result['failures'][] = "{$repo}: ".$e->getMessage();
                    break;
                }

                if ($response->failed()) {
                    $result['errors']++;
                    $result['failures'][] = "{$repo}: HTTP {$response->status()}";
                    break;
                }

                $body = $response->json();
                $conn = data_get($body, 'data.repository.pullRequests');
                $pageNodes = (is_array($conn) && is_array($conn['nodes'] ?? null)) ? $conn['nodes'] : null;

                // GraphQL kann Feld-Fehler (HTTP 200 + errors[]) liefern UND trotzdem
                // Teildaten mitschicken: ein Feld ohne Token-Recht (z. B. mergeQueueEntry)
                // kommt als null zurück, der Rest steht. Nur wenn gar keine Knoten
                // kommen, ist es ein harter Fehler.
                if (! empty($body['errors'])) {
                    $messages = collect($body['errors'])->pluck('message')->filter()->unique()->values()->implode('; ');
                    if ($pageNodes === null) {
                        $result['errors']++;
                        $result['failures'][] = "{$repo}: {$messages}";
                        break;
                    }
                    // Teildaten übernehmen; Warnung einmal vermerken (dedupliziert).
                    $note = "{$repo} (Teildaten): {$messages}";
                    if (! in_array($note, $result['failures'], true)) {
                        $result['failures'][] = $note;
                    }
                }

                if ($pageNodes === null) {
                    // Repo nicht gefunden / kein Zugriff → data.repository ist null.
                    $result['errors']++;
                    $result['failures'][] = "{$repo}: keine PR-Daten (Repo unbekannt oder kein Zugriff?)";
                    break;
                }

                $nodes = array_merge($nodes, $pageNodes);

                if (! data_get($conn, 'pageInfo.hasNextPage')) {
                    break;
                }
                $after = data_get($conn, 'pageInfo.endCursor');
            }

            if ($nodes !== []) {
                $result['prs'] += $this->applyToTasks((string) $repo, $nodes);
            }
        }

        return $result;
    }

    /**
     * Die PR-Knoten eines Repos auf die passenden Tasks schreiben. Abgleich über
     * project.github_repo == $repo und task.pr_number == PR-Nummer.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return int  Anzahl aktualisierter Tasks
     */
    private function applyToTasks(string $repo, array $nodes): int
    {
        // PR-Nummer → Knoten (die 100 jüngsten offenen PRs dieses Repos).
        $byNumber = collect($nodes)->keyBy(fn ($n) => (int) ($n['number'] ?? 0));

        $tasks = Task::query()
            ->whereNotNull('pr_number')
            ->whereHas('project', fn ($q) => $q->where('github_repo', $repo))
            ->get();

        $updated = 0;
        foreach ($tasks as $task) {
            $node = $byNumber->get((int) $task->pr_number);
            if ($node === null) {
                continue; // PR nicht im Fenster der 100 jüngsten / geschlossen → unverändert lassen
            }

            $this->apply($task, $node);
            $updated++;
        }

        return $updated;
    }

    /**
     * Einen PR-Knoten auf einen Task schreiben (quiet: kein Broadcast).
     *
     * @param  array<string, mixed>  $node
     */
    private function apply(Task $task, array $node): void
    {
        $unresolved = collect(data_get($node, 'reviewThreads.nodes', []))
            ->filter(fn ($t) => ($t['isResolved'] ?? false) === false)
            ->count();

        $committedDate = data_get($node, 'commits.nodes.0.commit.committedDate');
        $ci = $this->ciCounts(data_get($node, 'commits.nodes.0.commit.statusCheckRollup.contexts.nodes', []));

        $task->fill([
            'pr_node_id' => $node['id'] ?? null,
            'pr_title' => $node['title'] ?? null,
            'pr_ci_status' => data_get($node, 'commits.nodes.0.commit.statusCheckRollup.state'),
            'pr_ci_failed' => $ci['failed'],
            'pr_ci_running' => $ci['running'],
            'pr_ci_success' => $ci['success'],
            'pr_ci_waiting' => $ci['waiting'],
            'pr_in_merge_queue' => (bool) ($node['isInMergeQueue'] ?? false),
            'pr_merge_queue_state' => data_get($node, 'mergeQueueEntry.state'),
            'pr_unresolved_threads' => $unresolved,
            'pr_review_decision' => $node['reviewDecision'] ?? null,
            'pr_last_commit_at' => $committedDate ? Carbon::parse($committedDate) : null,
            'pr_status_synced_at' => now(),
        ]);

        $task->saveQuietly();
    }

    /**
     * Zählt die einzelnen CI-Steps (statusCheckRollup.contexts) nach Kategorie:
     * failed / running / successful / waiting. Ein Context ist entweder ein CheckRun
     * (status + conclusion) oder ein StatusContext (state).
     *
     * Einzelne Kontexte können null sein, wenn dem Token das Recht „Checks: read"
     * fehlt (GitHub liefert dann den Rollup-`state`, aber nicht die Einzel-Checks).
     * Gab es Kontexte, war aber keiner lesbar, geben wir null („unbekannt") statt
     * eines irreführenden 0 zurück.
     *
     * @param  array<int, array<string, mixed>|null>  $contexts
     * @return array{failed: ?int, running: ?int, success: ?int, waiting: ?int}
     */
    private function ciCounts(array $contexts): array
    {
        $c = ['failed' => 0, 'running' => 0, 'success' => 0, 'waiting' => 0];
        $usable = 0;

        foreach ($contexts as $ctx) {
            if (! is_array($ctx)) {
                continue; // null → nicht lesbar (fehlendes „Checks: read"-Recht)
            }
            $usable++;
            if (($ctx['__typename'] ?? null) === 'CheckRun') {
                $status = $ctx['status'] ?? null;      // QUEUED|IN_PROGRESS|COMPLETED|WAITING|PENDING|REQUESTED
                $conclusion = $ctx['conclusion'] ?? null; // SUCCESS|NEUTRAL|SKIPPED|FAILURE|…
                if ($status === 'COMPLETED') {
                    $c[in_array($conclusion, ['SUCCESS', 'NEUTRAL', 'SKIPPED'], true) ? 'success' : 'failed']++;
                } elseif ($status === 'IN_PROGRESS') {
                    $c['running']++;
                } else { // QUEUED, WAITING, PENDING, REQUESTED
                    $c['waiting']++;
                }
            } else { // StatusContext
                $state = $ctx['state'] ?? null;        // EXPECTED|ERROR|FAILURE|PENDING|SUCCESS
                if ($state === 'SUCCESS') {
                    $c['success']++;
                } elseif (in_array($state, ['FAILURE', 'ERROR'], true)) {
                    $c['failed']++;
                } elseif ($state === 'PENDING') {
                    $c['running']++;
                } else { // EXPECTED
                    $c['waiting']++;
                }
            }
        }

        // Es gab Kontexte, aber keiner war lesbar (Token ohne „Checks: read") →
        // unbekannt statt fälschlich 0.
        if ($contexts !== [] && $usable === 0) {
            return ['failed' => null, 'running' => null, 'success' => null, 'waiting' => null];
        }

        return $c;
    }

    private function client(?string $token): PendingRequest
    {
        $verify = config('planstack.github_verify_ssl', true);

        return Http::baseUrl(rtrim((string) config('planstack.github_api'), '/'))
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'planstack',
            ])
            ->withOptions(['verify' => $verify])
            ->withToken((string) $token)
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }
}
