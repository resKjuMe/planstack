<?php

namespace Tests\Feature;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_request_webhook_is_logged_and_resolved_to_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'created_by_id' => $user->id,
            'github_repo' => 'acme/widgets',
        ]);
        $task = $project->tasks()->create([
            'name' => 'W1',
            'summary' => 'Webhook-Task',
            'pr_number' => 42,
            'status' => StatusRole::IN_REVIEW->value,
        ]);

        $response = $this->postJson('/hooks/git', [
            'action' => 'opened',
            'number' => 42,
            'pull_request' => ['number' => 42],
            'repository' => ['full_name' => 'acme/widgets'],
        ], [
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => 'delivery-guid-1',
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('git_webhook_events', [
            'event' => 'pull_request',
            'action' => 'opened',
            'delivery_id' => 'delivery-guid-1',
            'repository' => 'acme/widgets',
            'pr_number' => 42,
            'project_id' => $project->id,
            'task_id' => $task->id,
        ]);
    }

    public function test_push_webhook_without_pr_is_still_logged(): void
    {
        $project = Project::factory()->create(['github_repo' => 'acme/widgets']);

        $response = $this->postJson('/hooks/git', [
            'ref' => 'refs/heads/main',
            'repository' => ['full_name' => 'acme/widgets'],
        ], ['X-GitHub-Event' => 'push']);

        $response->assertStatus(202);

        $this->assertDatabaseHas('git_webhook_events', [
            'event' => 'push',
            'action' => null,
            'pr_number' => null,
            'project_id' => $project->id,
            'task_id' => null,
        ]);
    }

    public function test_invalid_signature_is_rejected_when_secret_configured(): void
    {
        config(['planstack.github_webhook_secret' => 'top-secret']);

        $response = $this->postJson('/hooks/git', [
            'action' => 'opened',
            'repository' => ['full_name' => 'acme/widgets'],
        ], [
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('git_webhook_events', 0);
    }

    public function test_valid_signature_is_accepted(): void
    {
        config(['planstack.github_webhook_secret' => 'top-secret']);

        $body = json_encode([
            'action' => 'synchronize',
            'pull_request' => ['number' => 7],
            'repository' => ['full_name' => 'acme/widgets'],
        ]);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'top-secret');

        $response = $this->call(
            'POST',
            '/hooks/git',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_GITHUB_EVENT' => 'pull_request',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body,
        );

        $response->assertStatus(202);
        $this->assertDatabaseHas('git_webhook_events', [
            'event' => 'pull_request',
            'action' => 'synchronize',
            'pr_number' => 7,
        ]);
    }
}
