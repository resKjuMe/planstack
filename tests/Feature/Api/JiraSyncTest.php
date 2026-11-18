<?php

namespace Tests\Feature\Api;

use App\Models\Phase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JiraSyncTest extends TestCase
{
    use RefreshDatabase;

    private function ownedProject(array $attrs = []): Project
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id, ...$attrs]);
        Sanctum::actingAs($user);

        return $project;
    }

    public function test_jira_sync_groups_tasks_by_phase_with_their_jira_keys(): void
    {
        $project = $this->ownedProject(['jira_epic_key' => 'MN-100']);
        $second = Phase::factory()->create(['project_id' => $project->id, 'name' => 'Zwei', 'position' => 2, 'jira_key' => 'MN-102']);
        $first = Phase::factory()->create(['project_id' => $project->id, 'name' => 'Eins', 'position' => 1]);

        $make = fn (string $name, ?int $phaseId, array $attrs = []) => $project->tasks()->create([
            'created_by_id' => $project->created_by_id,
            'name' => $name,
            'summary' => "Summary {$name}",
            'phase_id' => $phaseId,
            ...$attrs,
        ]);
        $a10 = $make('A10', $first->id);
        $a2 = $make('A2', $first->id, ['jira_key' => 'MN-103', 'description_acceptance_criteria' => 'AK']);
        $make('B1', $second->id);
        $make('X1', null);
        $a10->prerequisites()->sync([$a2->id]);

        $this->getJson("/api/projects/{$project->alias}/jira-sync")
            ->assertOk()
            ->assertJsonPath('project.jira_epic_key', 'MN-100')
            ->assertJsonPath('phases.0.name', 'Eins')
            ->assertJsonPath('phases.0.jira_key', null)
            ->assertJsonPath('phases.0.tasks.0.name', 'A2')
            ->assertJsonPath('phases.0.tasks.0.jira_key', 'MN-103')
            ->assertJsonPath('phases.0.tasks.0.acceptance_criteria', 'AK')
            ->assertJsonPath('phases.0.tasks.1.name', 'A10')
            ->assertJsonPath('phases.0.tasks.1.gate', ['A2'])
            ->assertJsonPath('phases.1.jira_key', 'MN-102')
            ->assertJsonPath('phases.1.tasks.0.name', 'B1')
            ->assertJsonPath('unphased_tasks.0.name', 'X1');
    }

    public function test_jira_keys_are_normalized_on_write(): void
    {
        $project = $this->ownedProject();
        $phase = Phase::factory()->create(['project_id' => $project->id]);
        $task = $project->tasks()->create([
            'created_by_id' => $project->created_by_id,
            'name' => 'T1',
            'summary' => 'T1',
        ]);

        $this->patchJson("/api/projects/{$project->alias}", ['jira_epic_key' => ' mn-1 '])
            ->assertOk()
            ->assertJsonPath('data.jira_epic_key', 'MN-1');
        $this->patchJson("/api/projects/{$project->alias}/phases/{$phase->id}", ['jira_key' => 'mn-2'])
            ->assertOk()
            ->assertJsonPath('data.jira_key', 'MN-2');
        $this->patchJson("/api/projects/{$project->alias}/tasks/{$task->id}", ['jira_key' => 'mn-3'])
            ->assertOk()
            ->assertJsonPath('data.jira_key', 'MN-3');

        $this->patchJson("/api/projects/{$project->alias}/tasks/{$task->id}", ['jira_key' => ''])
            ->assertOk();
        $this->assertNull($task->fresh()->jira_key);
    }

    public function test_invalid_jira_key_is_rejected(): void
    {
        $project = $this->ownedProject();
        $task = $project->tasks()->create([
            'created_by_id' => $project->created_by_id,
            'name' => 'T1',
            'summary' => 'T1',
        ]);

        $this->patchJson("/api/projects/{$project->alias}/tasks/{$task->id}", ['jira_key' => 'kein key'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jira_key');
        $this->patchJson("/api/projects/{$project->alias}", ['jira_epic_key' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jira_epic_key');
    }
}
