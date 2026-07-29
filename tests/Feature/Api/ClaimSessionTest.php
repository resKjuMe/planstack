<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\TrackClaimSession;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Das Session-Lease auf dem Claim: welche Worker-Session hält einen Task, und
 * lebt sie noch? Deckt die beiden Eigenschaften ab, die das Feature nützlich
 * machen — das Label hängt am Claim (wird also mit ihm geräumt) und der
 * Heartbeat frischt ausschließlich das eigene Lease auf.
 */
class ClaimSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);

        Sanctum::actingAs($user);

        return [$user, $project];
    }

    private function task(Project $project, array $attrs = []): Task
    {
        return $project->tasks()->create(array_merge(
            ['name' => 'SESS', 'summary' => 'a task'],
            $attrs,
        ));
    }

    /** @param array<string, string> $headers */
    private function claim(Project $project, Task $task, array $headers = []): TestResponse
    {
        return $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/claim", [], $headers);
    }

    public function test_claim_with_session_header_stamps_label_and_heartbeat(): void
    {
        [$user, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => 'L2LR-Review'])
            ->assertOk();

        $task->refresh();
        $this->assertSame($user->id, $task->claimed_by_id);
        $this->assertSame('L2LR-Review', $task->claim_session_label);
        $this->assertNotNull($task->claim_seen_at);
    }

    public function test_claim_without_session_header_leaves_the_lease_empty(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task)->assertOk();

        $task->refresh();
        $this->assertNotNull($task->claimed_by_id);
        // Ein Claim aus dem Board (Mensch, keine Session) darf kein Badge erzeugen.
        $this->assertNull($task->claim_session_label);
        $this->assertNull($task->claim_seen_at);
    }

    public function test_release_clears_the_session_lease(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => 'worker-1'])->assertOk();
        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/release")->assertOk();

        $task->refresh();
        $this->assertNull($task->claimed_by_id);
        $this->assertNull($task->claim_session_label);
        $this->assertNull($task->claim_seen_at);
    }

    public function test_a_long_label_is_truncated_to_the_column_width(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => str_repeat('x', 200)])->assertOk();

        $this->assertSame(60, mb_strlen($task->refresh()->claim_session_label));
    }

    public function test_own_session_refreshes_the_heartbeat_on_a_later_request(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => 'worker-1'])->assertOk();

        // Heartbeat künstlich altern lassen (quiet, damit nichts anderes anspringt).
        $stale = now()->subHour();
        Task::whereKey($task->id)->update(['claim_seen_at' => $stale]);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => 'worker-1',
        ])->assertOk();

        $this->assertTrue(
            $task->refresh()->claim_seen_at->greaterThan($stale),
            'Ein Zugriff der haltenden Session muss den Heartbeat auffrischen.',
        );
    }

    public function test_another_session_does_not_refresh_a_foreign_heartbeat(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => 'worker-1'])->assertOk();

        $stale = now()->subHour()->startOfSecond();
        Task::whereKey($task->id)->update(['claim_seen_at' => $stale]);

        // Gleicher Nutzer, andere Session: darf das fremde Lease NICHT am Leben
        // halten — sonst sähe eine tote Session aktiv aus, sobald irgendwer im
        // Board denselben Task ansieht.
        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => 'worker-2',
        ])->assertOk();

        $this->assertTrue($task->refresh()->claim_seen_at->equalTo($stale));
    }

    public function test_a_status_change_keeps_the_lease_while_the_claim_stands(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => 'worker-1'])->assertOk();

        // Fortschritt ohne Session-Header — etwa der Mensch, der die Karte im Board
        // eine Spalte weiter zieht. Der Claim bleibt, also muss das Label bleiben:
        // sonst verlöre der Task sein Badge beim ersten Statuswechsel.
        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->assertSame('worker-1', $task->refresh()->claim_session_label);
    }

    public function test_claim_next_stamps_the_calling_session(): void
    {
        [, $project] = $this->ownedProject();
        $this->task($project);

        $this->postJson("/api/projects/{$project->alias}/claim-next", [], [
            TrackClaimSession::HEADER => 'auto-worker',
        ])->assertOk();

        $this->assertSame('auto-worker', $project->tasks()->sole()->claim_session_label);
    }

    public function test_the_task_resource_exposes_the_lease(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->claim($project, $task, [TrackClaimSession::HEADER => 'worker-1'])->assertOk();

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}?fields=full")
            ->assertOk()
            ->assertJsonPath('data.claim_session', 'worker-1');
    }
}
