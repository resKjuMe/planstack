<?php

namespace Tests\Feature;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\User;
use App\Support\GitHubPrSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubPrSyncEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_emits_a_merged_event_for_newly_merged_tasks(): void
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
            'summary' => 'Sync-Task',
            'pr_number' => 42,
            'status' => StatusRole::IN_REVIEW->value,
        ]);

        Http::fake([
            'api.github.com/*' => Http::response([
                'merged' => true,
                'merged_at' => '2026-07-20T10:00:00Z',
                'changed_files' => 3,
                'additions' => 10,
                'deletions' => 2,
                'commits' => 1,
            ], 200),
        ]);

        $this->actingAs($user);

        $result = (new GitHubPrSync)->sync($project);

        $this->assertSame(1, $result['merged']);
        $this->assertNotNull($task->refresh()->merged_at);
        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'event' => 'MERGED',
            'actor_id' => $user->id,
        ]);
    }
}
