<?php

namespace Tests\Feature;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\User;
use App\Support\GitHubPrStatusSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubPrStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_writes_the_polled_pr_state_onto_the_matching_task(): void
    {
        config([
            'planstack.github_token' => 'test-token',
            'planstack.github_api' => 'https://api.github.com',
            'planstack.github_verify_ssl' => false,
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->create([
            'created_by_id' => $user->id,
            'github_repo' => 'acme/widgets',
        ]);
        $task = $project->tasks()->create([
            'name' => 'S1',
            'summary' => 'PR-Status-Task',
            'pr_number' => 42,
            'status' => StatusRole::IN_REVIEW->value,
        ]);

        Http::fake([
            'api.github.com/graphql' => Http::response([
                'data' => [
                    'repository' => [
                        'pullRequests' => [
                            'nodes' => [
                                [
                                    'id' => 'PR_kwABC',
                                    'number' => 42,
                                    'title' => 'S1: Fix the widget',
                                    'reviewDecision' => 'CHANGES_REQUESTED',
                                    'commits' => ['nodes' => [[
                                        'commit' => [
                                            'committedDate' => '2026-07-24T09:30:00Z',
                                            'statusCheckRollup' => ['state' => 'FAILURE'],
                                        ],
                                    ]]],
                                    'reviewThreads' => ['nodes' => [
                                        ['isResolved' => true],
                                        ['isResolved' => false],
                                        ['isResolved' => false],
                                    ]],
                                ],
                                [
                                    // Ein PR ohne zugehörigen Task — wird ignoriert.
                                    'id' => 'PR_kwXYZ',
                                    'number' => 99,
                                    'title' => 'Unrelated',
                                    'reviewDecision' => null,
                                    'commits' => ['nodes' => []],
                                    'reviewThreads' => ['nodes' => []],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new GitHubPrStatusSync)->syncAll();

        $this->assertSame(1, $result['repos']);
        $this->assertSame(1, $result['prs']);
        $this->assertSame(0, $result['errors']);

        $task->refresh();
        $this->assertSame('PR_kwABC', $task->pr_node_id);
        $this->assertSame('S1: Fix the widget', $task->pr_title);
        $this->assertSame('FAILURE', $task->pr_ci_status);
        $this->assertSame(2, $task->pr_unresolved_threads);
        $this->assertSame('CHANGES_REQUESTED', $task->pr_review_decision);
        $this->assertNotNull($task->pr_last_commit_at);
        $this->assertSame('2026-07-24 09:30:00', $task->pr_last_commit_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNotNull($task->pr_status_synced_at);
    }

    public function test_it_reports_token_missing_without_calling_github(): void
    {
        config(['planstack.github_token' => null]);

        $user = User::factory()->create();
        Project::factory()->create([
            'created_by_id' => $user->id,
            'github_repo' => 'acme/widgets',
        ]);

        Http::fake();

        $result = (new GitHubPrStatusSync)->syncAll();

        $this->assertTrue($result['tokenMissing']);
        $this->assertSame(0, $result['repos']);
        Http::assertNothingSent();
    }
}
