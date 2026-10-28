<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Support\PullRequestStateSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullRequestStateSyncTest extends TestCase
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

    private function pullRequest(int $number, string $state = 'OPEN'): PullRequest
    {
        return PullRequest::create([
            'organization_id' => Organization::factory()->create()->id,
            'repository' => 'acme/widgets',
            'pull_request_id' => $number,
            'title' => "PR {$number}",
            'branch' => 'feature',
            'target_branch' => 'main',
            'last_commit' => 'aaa',
            'state' => $state,
        ]);
    }

    public function test_it_mirrors_state_and_size_of_open_pull_requests(): void
    {
        $merged = $this->pullRequest(1);
        $open = $this->pullRequest(2);
        $missing = $this->pullRequest(3);
        $closed = $this->pullRequest(4, 'CLOSED');

        Http::fake([
            'api.github.com/graphql' => Http::response([
                'data' => ['repository' => [
                    'pr1' => [
                        'state' => 'MERGED', 'mergedAt' => '2026-10-26T10:00:00Z', 'closedAt' => '2026-10-26T10:00:00Z',
                        'title' => 'PR 1', 'headRefOid' => 'bbb', 'additions' => 5, 'deletions' => 1, 'changedFiles' => 2,
                        'commitCount' => ['totalCount' => 3], 'comments' => ['totalCount' => 1],
                        'latestOpinionatedReviews' => ['nodes' => [
                            ['author' => ['login' => 'vera-rolf_clockodo'], 'state' => 'APPROVED', 'submittedAt' => '2026-10-26T09:00:00Z', 'commit' => ['oid' => 'bbb']],
                        ]],
                        'commits' => ['nodes' => [['commit' => ['checkSuites' => ['nodes' => [
                            ['checkRuns' => ['nodes' => [['summary' => "## Review Requirement\n\n**Stufe:** `critical` — KI-Einschätzung `critical`\n\n**Approvals:** 1 von 2 (gezählt auf dem aktuellen Commit, ohne Autor)\n\n- Gruppe **Backend**: `backend/public/index.php`"]]]],
                        ]]]]]],
                    ],
                    'pr2' => [
                        'state' => 'OPEN', 'mergedAt' => null, 'closedAt' => null,
                        'title' => 'PR 2 neu', 'headRefOid' => 'ccc', 'additions' => 9, 'deletions' => 0, 'changedFiles' => 1,
                        'commitCount' => ['totalCount' => 1], 'comments' => ['totalCount' => 0],
                    ],
                    'pr3' => null,
                ]],
                'errors' => [['message' => 'Could not resolve to a PullRequest with the number of 3.']],
            ]),
        ]);

        $result = app(PullRequestStateSync::class)->sync();

        $this->assertSame(2, $result['prs']);
        $this->assertSame('MERGED', $merged->refresh()->state->value);
        $this->assertNotNull($merged->merged_at);
        $this->assertSame('bbb', $merged->last_commit);
        $this->assertSame(3, $merged->commits);
        $this->assertSame(['level' => 'critical', 'required' => 2, 'criticalGroups' => ['Backend']], $merged->review_requirement);
        $this->assertNull($merged->approval_groups);
        $this->assertSame(['vera-rolf_clockodo'], $merged->approvals()->pluck('login')->all());
        // Ohne Check-Daten bleibt die Anforderung unbekannt.
        $this->assertNull($open->refresh()->review_requirement);
        $this->assertSame('PR 2 neu', $open->title);
        $this->assertSame(9, $open->additions);
        $this->assertSame('OPEN', $missing->refresh()->state->value);

        // Nur offene PRs werden abgefragt.
        Http::assertSent(fn ($request) => ! str_contains($request['query'], 'pr4:')
            && str_contains($request['query'], 'filterBy: {appId: 5393133}'));
        $this->assertSame('CLOSED', $closed->refresh()->state->value);
    }

    public function test_a_failing_repository_is_reported_and_leaves_the_data_untouched(): void
    {
        $pullRequest = $this->pullRequest(1);

        Http::fake(['api.github.com/graphql' => Http::response([], 502)]);

        $result = app(PullRequestStateSync::class)->sync();

        $this->assertSame(1, $result['errors']);
        $this->assertSame('OPEN', $pullRequest->refresh()->state->value);
    }
}
