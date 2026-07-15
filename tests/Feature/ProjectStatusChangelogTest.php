<?php

namespace Tests\Feature;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The changelog page combines audit rows from several per-entity audit
 * tables (project, task, phase, ...) into one paginated, sorted feed. This
 * locks that the route renders and that both created- and updated-actions
 * show up with a resolved subject and causer.
 */
class ProjectStatusChangelogTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_combines_project_and_task_changes(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);

        $this->actingAs($user);

        $phase = Phase::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'phase_id' => $phase->id,
            'name' => 'T1',
        ]);
        $task->update(['summary' => 'Updated summary']);

        $response = $this->get(route('projects.status.changelog', $project));

        $response->assertOk();

        $changes = $response->viewData('changes');
        $this->assertGreaterThanOrEqual(3, $changes->total()); // project created + task created + task updated

        $entities = collect($changes->items())->pluck('entity_label');
        $this->assertTrue($entities->contains('Projekt'));
        $this->assertTrue($entities->contains('Task'));

        $causers = collect($changes->items())->pluck('causer');
        $this->assertTrue($causers->contains('Ada Lovelace'));

        $updated = collect($changes->items())->firstWhere('entity_label', 'Task');
        $this->assertNotEmpty($updated['changes']);
    }
}
