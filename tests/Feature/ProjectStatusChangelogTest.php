<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
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
 * show up with a resolved headline and causer.
 */
class ProjectStatusChangelogTest extends TestCase
{
    use RefreshDatabase;

    private function headlineText(array $row): string
    {
        return collect($row['headline'])->pluck('v')->implode('');
    }

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

        $rows = collect($changes->items());
        $this->assertTrue($rows->contains(fn ($row) => str_contains($this->headlineText($row), $project->alias)));

        $taskRow = $rows->first(fn ($row) => str_contains($this->headlineText($row), 'T1') && str_contains($this->headlineText($row), 'aktualisiert'));
        $this->assertNotNull($taskRow);
        $this->assertTrue($rows->contains(fn ($row) => $row['causer'] === 'Ada Lovelace'));

        $fieldRows = collect($taskRow['sections'][0]['visible'])->merge($taskRow['sections'][0]['hidden']);
        $this->assertTrue($fieldRows->contains(fn ($r) => $r['field'] === 'Zusammenfassung'));
    }

    /**
     * Filing a concern also flips the task to CONCERNED in the same request
     * (TaskConcernController::update). Those are two audit rows with the
     * same timestamp; the changelog should fold the status flip into the
     * concern row instead of listing the task update separately.
     */
    public function test_concern_creation_folds_the_resulting_status_change_into_its_own_row(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $this->actingAs($user);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'name' => 'T1',
            'status' => TaskStatus::PICKABLE,
        ]);

        $this->put(route('projects.tasks.concern.update', [$project, $task]), [
            'summary' => 'Etwas blockiert',
        ])->assertRedirect();

        $response = $this->get(route('projects.status.changelog', $project));
        $response->assertOk();

        $rows = collect($response->viewData('changes')->items());

        // No standalone "T1 → problematisch" row — it should be folded away.
        $standalone = $rows->first(fn ($row) => str_starts_with($this->headlineText($row), 'T1 → '));
        $this->assertNull($standalone);

        $concernRow = $rows->first(fn ($row) => str_contains($this->headlineText($row), 'Concern zu'));
        $this->assertNotNull($concernRow);
        $this->assertStringContainsString('T1', $this->headlineText($concernRow));
        $this->assertStringContainsString('problematisch', $this->headlineText($concernRow));

        $taskSection = collect($concernRow['sections'])->first(fn ($s) => $s['label'] === 'Task aktualisiert');
        $this->assertNotNull($taskSection);
        $statusRow = collect($taskSection['visible'])->firstWhere('field', 'Status');
        $this->assertSame('problematisch', $statusRow['new']);
    }
}
