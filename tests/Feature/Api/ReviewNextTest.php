<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /api/projects/{project}/review-next — Auswahl des nächsten Review-Tasks:
 * eigener, schon reservierter Review zuerst, dann die freien (älteste zuerst);
 * fremde Reservierungen und eigene (selbst umgesetzte) Tasks bleiben außen vor.
 */
class ReviewNextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);

        return [$user, $project];
    }

    private function statusId(Project $project, StatusRole $role): int
    {
        return $project->organization->statusForRole($role)->id;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function task(Project $project, string $name, StatusRole $role, array $attrs = []): Task
    {
        return $project->tasks()->create(array_merge([
            'created_by_id' => $project->created_by_id,
            'name' => $name,
            'summary' => $name,
            'pr_number' => 100 + $project->tasks()->count(),
            'status_id' => $this->statusId($project, $role),
        ], $attrs));
    }

    public function test_it_picks_the_oldest_free_task_from_the_pool_and_stamps_the_reviewer(): void
    {
        [$user, $project] = $this->ownedProject();
        $first = $this->task($project, 'A', StatusRole::REVIEWABLE);
        $this->task($project, 'B', StatusRole::REVIEWABLE);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/review-next")
            ->assertOk()
            ->assertJsonPath('data.name', 'A');

        $this->assertSame($user->id, $first->refresh()->reviewed_by);
    }

    public function test_an_own_already_reserved_review_is_returned_before_free_ones(): void
    {
        [$user, $project] = $this->ownedProject();
        // Älterer freier Task — würde ohne die Vorrangregel gewinnen.
        $free = $this->task($project, 'FREE', StatusRole::REVIEWABLE);
        // Eigener, schon übernommener Review (Status bleibt im Pool, da
        // review-next/review-claim nicht verschieben).
        $mine = $this->task($project, 'MINE', StatusRole::REVIEWABLE, ['reviewed_by' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/review-next")
            ->assertOk()
            ->assertJsonPath('data.name', 'MINE');

        // Fortgesetzt, nicht neu gestempelt — und der freie Task bleibt frei.
        $this->assertSame($user->id, $mine->refresh()->reviewed_by);
        $this->assertNull($free->refresh()->reviewed_by);
    }

    public function test_an_own_reserved_in_review_task_is_resumed_too(): void
    {
        [$user, $project] = $this->ownedProject();
        $mine = $this->task($project, 'MINE', StatusRole::IN_REVIEW, ['reviewed_by' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/review-next")
            ->assertOk()
            ->assertJsonPath('data.name', 'MINE')
            ->assertJsonPath('data.reviewed_by', $user->id);

        $this->assertSame($user->id, $mine->refresh()->reviewed_by);
    }

    public function test_a_review_reserved_by_someone_else_is_skipped(): void
    {
        [$user, $project] = $this->ownedProject();
        $other = User::factory()->create();
        $taken = $this->task($project, 'TAKEN', StatusRole::REVIEWABLE, ['reviewed_by' => $other->id]);
        $free = $this->task($project, 'FREE', StatusRole::REVIEWABLE);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/review-next")
            ->assertOk()
            ->assertJsonPath('data.name', 'FREE');

        $this->assertSame($other->id, $taken->refresh()->reviewed_by);
        $this->assertSame($user->id, $free->refresh()->reviewed_by);
    }

    public function test_an_own_review_with_a_recorded_result_is_not_re_offered(): void
    {
        [$user, $project] = $this->ownedProject();
        // Ergebnis erfasst → wartet auf den Statuswechsel, nicht auf Review-Arbeit.
        $this->task($project, 'DONE', StatusRole::REVIEWABLE, [
            'reviewed_by' => $user->id,
            'last_reviewed_at' => now(),
        ]);
        $free = $this->task($project, 'FREE', StatusRole::REVIEWABLE);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/review-next")
            ->assertOk()
            ->assertJsonPath('data.name', 'FREE');

        $this->assertSame($user->id, $free->refresh()->reviewed_by);
    }

    public function test_own_worked_tasks_and_tasks_without_pr_are_not_offered(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->task($project, 'MYWORK', StatusRole::REVIEWABLE, ['claimed_by_id' => $user->id]);
        $this->task($project, 'NOPR', StatusRole::REVIEWABLE, ['pr_number' => null]);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/review-next")
            ->assertOk()
            ->assertExactJson(['reviewing' => null]);
    }
}
