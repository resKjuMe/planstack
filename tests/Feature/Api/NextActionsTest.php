<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Http\Middleware\TrackClaimSession;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /projects/{p}/next-actions — mehrere Arbeitseinheiten für parallele Worker in
 * einem Aufruf. Geprüft wird vor allem, was bei Parallelbetrieb schiefgehen KANN:
 * derselbe Task zweimal vergeben, Reservierung auf den Aufrufer statt auf den Worker
 * gestempelt, ein ablaufendes Fix-Lease mitten in der Arbeit.
 */
class NextActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownedProject(): array
    {
        $user = User::factory()->create(['name' => 'Christian Mietze']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);

        return [$user, $project];
    }

    private function inReviewId(Project $project): int
    {
        return $project->organization->statusForRole(StatusRole::IN_REVIEW)->id;
    }

    private function task(Project $project, string $name, array $attrs = []): Task
    {
        return $project->tasks()->create([
            'created_by_id' => $project->created_by_id,
            'name' => $name,
            'summary' => $name,
            ...$attrs,
        ]);
    }

    private function maxWorkers(Project $project, int $max): void
    {
        $project->update(['config' => ['overrides' => ['parallelism.max_workers' => $max]]]);
    }

    /** Aufruf des Batch-Endpunkts mit Supervisor-Session-Header. */
    private function batch(Project $project, array $payload = [])
    {
        return $this->postJson(
            "/api/projects/{$project->alias}/next-actions",
            $payload,
            [TrackClaimSession::HEADER => "auto {$project->alias}"],
        );
    }

    public function test_every_worker_gets_its_own_task(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 3);
        foreach (['A1', 'A2', 'A3'] as $name) {
            $this->task($project, $name);
        }
        Sanctum::actingAs($user);

        $response = $this->batch($project, ['count' => 3])->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertSame(3, $response->json('workers'));
        // Drei verschiedene Tasks — genau das, was ein Stapel garantieren muss.
        $names = array_map(fn ($unit) => $unit['task']['name'], $data);
        $this->assertSame(['A1', 'A2', 'A3'], collect($names)->sort()->values()->all());
        $this->assertSame(3, $project->tasks()->where('claimed_by_id', $user->id)->count());
    }

    public function test_each_unit_carries_the_session_label_it_is_reserved_with(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 2);
        $this->task($project, 'A1');
        $this->task($project, 'A2');
        Sanctum::actingAs($user);

        $data = $this->batch($project, ['count' => 2])->assertOk()->json('data');

        foreach ($data as $index => $unit) {
            $expected = "work {$project->alias}/{$unit['task']['name']} #".($index + 1);
            $this->assertSame($expected, $unit['session']);

            $task = $project->tasks()->where('name', $unit['task']['name'])->sole();
            // Das Claim-Lease gehört dem WORKER, nicht dem Supervisor („auto …"):
            // sonst trifft der Heartbeat des Workers sein eigenes Lease nicht.
            $this->assertSame($expected, $task->claim_session_label);
            $this->assertNotNull($task->claim_seen_at);
            // Der „arbeitet daran"-Vermerk steht schon vor dem ersten eigenen Aufruf
            // des Workers auf dem Board — mit Initialen des Betreibers davor.
            $this->assertSame('CM '.$expected, $task->active_session_label);
            $this->assertNotNull($task->active_session_seen_at);
        }
    }

    public function test_the_workers_heartbeat_keeps_its_own_lease_alive(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 1);
        $this->task($project, 'A1');
        Sanctum::actingAs($user);

        $unit = $this->batch($project, ['count' => 1])->assertOk()->json('data.0');
        $task = $project->tasks()->where('name', 'A1')->sole();
        $task->update(['claim_seen_at' => now()->subMinutes(20)]);

        // Ein beliebiger Aufruf DIESES Workers (Label aus der Antwort) frischt auf.
        $this->getJson(
            "/api/projects/{$project->alias}/tasks/A1",
            [TrackClaimSession::HEADER => $unit['session']],
        )->assertOk();

        $this->assertTrue($task->refresh()->claim_seen_at->gt(now()->subMinute()));
    }

    public function test_a_fix_lease_lives_with_the_worker_and_dies_with_its_unit(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 1);
        $reviewId = $this->inReviewId($project);
        $other = User::factory()->create();
        // Fremd beansprucht: `fix` arbeitet typischerweise am PR eines anderen.
        $this->task($project, 'FIXY', [
            'pr_number' => 10, 'status_id' => $reviewId, 'pr_ci_status' => 'FAILURE',
            'claimed_by_id' => $other->id,
        ]);
        Sanctum::actingAs($user);

        $unit = $this->batch($project, ['count' => 1])->assertOk()->json('data.0');
        $this->assertSame('fix', $unit['action']);

        $task = $project->tasks()->where('name', 'FIXY')->sole();
        $header = [TrackClaimSession::HEADER => $unit['session']];

        // Kurz vor dem Ablauf: ein Aufruf dieses Workers verlängert das Lease, damit
        // kein zweiter Worker denselben PR bekommt, während hier noch gearbeitet wird.
        $task->update(['fix_lease_expires_at' => now()->addSeconds(30)]);
        $this->getJson("/api/projects/{$project->alias}/tasks/FIXY", $header)->assertOk();
        $this->assertTrue($task->refresh()->fix_lease_expires_at->gt(now()->addMinutes(5)));

        // Ende der Arbeitseinheit → Lease sofort frei, nicht erst nach Ablauf.
        $this->postJson("/api/projects/{$project->alias}/tasks/FIXY/events", ['event' => 'POLISHED'], $header)
            ->assertOk();

        $task->refresh();
        $this->assertNull($task->fix_leased_by);
        $this->assertNull($task->fix_lease_expires_at);
    }

    public function test_the_project_config_caps_the_number_of_workers(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 2);
        foreach (['A1', 'A2', 'A3', 'A4'] as $name) {
            $this->task($project, $name);
        }
        Sanctum::actingAs($user);

        $response = $this->batch($project, ['count' => 4])->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('workers', 2)
            ->assertJsonPath('requested', 4)
            ->assertJsonPath('max_workers', 2);
    }

    public function test_without_a_count_the_configured_maximum_is_used(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 3);
        foreach (['A1', 'A2', 'A3', 'A4'] as $name) {
            $this->task($project, $name);
        }
        Sanctum::actingAs($user);

        $this->batch($project)->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('requested', 3);
    }

    public function test_fewer_units_than_workers_when_less_work_is_due(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 4);
        $this->task($project, 'A1');
        Sanctum::actingAs($user);

        $this->batch($project, ['count' => 4])->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('workers', 1);
    }

    public function test_an_empty_board_hands_out_nothing(): void
    {
        [$user, $project] = $this->ownedProject();
        Sanctum::actingAs($user);

        $this->batch($project, ['count' => 3])->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('workers', 0);
    }

    public function test_the_batch_keeps_the_server_side_priority(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 3);
        $reviewId = $this->inReviewId($project);
        $other = User::factory()->create();

        $this->task($project, 'FIXY', [
            'pr_number' => 10, 'status_id' => $reviewId, 'pr_ci_status' => 'FAILURE',
        ]);
        // Fremd beansprucht, damit dieser Task als Review-Kandidat gilt (eigene nicht).
        $this->task($project, 'REVVY', [
            'pr_number' => 11, 'status_id' => $reviewId, 'claimed_by_id' => $other->id,
        ]);
        $this->task($project, 'WORKY');
        Sanctum::actingAs($user);

        $data = $this->batch($project, ['count' => 3])->assertOk()->json('data');

        $this->assertSame(['fix', 'review', 'work'], array_column($data, 'action'));
        $this->assertSame(['FIXY', 'REVVY', 'WORKY'], array_map(fn ($u) => $u['task']['name'], $data));
        $this->assertSame('CI FAILURE', $data[0]['reason']);
    }

    public function test_a_task_someone_is_visibly_working_on_is_not_handed_out(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 2);
        $reviewId = $this->inReviewId($project);

        // Rotes CI, Lease frei — aber ein Worker sitzt sichtbar daran (frischer
        // Vermerk). Ohne diese Prüfung bekäme ein zweiter Worker denselben PR.
        $this->task($project, 'FIXY', [
            'pr_number' => 10, 'status_id' => $reviewId, 'pr_ci_status' => 'FAILURE',
            'active_session_label' => 'CM fix X/FIXY #1', 'active_session_seen_at' => now()->subMinute(),
        ]);
        $this->task($project, 'WORKY');
        Sanctum::actingAs($user);

        $data = $this->batch($project, ['count' => 2])->assertOk()->json('data');

        $this->assertSame(['work'], array_column($data, 'action'));
        $this->assertSame('WORKY', $data[0]['task']['name']);
    }

    public function test_a_pickable_task_with_a_live_worker_stays_untouched(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 2);
        // Unbeansprucht (also pickbar), aber ein Worker sitzt noch daran — etwa weil
        // der Claim gerade freigegeben wurde, während die Arbeit weiterläuft.
        $this->task($project, 'BUSY', [
            'active_session_label' => 'CM work X/BUSY #1', 'active_session_seen_at' => now(),
        ]);
        $this->task($project, 'FREE');
        Sanctum::actingAs($user);

        $data = $this->batch($project, ['count' => 2])->assertOk()->json('data');

        $this->assertSame(['FREE'], array_map(fn ($u) => $u['task']['name'], $data));
    }

    public function test_two_calls_in_a_row_never_hand_out_the_same_task(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 1);
        $this->task($project, 'A1');
        $this->task($project, 'A2');
        Sanctum::actingAs($user);

        $first = $this->batch($project, ['count' => 1])->assertOk()->json('data.0.task.name');
        $second = $this->batch($project, ['count' => 1])->assertOk()->json('data.0.task.name');

        $this->assertNotSame($first, $second);
    }

    public function test_an_abandoned_note_stops_blocking_after_the_ttl(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 1);
        $reviewId = $this->inReviewId($project);
        $ttl = (int) config('planstack.active_session_ttl_minutes', 10);

        $this->task($project, 'FIXY', [
            'pr_number' => 10, 'status_id' => $reviewId, 'pr_ci_status' => 'FAILURE',
            'active_session_label' => 'CM fix X/FIXY #1',
            'active_session_seen_at' => now()->subMinutes($ttl + 1),
        ]);
        Sanctum::actingAs($user);

        $this->batch($project, ['count' => 1])->assertOk()
            ->assertJsonPath('data.0.action', 'fix')
            ->assertJsonPath('data.0.task.name', 'FIXY');
    }

    public function test_the_auto_instructions_come_once_for_the_whole_batch(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->maxWorkers($project, 2);
        $this->task($project, 'A1');
        $this->task($project, 'A2');
        Sanctum::actingAs($user);

        $payload = $this->batch($project, ['count' => 2])->assertOk()->json();

        $this->assertArrayHasKey('command_instructions', $payload);
        $this->assertStringContainsString('Auto-Modus', $payload['command_instructions']);
        // Nicht je Einheit — das ist der Token-Grund für den Stapel-Endpunkt.
        foreach ($payload['data'] as $unit) {
            $this->assertArrayNotHasKey('command_instructions', $unit);
        }
    }

    public function test_an_invalid_count_is_rejected(): void
    {
        [$user, $project] = $this->ownedProject();
        Sanctum::actingAs($user);

        $this->batch($project, ['count' => 0])->assertStatus(422);
        $this->batch($project, ['count' => 99])->assertStatus(422);
    }

    public function test_the_single_endpoint_also_names_the_worker_session(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->task($project, 'A1');
        Sanctum::actingAs($user);

        $this->postJson(
            "/api/projects/{$project->alias}/next-action",
            [],
            [TrackClaimSession::HEADER => "auto {$project->alias}"],
        )->assertOk()->assertJsonPath('session', "work {$project->alias}/A1 #1");
    }
}
