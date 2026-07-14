<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskStoreTest extends TestCase
{
    use RefreshDatabase;

    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        Sanctum::actingAs($user);

        return [$user, $project];
    }

    public function test_store_persists_acceptance_criteria_via_public_field_name(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->postJson("/api/projects/{$project->alias}/tasks", [
            'name' => 'C23',
            'summary' => 'Beispieltask',
            'acceptance_criteria' => '- AK 1\n- AK 2',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.acceptance_criteria', '- AK 1\n- AK 2');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'name' => 'C23',
            'description_acceptance_criteria' => '- AK 1\n- AK 2',
        ]);
    }

    public function test_store_still_accepts_the_legacy_column_field_name(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->postJson("/api/projects/{$project->alias}/tasks", [
            'name' => 'C24',
            'summary' => 'Legacy-Feld',
            'description_acceptance_criteria' => 'Altes Feld',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.acceptance_criteria', 'Altes Feld');
    }

    public function test_store_lets_the_public_field_win_when_both_are_sent(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->postJson("/api/projects/{$project->alias}/tasks", [
            'name' => 'C25',
            'summary' => 'Beide Felder',
            'acceptance_criteria' => 'gewinnt',
            'description_acceptance_criteria' => 'verliert',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.acceptance_criteria', 'gewinnt');

        $this->assertSame('gewinnt', Task::firstWhere('name', 'C25')->description_acceptance_criteria);
    }
}
