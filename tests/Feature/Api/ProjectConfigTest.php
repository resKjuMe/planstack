<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectConfigTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project} */
    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        Sanctum::actingAs($user);

        return [$user, $project];
    }

    private function pickableTask(Project $project, string $name): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'name' => $name,
            'status' => TaskStatus::UNKNOWN,
            'claimed_by_id' => null,
            'pr_number' => null,
        ]);
    }

    public function test_board_uses_recommended_defaults(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $this->pickableTask($project, 'C2');

        $response = $this->getJson("/api/projects/{$project->alias}/board");

        $response->assertOk()
            ->assertHeader('X-Planstack-Config-Version', '1')
            ->assertJsonPath('config_version', 1)
            ->assertJsonCount(2, 'pickable')
            // Recommended default: aggregates off.
            ->assertJsonMissingPath('totals')
            ->assertJsonMissingPath('phases');

        // Recommended differs from the shipped defaults → a hint delta is sent.
        $this->assertArrayHasKey('execution.mode', $response->json('client_hints'));
    }

    public function test_config_show_exposes_catalog_and_defaults(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->getJson("/api/projects/{$project->alias}/config")
            ->assertOk()
            ->assertJsonPath('config_version', 1)
            ->assertJsonPath('profile', null)
            ->assertJsonPath('catalog.profiles', ['recommended', 'economy', 'balanced', 'rich']);

        // Effective config uses dotted keys (flat map) — assert the literal key.
        $this->assertSame('pickable', $response->json('effective')['board.scope']);
    }

    public function test_economy_profile_bumps_version_and_returns_terse_next_only(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $this->pickableTask($project, 'C2');

        $cfg = $this->putJson("/api/projects/{$project->alias}/config", ['profile' => 'economy'])
            ->assertOk()
            ->assertJsonPath('config_version', 2);
        $this->assertSame('next_only', $cfg->json('effective')['board.scope']);

        $board = $this->get("/api/projects/{$project->alias}/board");
        $board->assertOk()
            ->assertHeader('X-Planstack-Config-Version', '2');
        $this->assertStringContainsString('text/plain', $board->headers->get('Content-Type'));

        // next_only ⇒ exactly one task line (lines starting with a task name).
        $taskLines = collect(explode("\n", trim($board->getContent())))
            ->filter(fn ($l) => $l !== '' && ! str_starts_with($l, '#'));
        $this->assertCount(1, $taskLines);
    }

    public function test_etag_yields_304_when_board_unchanged(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $this->putJson("/api/projects/{$project->alias}/config", ['profile' => 'economy']);

        $first = $this->get("/api/projects/{$project->alias}/board");
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        $this->get("/api/projects/{$project->alias}/board", ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_complete_bundles_pr_and_merge_in_one_call(): void
    {
        [$user, $project] = $this->ownedProject();
        $task = $this->pickableTask($project, 'C1');
        $task->update(['claimed_by_id' => $user->id, 'status' => TaskStatus::IN_PROGRESS]);

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/complete", [
            'pr_number' => 42,
            'merge' => true,
        ])->assertOk();

        $task->refresh();
        $this->assertEquals(42, $task->pr_number);
        $this->assertSame(TaskStatus::MERGED, $task->status);
        $this->assertNotNull($task->merged_at);
    }

    public function test_claim_return_details_false_returns_minimal_ack(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->pickableTask($project, 'C1');
        $this->putJson("/api/projects/{$project->alias}/config", [
            'overrides' => ['claim.return_details' => false],
        ])->assertOk();

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/claim")
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'status', 'display_status'])
            ->assertJsonMissingPath('summary')
            ->assertJsonMissingPath('gate');
    }

    public function test_drift_hints_appear_only_when_client_version_is_stale(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        // A client-hint delta on an otherwise full/JSON board.
        $this->putJson("/api/projects/{$project->alias}/config", [
            'overrides' => ['execution.mode' => 'headless'],
        ])->assertOk();

        // No known-version header ⇒ hints delta included (flat dotted key).
        $board = $this->getJson("/api/projects/{$project->alias}/board")->assertOk();
        $this->assertSame('headless', $board->json('client_hints')['execution.mode']);

        // Client already knows the current version ⇒ no hints block.
        $this->getJson("/api/projects/{$project->alias}/board", [
            'X-Planstack-Client-Config-Version' => (string) $project->fresh()->config_version,
        ])->assertOk()->assertJsonMissingPath('client_hints');
    }

    public function test_invalid_override_value_is_rejected(): void
    {
        [, $project] = $this->ownedProject();

        $this->putJson("/api/projects/{$project->alias}/config", [
            'overrides' => ['board.scope' => 'bogus'],
        ])->assertStatus(422);
    }
}
