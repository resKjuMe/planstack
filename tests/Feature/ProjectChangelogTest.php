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
class ProjectChangelogTest extends TestCase
{
    use RefreshDatabase;

    private function headlineText(array $row): string
    {
        return collect($row['headline'])->pluck('v')->implode('');
    }

    /**
     * True for a task's own status-arrow headline ("T1 oldLabel → newLabel"),
     * as opposed to a generic "T1 aktualisiert" or a merged concern row.
     */
    private function isTaskStatusArrow(array $row): bool
    {
        return (bool) preg_match('/^T1\s.*→/', $this->headlineText($row));
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

        $response = $this->get(route('projects.changelog', $project));

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

        $response = $this->get(route('projects.changelog', $project));
        $response->assertOk();

        $rows = collect($response->viewData('changes')->items());

        // No standalone task status-arrow row — it should be folded away.
        $standalone = $rows->first(fn ($row) => $this->isTaskStatusArrow($row));
        $this->assertNull($standalone);

        $concernRow = $rows->first(fn ($row) => str_contains($this->headlineText($row), 'Concern zu'));
        $this->assertNotNull($concernRow);
        $this->assertStringContainsString('T1', $this->headlineText($concernRow));
        $this->assertStringContainsString('kritisch', $this->headlineText($concernRow));

        $taskSection = collect($concernRow['sections'])->first(fn ($s) => $s['label'] === 'Task aktualisiert');
        $this->assertNotNull($taskSection);
        $statusRow = collect($taskSection['visible'])->firstWhere('field', 'Status');
        $this->assertSame('kritisch', $statusRow['new']);
    }

    /**
     * A status change should get the highlighted arrow headline even when
     * another field changes in the same update (e.g. MERGED also stamps
     * merged_at) — not just for pure, single-field status flips.
     */
    public function test_status_change_is_highlighted_even_with_other_fields_changed_too(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $this->actingAs($user);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'name' => 'T1',
            'status' => TaskStatus::IN_REVIEW,
        ]);

        $task->update(['status' => TaskStatus::MERGED->value, 'merged_at' => now()]);

        $response = $this->get(route('projects.changelog', $project));
        $response->assertOk();

        $rows = collect($response->viewData('changes')->items());
        $mergeRow = $rows->first(fn ($row) => $this->isTaskStatusArrow($row));

        $this->assertNotNull($mergeRow);
        $this->assertStringContainsString('gemerged', $this->headlineText($mergeRow));
        $this->assertStringContainsString('in Review', $this->headlineText($mergeRow));
    }

    /**
     * Setting a PR number and flipping the status to IN_REVIEW are two
     * separate requests (TaskController::pr / ::status) but happen moments
     * apart — the PR-number-only row should fold into the status row instead
     * of appearing as its own "T1 aktualisiert" line.
     */
    public function test_pr_number_folds_into_the_accompanying_status_change(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $this->actingAs($user);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'name' => 'T1',
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $task->update(['pr_number' => 8076]);
        $task->update(['status' => TaskStatus::IN_REVIEW->value]);

        $response = $this->get(route('projects.changelog', $project));
        $response->assertOk();

        $rows = collect($response->viewData('changes')->items());

        // No standalone "T1 · aktualisiert" row just for the PR number.
        $standalone = $rows->first(fn ($row) => $this->headlineText($row) === 'T1 · aktualisiert');
        $this->assertNull($standalone);

        $mergeRow = $rows->first(fn ($row) => $this->isTaskStatusArrow($row));
        $this->assertNotNull($mergeRow);
        $this->assertStringContainsString('in Review', $this->headlineText($mergeRow));
        $this->assertStringContainsString('#8076', $this->headlineText($mergeRow));

        $fieldRows = collect($mergeRow['sections'][0]['visible'])->merge($mergeRow['sections'][0]['hidden']);
        $prRow = $fieldRows->firstWhere('field', 'PR-Nummer');
        $this->assertNotNull($prRow);
        $this->assertSame('8076', $prRow['new']);
    }

    /**
     * Claiming a task sets claimed_by_id/claimed_at/status in one update, so
     * the claimer's initials should ride along on the status arrow headline.
     */
    public function test_claiming_a_task_shows_the_claimers_initials(): void
    {
        $user = User::factory()->create(['name' => 'Jonas Grobe']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $this->actingAs($user);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'name' => 'T1',
            'status' => TaskStatus::PICKABLE,
        ]);

        $task->update([
            'claimed_by_id' => $user->id,
            'claimed_at' => now(),
            'status' => TaskStatus::CLAIMED->value,
        ]);

        $response = $this->get(route('projects.changelog', $project));
        $response->assertOk();

        $rows = collect($response->viewData('changes')->items());
        $claimRow = $rows->first(fn ($row) => $this->isTaskStatusArrow($row));

        $this->assertNotNull($claimRow);
        $this->assertStringContainsString('beansprucht (JG)', $this->headlineText($claimRow));
    }

    /**
     * A deleted task's own "deleted" audit row carries the full old
     * snapshot (incl. project_id) even though the task itself is gone from
     * the live table — the changelog must still surface it instead of
     * silently dropping every deletion.
     */
    public function test_deleted_task_still_shows_up_in_the_changelog(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $this->actingAs($user);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'name' => 'T1',
        ]);
        $task->delete();

        $response = $this->get(route('projects.changelog', $project));
        $response->assertOk();

        $rows = collect($response->viewData('changes')->items());
        $deletedRow = $rows->first(fn ($row) => $this->headlineText($row) === 'T1 gelöscht');
        $this->assertNotNull($deletedRow);
    }
}
