<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\NextActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NextActionTest extends TestCase
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

    private function inReviewId(Project $project): int
    {
        return $project->organization->statusForRole(StatusRole::IN_REVIEW)->id;
    }

    private function resolver(): NextActionResolver
    {
        return app(NextActionResolver::class);
    }

    public function test_fix_wins_over_review_and_work_and_leases_the_task(): void
    {
        [$user, $project] = $this->ownedProject();
        $reviewId = $this->inReviewId($project);

        $fix = $project->tasks()->create([
            'name' => 'FIXY', 'summary' => 'red CI', 'pr_number' => 10,
            'status_id' => $reviewId, 'pr_ci_status' => 'FAILURE',
        ]);
        // Review-Kandidat (im Review-Pool, PR, kein Fix-Grund) …
        $project->tasks()->create([
            'name' => 'REVVY', 'summary' => 'ready to review', 'pr_number' => 11,
            'status_id' => $reviewId,
        ]);
        // … und ein pickbarer Work-Kandidat.
        $project->tasks()->create(['name' => 'WORKY', 'summary' => 'pickable']);

        $result = $this->resolver()->resolve($project, $user);

        $this->assertSame('fix', $result['action']);
        $this->assertSame($fix->id, $result['task']->id);
        $this->assertSame('CI FAILURE', $result['reason']);

        $fix->refresh();
        $this->assertSame($user->id, $fix->fix_leased_by);
        $this->assertNotNull($fix->fix_lease_expires_at);
    }

    public function test_review_wins_when_no_fix_and_stamps_reviewer(): void
    {
        [$user, $project] = $this->ownedProject();
        $reviewId = $this->inReviewId($project);

        $rev = $project->tasks()->create([
            'name' => 'REVVY', 'summary' => 'ready', 'pr_number' => 11, 'status_id' => $reviewId,
        ]);
        $project->tasks()->create(['name' => 'WORKY', 'summary' => 'pickable']);

        $result = $this->resolver()->resolve($project, $user);

        $this->assertSame('review', $result['action']);
        $this->assertSame($rev->id, $result['task']->id);
        $this->assertSame($user->id, $rev->refresh()->reviewed_by);
    }

    public function test_work_is_the_fallback_and_claims_the_task(): void
    {
        [$user, $project] = $this->ownedProject();
        $work = $project->tasks()->create(['name' => 'WORKY', 'summary' => 'pickable']);

        $result = $this->resolver()->resolve($project, $user);

        $this->assertSame('work', $result['action']);
        $this->assertSame($work->id, $result['task']->id);
        $this->assertSame($user->id, $work->refresh()->claimed_by_id);
    }

    public function test_none_when_nothing_is_due(): void
    {
        [$user, $project] = $this->ownedProject();
        // Ein bereits gemergter Task ist weder fix- noch review- noch pickbar.
        $project->tasks()->create([
            'name' => 'DONE', 'summary' => 'merged', 'pr_number' => 9,
            'status' => StatusRole::MERGED->value,
        ]);

        $result = $this->resolver()->resolve($project, $user);

        $this->assertSame('none', $result['action']);
        $this->assertNull($result['task']);
    }

    public function test_a_valid_foreign_lease_is_skipped_but_an_expired_one_is_reclaimed(): void
    {
        [$user, $project] = $this->ownedProject();
        $reviewId = $this->inReviewId($project);
        $other = User::factory()->create();

        $fix = $project->tasks()->create([
            'name' => 'FIXY', 'summary' => 'red CI', 'pr_number' => 10,
            'status_id' => $reviewId, 'pr_ci_status' => 'FAILURE',
            'fix_leased_by' => $other->id, 'fix_lease_expires_at' => now()->addMinutes(10),
        ]);
        $rev = $project->tasks()->create([
            'name' => 'REVVY', 'summary' => 'ready', 'pr_number' => 11, 'status_id' => $reviewId,
        ]);

        // Gültiges Fremd-Lease → fix übersprungen, review gewinnt.
        $result = $this->resolver()->resolve($project, $user);
        $this->assertSame('review', $result['action']);
        $this->assertSame($rev->id, $result['task']->id);

        // Lease abgelaufen → fix wird neu übernommen.
        $fix->update(['fix_lease_expires_at' => now()->subMinute()]);
        $rev->update(['reviewed_by' => null]); // review-Kandidat wieder frei machen
        $result = $this->resolver()->resolve($project, $user);
        $this->assertSame('fix', $result['action']);
        $this->assertSame($fix->id, $result['task']->id);
        $this->assertSame($user->id, $fix->refresh()->fix_leased_by);
    }

    public function test_endpoint_returns_action_and_task_payload(): void
    {
        [$user, $project] = $this->ownedProject();
        $work = $project->tasks()->create(['name' => 'WORKY', 'summary' => 'pickable']);
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->alias}/next-action")
            ->assertOk()
            ->assertJsonPath('action', 'work')
            ->assertJsonPath('data.name', 'WORKY');

        $this->assertSame($user->id, $work->refresh()->claimed_by_id);
    }
}
