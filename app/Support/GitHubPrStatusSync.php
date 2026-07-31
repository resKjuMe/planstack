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
 *
 * Fehler beenden den Lauf nicht: eine fehlgeschlagene Seite (Timeout, 504, GraphQL-
 * Fehler) wird gemeldet und mit halbierter Seitengröße wiederholt, statt die Pagi-
 * nierung des Repos abzubrechen — sonst kostet ein einzelner 504 auf Seite 1 den CI-
 * Status aller folgenden PRs. Erst wenn eine Seite dauerhaft scheitert, endet das
 * Repo; alle bis dahin geholten Knoten werden angewandt.
 *
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

    /** Versuche je Seite, bevor die Paginierung eines Repos endet. Jeder weitere
     *  Versuch halbiert die Seitengröße — ein 504 ist meist eine zu große Seite. */
    private const PAGE_ATTEMPTS = 4;

    /** Anfrage-Budget je Repo. Ein sauberer Lauf braucht 4 (100/25); der Rest ist
     *  Luft für Wiederholungen und kleinere Seiten, damit ein dauerhaft langsames
     *  Repo den Minuten-Cron nicht unbegrenzt beschäftigt. */
    private const MAX_REQUESTS = 24;

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
            mergeable
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
            // MAX_PRS erreicht sind oder keine weitere Seite existiert.
            //
            // Ein Fehler auf einer Seite beendet den Lauf NICHT: er wird gemeldet und
            // dieselbe Seite mit halbierter Seitengröße erneut geholt (ein 504 ist in
            // der Regel eine zu große Seite). Überspringen ist keine Option — ohne den
            // endCursor der fehlgeschlagenen Antwort gibt es keinen Einstieg in die
            // Folgeseite. Erst wenn PAGE_ATTEMPTS Versuche an einem Cursor scheitern
            // (oder der Fehler nicht wiederholbar ist), endet die Paginierung dieses
            // Repos; die bereits geholten Knoten werden immer angewandt.
            $nodes = [];
            $after = null;
            $size = self::PAGE_SIZE;
            $attempts = 0;
            $requests = 0;

            while (count($nodes) < self::MAX_PRS) {
                if ($requests >= self::MAX_REQUESTS) {
                    $this->note($result, "{$repo}: Anfrage-Budget (".self::MAX_REQUESTS.') erschöpft — restliche Seiten ungeprüft', isError: false);
                    break;
                }

                $requests++;
                $page = $this->fetchPage($client, $owner, $name, max(1, min($size, self::MAX_PRS - count($nodes))), $after);

                if ($page['error'] !== null) {
                    $this->note($result, "{$repo}: {$page['error']}", isError: true);

                    if (! $page['retryable'] || ++$attempts >= self::PAGE_ATTEMPTS) {
                        break;
                    }

                    $size = max(1, intdiv($size, 2));

                    continue;
                }

                $attempts = 0;

                // Teildaten (HTTP 200 + errors[]): übernehmen, Warnung vermerken.
                if ($page['warning'] !== null) {
                    $this->note($result, "{$repo} {$page['warning']}", isError: false);
                }

                $nodes = array_merge($nodes, $page['nodes']);

                if (! data_get($page['pageInfo'], 'hasNextPage')) {
                    break;
                }
                $after = data_get($page['pageInfo'], 'endCursor');
            }

            if ($nodes !== []) {
                $result['prs'] += $this->applyToTasks((string) $repo, $nodes);
            }
        }

        return $result;
    }

    /**
     * Eine Seite der zuletzt aktualisierten offenen PRs holen.
     *
     * `retryable` sagt, ob ein zweiter Versuch am selben Cursor Sinn hat: Timeouts,
     * 5xx und GraphQL-Fehler ohne Daten ja — ein fehlendes `repository` (Repo
     * unbekannt oder kein Zugriff) nein, das bleibt bei jedem Versuch gleich.
     *
     * @return array{nodes: ?array<int, array<string, mixed>>, pageInfo: array<string, mixed>, error: ?string, retryable: bool, warning: ?string}
     */
    private function fetchPage(PendingRequest $client, string $owner, string $name, int $first, ?string $after): array
    {
        // Gemeinsame Basis der Fehlerfälle; die Rückgaben ergänzen error + retryable.
        $noData = ['nodes' => null, 'pageInfo' => [], 'warning' => null];

        try {
            $response = $client->post('/graphql', [
                'query' => self::QUERY,
                'variables' => ['owner' => $owner, 'repo' => $name, 'first' => $first, 'after' => $after],
            ]);
        } catch (ConnectionException $e) {
            return $noData + ['error' => $e->getMessage(), 'retryable' => true];
        }

        if ($response->failed()) {
            return $noData + ['error' => "HTTP {$response->status()}", 'retryable' => true];
        }

        $body = $response->json();
        $conn = data_get($body, 'data.repository.pullRequests');
        $pageNodes = (is_array($conn) && is_array($conn['nodes'] ?? null)) ? $conn['nodes'] : null;

        $messages = empty($body['errors'])
            ? null
            : collect($body['errors'])->pluck('message')->filter()->unique()->values()->implode('; ');

        // GraphQL kann Feld-Fehler (HTTP 200 + errors[]) liefern UND trotzdem
        // Teildaten mitschicken: ein Feld ohne Token-Recht (z. B. mergeQueueEntry)
        // kommt als null zurück, der Rest steht. Nur wenn gar keine Knoten kommen,
        // ist die Seite fehlgeschlagen.
        if ($pageNodes !== null) {
            return [
                'nodes' => $pageNodes,
                'pageInfo' => (array) data_get($conn, 'pageInfo', []),
                'error' => null,
                'retryable' => false,
                'warning' => $messages === null ? null : "(Teildaten): {$messages}",
            ];
        }

        if ($messages !== null) {
            return $noData + ['error' => $messages, 'retryable' => true];
        }

        // Keine Fehler, aber auch kein repository → Repo unbekannt / kein Zugriff.
        return $noData + ['error' => 'keine PR-Daten (Repo unbekannt oder kein Zugriff?)', 'retryable' => false];
    }

    /**
     * Meldung dedupliziert anhängen. `isError` zählt sie zusätzlich als Fehler —
     * einmal je Meldung, nicht je Versuch, sonst zählt eine wiederholte Seite
     * mehrfach.
     *
     * @param  array{repos: int, prs: int, errors: int, tokenMissing: bool, failures: array<int, string>}  $result
     */
    private function note(array &$result, string $message, bool $isError): void
    {
        if (in_array($message, $result['failures'], true)) {
            return;
        }

        $result['failures'][] = $message;

        if ($isError) {
            $result['errors']++;
        }
    }

    /**
     * Die PR-Knoten eines Repos auf die passenden Tasks schreiben. Abgleich über
     * project.github_repo == $repo und task.pr_number == PR-Nummer.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return int Anzahl aktualisierter Tasks
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
            'pr_mergeable' => $node['mergeable'] ?? null,
            'pr_unresolved_threads' => $unresolved,
            'pr_review_decision' => $node['reviewDecision'] ?? null,
            'pr_last_commit_at' => $committedDate ? Carbon::parse($committedDate) : null,
            'pr_status_synced_at' => now(),
        ]);

        // Nur melden, wenn sich wirklich etwas geändert hat: der Sync läuft im Minuten-
        // Takt über alle offenen PRs, ein Broadcast pro Lauf und Task wäre ein Sturm
        // ohne Neuigkeit.
        $changed = $task->isDirty();

        $task->saveQuietly();

        // Quiet bleibt es mit Absicht (kein Audit-Eintrag für einen gepollten
        // Fremdzustand), das Board soll die neuen CI-Zahlen aber ohne Reload sehen —
        // deshalb der Broadcast explizit.
        if ($changed) {
            $task->emitEntityChange('update');
        }
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
