<?php

namespace App\Support;

use App\Models\PullRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Hält die offenen PRs aus ps-review aktuell: Status (gemergt/geschlossen),
 * letzter Commit, Titel, Umfang und Approvals (je Reviewer und je Gruppe
 * kritischer Dateien) kommen je Repo per GraphQL, je Anfrage bis zu CHUNK PRs
 * als Aliase. Ein fehlgeschlagenes Repo bricht den Lauf nicht ab.
 * syncAuthored() findet zusätzlich die eigenen offenen PRs, die noch fehlen.
 */
class PullRequestStateSync
{
    private const CHUNK = 25;

    private const SEARCH_PAGES = 8;

    private const FIELDS = 'state mergedAt closedAt title headRefOid additions deletions changedFiles commitCount: commits { totalCount } comments { totalCount }';

    /**
     * @param  Collection<int, PullRequest>|null  $pullRequests  Default: alle offenen
     * @return array{repos: int, prs: int, created: int, errors: int, tokenMissing: bool, failures: array<int, string>}
     */
    public function sync(?Collection $pullRequests = null): array
    {
        $token = config('planstack.github_token');
        $result = $this->emptyResult();

        $open = ($pullRequests ?? PullRequest::query()->where('state', 'OPEN')->get())
            ->filter(fn (PullRequest $pr) => $pr->state->value === 'OPEN')
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
     * Alle offenen PRs des Users auf GitHub (Login `<slug>_clockodo`): unbekannte
     * werden angelegt, bekannte aktualisiert. Eigene offene PRs, die die Suche
     * nicht mehr liefert (gemergt/geschlossen), gleicht sync() ab.
     *
     * @return array{repos: int, prs: int, created: int, errors: int, tokenMissing: bool, failures: array<int, string>}
     */
    public function syncAuthored(User $user): array
    {
        $token = config('planstack.github_token');
        $result = $this->emptyResult();

        if (empty($token)) {
            return $result;
        }

        $nodes = $this->search($this->client((string) $token), PullRequest::loginFor($user), $error);
        if ($error !== null) {
            $result['errors']++;
            $result['failures'][] = "Suche: {$error}";
        }

        $seen = [];
        foreach ($nodes as $node) {
            $pullRequest = PullRequest::firstOrNew([
                'organization_id' => $user->organization_id,
                'repository' => data_get($node, 'repository.nameWithOwner'),
                'pull_request_id' => $node['number'],
            ]);
            $known = $pullRequest->exists;

            $pullRequest->fill([
                'author_id' => $user->id,
                'author_login' => data_get($node, 'author.login'),
                'branch' => $node['headRefName'],
                'target_branch' => $node['baseRefName'],
                'description' => $node['body'] ?? null,
                'opened_at' => $this->date($node['createdAt'] ?? null),
            ]);

            if ($this->applyNode($pullRequest, $node)) {
                $known ? $result['prs']++ : $result['created']++;
            }
            $seen[] = $pullRequest->id;
        }

        $rest = $this->sync(PullRequest::query()
            ->where('organization_id', $user->organization_id)
            ->where('author_id', $user->id)
            ->where('state', 'OPEN')
            ->whereNotIn('id', $seen)
            ->get());

        return [
            'repos' => $rest['repos'],
            'prs' => $result['prs'] + $rest['prs'],
            'created' => $result['created'],
            'errors' => $result['errors'] + $rest['errors'],
            'tokenMissing' => false,
            'failures' => [...$result['failures'], ...$rest['failures']],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search(PendingRequest $client, string $login, ?string &$error): array
    {
        $error = null;
        $nodes = [];
        $after = null;
        $fields = 'number repository { nameWithOwner } author { login } headRefName baseRefName body createdAt '
            .self::FIELDS.' '.$this->approvalFields();

        for ($page = 0; $page < self::SEARCH_PAGES; $page++) {
            try {
                $response = $client->post('/graphql', [
                    'query' => 'query($q: String!, $after: String) { search(query: $q, type: ISSUE, first: '.self::CHUNK.', after: $after) { '
                        ."pageInfo { hasNextPage endCursor } nodes { ... on PullRequest { {$fields} } } } }",
                    'variables' => ['q' => "is:pr is:open author:{$login}", 'after' => $after],
                ]);
            } catch (ConnectionException $e) {
                $error = $e->getMessage();

                break;
            }

            $search = $response->successful() ? $response->json('data.search') : null;
            if (! is_array($search)) {
                $error = $response->failed()
                    ? "HTTP {$response->status()}"
                    : (collect($response->json('errors') ?? [])->pluck('message')->implode('; ') ?: 'Suche fehlgeschlagen');

                break;
            }

            $nodes = [...$nodes, ...array_filter($search['nodes'] ?? [], fn ($node) => isset($node['number']))];

            if (! data_get($search, 'pageInfo.hasNextPage')) {
                break;
            }
            $after = data_get($search, 'pageInfo.endCursor');
        }

        return $nodes;
    }

    /**
     * @param  array<int, int>  $numbers
     * @return array<int, array<string, mixed>> PR-Nummer => Knoten
     */
    private function fetch(PendingRequest $client, string $owner, string $name, array $numbers, ?string &$error): array
    {
        $error = null;
        $fields = self::FIELDS.' '.$this->approvalFields();
        $aliases = collect($numbers)
            ->map(fn (int $n) => "pr{$n}: pullRequest(number: {$n}) { {$fields} }")
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
            if ($node !== null && $this->applyNode($pullRequest, $node)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Übernimmt Status, Umfang und Approvals aus dem GraphQL-Knoten; true, wenn
     * der PR neu angelegt oder geändert wurde.
     *
     * @param  array<string, mixed>  $node
     */
    private function applyNode(PullRequest $pullRequest, array $node): bool
    {
        $pullRequest->fill([
            'state' => $node['state'],
            'merged_at' => $this->date($node['mergedAt'] ?? null),
            'closed_at' => $this->date($node['closedAt'] ?? null),
            'title' => $node['title'],
            'last_commit' => $node['headRefOid'],
            'additions' => $node['additions'],
            'deletions' => $node['deletions'],
            'changed_files' => $node['changedFiles'],
            'commits' => data_get($node, 'commitCount.totalCount', $pullRequest->commits ?? 0),
            'comments' => data_get($node, 'comments.totalCount', $pullRequest->comments ?? 0),
        ]);

        $check = collect(data_get($node, 'commits.nodes.0.commit.checkSuites.nodes', []))
            ->flatMap(fn ($suite) => data_get($suite, 'checkRuns.nodes', []) ?? [])
            ->first();
        if ($check !== null) {
            $pullRequest->review_requirement = PullRequestApprovals::parseRequirement($check['summary'] ?? null);
            $pullRequest->approval_groups = PullRequestApprovals::parseGroups($check['summary'] ?? null);
        }

        $changed = $pullRequest->isDirty();
        if ($changed) {
            $pullRequest->save();
        }

        if (is_array(data_get($node, 'latestOpinionatedReviews.nodes'))) {
            PullRequestApprovals::sync($pullRequest, collect($node['latestOpinionatedReviews']['nodes'])->map(fn ($r) => [
                'login' => data_get($r, 'author.login'),
                'state' => $r['state'] ?? null,
                'submitted_at' => $r['submittedAt'] ?? null,
                'commit_sha' => data_get($r, 'commit.oid'),
            ]));
        }

        return $changed;
    }

    /**
     * Reviews plus der Check-Run mit den Gruppen; die Check-Suites werden auf die
     * App des Checks gefiltert, sonst sprengt die Abfrage GitHubs Ressourcenlimit.
     */
    private function approvalFields(): string
    {
        $fields = 'latestOpinionatedReviews(first: 30) { nodes { author { login } state submittedAt commit { oid } } }';
        $appId = (int) config('planstack.approval_check_app_id');
        $checkName = json_encode((string) config('planstack.approval_check_name'));

        if ($appId > 0) {
            $fields .= " commits(last: 1) { nodes { commit { checkSuites(first: 3, filterBy: {appId: {$appId}}) { nodes { checkRuns(first: 1, filterBy: {checkName: {$checkName}}) { nodes { summary } } } } } } }";
        }

        return $fields;
    }

    /**
     * @return array{repos: int, prs: int, created: int, errors: int, tokenMissing: bool, failures: array<int, string>}
     */
    private function emptyResult(): array
    {
        return ['repos' => 0, 'prs' => 0, 'created' => 0, 'errors' => 0, 'tokenMissing' => empty(config('planstack.github_token')), 'failures' => []];
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
