<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Support\PullRequestCommits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullRequestCommitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'planstack.github_token' => 'test-token',
            'planstack.github_api' => 'https://api.github.com',
            'planstack.github_verify_ssl' => false,
        ]);
    }

    private function pullRequest(string $lastCommit = 'bbb'): PullRequest
    {
        return PullRequest::create([
            'organization_id' => Organization::factory()->create()->id,
            'repository' => 'acme/widgets',
            'pull_request_id' => 26,
            'title' => 'PR 26',
            'branch' => 'feature',
            'target_branch' => 'main',
            'last_commit' => $lastCommit,
        ]);
    }

    public function test_it_returns_the_commits_and_caches_them_per_head_commit(): void
    {
        Http::fake([
            'api.github.com/graphql' => Http::response(['data' => ['repository' => ['pullRequest' => ['commits' => ['nodes' => [
                ['commit' => ['oid' => 'aaa', 'committedDate' => '2026-10-27T10:00:00Z', 'additions' => 41, 'deletions' => 120, 'messageHeadline' => 'feat: a', 'parents' => ['totalCount' => 1]]],
                ['commit' => ['oid' => 'bbb', 'committedDate' => '2026-10-28T10:00:00Z', 'additions' => 4, 'deletions' => 4, 'messageHeadline' => 'fix: b']],
                ['commit' => ['oid' => 'ccc', 'committedDate' => '2026-10-28T11:00:00Z', 'additions' => 900, 'deletions' => 50, 'messageHeadline' => 'Merge main', 'parents' => ['totalCount' => 2]]],
            ]]]]]]),
        ]);

        $pr = $this->pullRequest();
        $commits = app(PullRequestCommits::class)->for($pr);
        app(PullRequestCommits::class)->for($pr);

        $this->assertSame([
            ['sha' => 'aaa', 'committedAt' => '2026-10-27T10:00:00Z', 'additions' => 41, 'deletions' => 120, 'message' => 'feat: a', 'merge' => false],
            ['sha' => 'bbb', 'committedAt' => '2026-10-28T10:00:00Z', 'additions' => 4, 'deletions' => 4, 'message' => 'fix: b', 'merge' => false],
            ['sha' => 'ccc', 'committedAt' => '2026-10-28T11:00:00Z', 'additions' => 900, 'deletions' => 50, 'message' => 'Merge main', 'merge' => true],
        ], $commits);
        Http::assertSentCount(1);
    }

    public function test_a_failed_request_yields_no_commits_and_is_not_cached(): void
    {
        Http::fake(['api.github.com/graphql' => Http::response([], 502)]);

        $pr = $this->pullRequest();

        $this->assertSame([], app(PullRequestCommits::class)->for($pr));
        $this->assertSame([], app(PullRequestCommits::class)->for($pr));
        Http::assertSentCount(2);
    }

    public function test_without_token_github_is_not_asked(): void
    {
        config(['planstack.github_token' => null]);
        Http::fake();

        $this->assertSame([], app(PullRequestCommits::class)->for($this->pullRequest()));
        Http::assertNothingSent();
    }
}
