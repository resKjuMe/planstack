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
    private const QUERY = <<<'GQL'
    query($owner: String!, $repo: String!) {
      repository(owner: $owner, name: $repo) {
        pullRequests(first: 100, states: OPEN, orderBy: {field: UPDATED_AT, direction: DESC}) {
          nodes {
            id
            number
            title
            reviewDecision
            commits(last: 1) {
              nodes {
                commit {
                  committedDate
                  statusCheckRollup { state }
                }
              }
            }
            reviewThreads(first: 100) {
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

            try {
                $response = $client->post('/graphql', [
                    'query' => self::QUERY,
                    'variables' => ['owner' => $owner, 'repo' => $name],
                ]);
            } catch (ConnectionException $e) {
                $result['errors']++;
                $result['failures'][] = "{$repo}: ".$e->getMessage();

                continue;
            }

            if ($response->failed()) {
                $result['errors']++;
                $result['failures'][] = "{$repo}: HTTP {$response->status()}";

                continue;
            }

            $body = $response->json();

            // GraphQL meldet Fehler mit HTTP 200 + einem "errors"-Array.
            if (! empty($body['errors'])) {
                $result['errors']++;
                $messages = array_map(fn ($e) => $e['message'] ?? 'GraphQL-Fehler', $body['errors']);
                $result['failures'][] = "{$repo}: ".implode('; ', $messages);

                continue;
            }

            $nodes = data_get($body, 'data.repository.pullRequests.nodes');
            if (! is_array($nodes)) {
                // Repo nicht gefunden / kein Zugriff → data.repository ist null.
                $result['errors']++;
                $result['failures'][] = "{$repo}: keine PR-Daten (Repo unbekannt oder kein Zugriff?)";

                continue;
            }

            $result['prs'] += $this->applyToTasks((string) $repo, $nodes);
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

        $task->fill([
            'pr_node_id' => $node['id'] ?? null,
            'pr_title' => $node['title'] ?? null,
            'pr_ci_status' => data_get($node, 'commits.nodes.0.commit.statusCheckRollup.state'),
            'pr_unresolved_threads' => $unresolved,
            'pr_review_decision' => $node['reviewDecision'] ?? null,
            'pr_last_commit_at' => $committedDate ? Carbon::parse($committedDate) : null,
            'pr_status_synced_at' => now(),
        ]);

        $task->saveQuietly();
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
