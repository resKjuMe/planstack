<?php

namespace App\Support;

use App\Models\PullRequest;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Hält die offenen PRs aus ps-review aktuell: Status (gemergt/geschlossen),
 * letzter Commit, Titel und Umfang kommen je Repo per GraphQL, je Anfrage bis zu
 * CHUNK PRs als Aliase. Ein fehlgeschlagenes Repo bricht den Lauf nicht ab.
 */
class PullRequestStateSync
{
    private const CHUNK = 25;

    private const FIELDS = 'state mergedAt closedAt title headRefOid additions deletions changedFiles commits { totalCount } comments { totalCount }';

    /**
     * @return array{repos: int, prs: int, errors: int, tokenMissing: bool, failures: array<int, string>}
     */
    public function sync(): array
    {
        $token = config('planstack.github_token');
        $result = ['repos' => 0, 'prs' => 0, 'errors' => 0, 'tokenMissing' => empty($token), 'failures' => []];

        $open = PullRequest::query()
            ->where('state', 'OPEN')
            ->get()
            ->groupBy('repository');

        if ($open->isEmpty() || empty($token)) {
            return $result;
        }

        $client = $this->client((string) $token);

        foreach ($open as $repository => $rows) {
            [$owner, $name] = explode('/', (string) $repository, 2) + [1 => ''];
            $result['repos']++;

            foreach ($rows->pluck('pull_request_id')->unique()->chunk(self::CHUNK) as $numbers) {
                $nodes = $this->fetch($client, $owner, $name, $numbers->values()->all(), $error);

                if ($error !== null) {
                    $result['errors']++;
                    $result['failures'][] = "{$repository}: {$error}";

                    continue;
                }

                $result['prs'] += $this->apply($rows, $nodes);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $numbers
     * @return array<int, array<string, mixed>> PR-Nummer => Knoten
     */
    private function fetch(PendingRequest $client, string $owner, string $name, array $numbers, ?string &$error): array
    {
        $error = null;
        $aliases = collect($numbers)
            ->map(fn (int $n) => "pr{$n}: pullRequest(number: {$n}) { ".self::FIELDS.' }')
            ->implode("\n");

        try {
            $response = $client->post('/graphql', [
                'query' => "query(\$owner: String!, \$name: String!) { repository(owner: \$owner, name: \$name) { {$aliases} } }",
                'variables' => ['owner' => $owner, 'name' => $name],
            ]);
        } catch (ConnectionException $e) {
            $error = $e->getMessage();

            return [];
        }

        if ($response->failed()) {
            $error = "HTTP {$response->status()}";

            return [];
        }

        $repository = $response->json('data.repository');
        if (! is_array($repository)) {
            $error = collect($response->json('errors') ?? [])->pluck('message')->implode('; ') ?: 'Repo nicht gefunden';

            return [];
        }

        // Ein einzelner unbekannter PR kommt als null (mit errors[]) — der Rest zählt.
        return collect($numbers)
            ->filter(fn (int $n) => is_array($repository["pr{$n}"] ?? null))
            ->mapWithKeys(fn (int $n) => [$n => $repository["pr{$n}"]])
            ->all();
    }

    /**
     * @param  Collection<int, PullRequest>  $rows
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function apply(Collection $rows, array $nodes): int
    {
        $updated = 0;

        foreach ($rows as $pullRequest) {
            $node = $nodes[$pullRequest->pull_request_id] ?? null;
            if ($node === null) {
                continue;
            }

            $pullRequest->fill([
                'state' => $node['state'],
                'merged_at' => $this->date($node['mergedAt'] ?? null),
                'closed_at' => $this->date($node['closedAt'] ?? null),
                'title' => $node['title'],
                'last_commit' => $node['headRefOid'],
                'additions' => $node['additions'],
                'deletions' => $node['deletions'],
                'changed_files' => $node['changedFiles'],
                'commits' => data_get($node, 'commits.totalCount', $pullRequest->commits),
                'comments' => data_get($node, 'comments.totalCount', $pullRequest->comments),
            ]);

            if ($pullRequest->isDirty()) {
                $pullRequest->save();
                $updated++;
            }
        }

        return $updated;
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function client(string $token): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('planstack.github_api'), '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'planstack'])
            ->withOptions(['verify' => config('planstack.github_verify_ssl', true)])
            ->withToken($token)
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }
}
