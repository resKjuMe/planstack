<?php

namespace App\Support;

use App\Models\PullRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Commits eines PRs mit Zeitpunkt und Umfang für den Verlauf auf der Detailseite.
 * Per GraphQL geholt und je Kopf-Commit gecacht; ohne Token oder bei Fehlern leer.
 */
class PullRequestCommits
{
    private const QUERY = <<<'GQL'
    query($owner: String!, $name: String!, $number: Int!) {
      repository(owner: $owner, name: $name) {
        pullRequest(number: $number) {
          commits(first: 100) {
            nodes { commit { oid committedDate additions deletions messageHeadline parents(first: 1) { totalCount } } }
          }
        }
      }
    }
    GQL;

    /**
     * @return array<int, array{sha: string, committedAt: string, additions: int, deletions: int, message: string, merge: bool}>
     */
    public function for(PullRequest $pr): array
    {
        $token = config('planstack.github_token');
        [$owner, $name] = explode('/', (string) $pr->repository, 2) + [1 => ''];

        if (empty($token) || $owner === '' || $name === '') {
            return [];
        }

        $key = "pr-commits:v2:{$pr->repository}#{$pr->pull_request_id}:{$pr->last_commit}";
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $commits = $this->fetch((string) $token, $owner, $name, $pr->pull_request_id);
        if ($commits !== null) {
            Cache::put($key, $commits, now()->addDay());
        }

        return $commits ?? [];
    }

    /**
     * @return array<int, array{sha: string, committedAt: string, additions: int, deletions: int, message: string, merge: bool}>|null null bei Fehler
     */
    private function fetch(string $token, string $owner, string $name, int $number): ?array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('planstack.github_api'), '/'))
                ->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'planstack'])
                ->withOptions(['verify' => config('planstack.github_verify_ssl', true)])
                ->withToken($token)
                ->timeout(10)
                ->post('/graphql', [
                    'query' => self::QUERY,
                    'variables' => ['owner' => $owner, 'name' => $name, 'number' => $number],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $nodes = $response->successful() ? data_get($response->json(), 'data.repository.pullRequest.commits.nodes') : null;
        if (! is_array($nodes)) {
            return null;
        }

        return collect($nodes)
            ->map(fn ($n) => data_get($n, 'commit'))
            ->filter(fn ($c) => is_array($c) && ! empty($c['oid']) && ! empty($c['committedDate']))
            ->map(fn (array $c) => [
                'sha' => $c['oid'],
                'committedAt' => $c['committedDate'],
                'additions' => (int) ($c['additions'] ?? 0),
                'deletions' => (int) ($c['deletions'] ?? 0),
                'message' => (string) ($c['messageHeadline'] ?? ''),
                // Merge-Commits (z. B. main in den Branch) tragen fremden Diff.
                'merge' => (int) data_get($c, 'parents.totalCount', 1) > 1,
            ])
            ->values()
            ->all();
    }
}
