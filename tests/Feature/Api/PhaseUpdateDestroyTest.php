<?php

namespace Tests\Feature\Api;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseUpdateDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        Sanctum::actingAs($user);

        return [$user, $project];
    }

    public function test_update_renames_and_repositions_a_phase(): void
    {
        [, $project] = $this->ownedProject();
        $phase = Phase::factory()->create(['project_id' => $project->id, 'name' => 'Alt', 'position' => 1]);

        $response = $this->putJson("/api/projects/{$project->alias}/phases/{$phase->id}", [
            'name' => 'Neu',
            'position' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Neu')
            ->assertJsonPath('data.position', 5);

        $this->assertDatabaseHas('phases', ['id' => $phase->id, 'name' => 'Neu', 'position' => 5]);
    }

    public function test_update_allows_partial_payload(): void
    {
        [, $project] = $this->ownedProject();
        $phase = Phase::factory()->create(['project_id' => $project->id, 'name' => 'Alt', 'position' => 3]);

        $this->patchJson("/api/projects/{$project->alias}/phases/{$phase->id}", ['name' => 'NurName'])
            ->assertOk()
            ->assertJsonPath('data.name', 'NurName')
            ->assertJsonPath('data.position', 3);
    }

    public function test_destroy_removes_phase_and_detaches_its_tasks(): void
    {
        [$user, $project] = $this->ownedProject();
        $phase = Phase::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'phase_id' => $phase->id,
        ]);

        $this->deleteJson("/api/projects/{$project->alias}/phases/{$phase->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('phases', ['id' => $phase->id]);
        // Task survives, only detached from the deleted phase.
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'phase_id' => null]);
    }

    public function test_phase_is_scoped_to_its_project(): void
    {
        [, $project] = $this->ownedProject();
        $other = Project::factory()->create();
        $foreignPhase = Phase::factory()->create(['project_id' => $other->id]);

        $this->deleteJson("/api/projects/{$project->alias}/phases/{$foreignPhase->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('phases', ['id' => $foreignPhase->id]);
    }

    public function test_non_member_cannot_delete_a_phase(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $owner->id]);
        $phase = Phase::factory()->create(['project_id' => $project->id]);

        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);

        $this->deleteJson("/api/projects/{$project->alias}/phases/{$phase->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('phases', ['id' => $phase->id]);
    }
}
