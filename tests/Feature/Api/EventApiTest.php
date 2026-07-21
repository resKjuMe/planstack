<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Enums\TaskEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project, 2: Task}
     */
    private function ownedTask(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $task = $project->tasks()->create(['name' => 'E1', 'summary' => 'Event-Task']);
        Sanctum::actingAs($user);

        return [$user, $project, $task];
    }

    public function test_configured_event_moves_task_into_target_status_and_applies_effects(): void
    {
        [$user, $project, $task] = $this->ownedTask();
        $organization = $project->organization;
        $target = $organization->statusForRole(StatusRole::IN_PROGRESS);

        // PROCESSING ist per Default vorkonfiguriert → updateOrCreate statt create.
        $organization->eventAutomations()->updateOrCreate(
            ['event' => TaskEvent::PROCESSING->value],
            [
                'target_status_id' => $target->id,
                'overridable_status_ids' => null,
                'effects' => [
                    ['field' => 'claimed_by_id', 'value' => '@actor', 'only_if_empty' => false],
                    ['field' => 'affected_files', 'value' => '7', 'only_if_empty' => false],
                ],
            ],
        );

        $response = $this->postJson('/api/events', [
            'task_id' => $task->id,
            'event' => 'PROCESSING',
        ]);

        $response->assertOk()
            ->assertJsonPath('event', 'PROCESSING')
            ->assertJsonPath('configured', true)
            ->assertJsonPath('status_changed', true)
            ->assertJsonPath('status', 'IN_PROGRESS');

        $task->refresh();
        $this->assertSame($target->id, $task->status_id);
        $this->assertSame($user->id, $task->claimed_by_id);
        $this->assertSame(7, $task->affected_files);

        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'event' => 'PROCESSING',
        ]);
    }

    public function test_unconfigured_event_is_a_noop_but_is_logged(): void
    {
        [, , $task] = $this->ownedTask();
        $before = $task->status_id;

        // PUBLISHED hat per Default keine Automation ⇒ reine Meldung.
        $response = $this->postJson('/api/events', [
            'task_id' => $task->id,
            'event' => 'PUBLISHED',
        ]);

        $response->assertOk()
            ->assertJsonPath('configured', false)
            ->assertJsonPath('status_changed', false);

        $this->assertSame($before, $task->refresh()->status_id);
        $this->assertDatabaseHas('task_events', ['task_id' => $task->id, 'event' => 'PUBLISHED']);
    }

    public function test_status_is_kept_when_current_status_is_not_overridable(): void
    {
        [, $project, $task] = $this->ownedTask();
        $organization = $project->organization;
        $target = $organization->statusForRole(StatusRole::MERGED);
        // Only allow overriding IN_REVIEW — the task sits in PICKABLE, so no change.
        $inReview = $organization->statusForRole(StatusRole::IN_REVIEW);
        $before = $task->status_id;

        $organization->eventAutomations()->updateOrCreate(
            ['event' => TaskEvent::MERGED->value],
            [
                'target_status_id' => $target->id,
                'overridable_status_ids' => [$inReview->id],
                'effects' => null,
            ],
        );

        $response = $this->postJson('/api/events', [
            'task_id' => $task->id,
            'event' => 'MERGED',
        ]);

        $response->assertOk()
            ->assertJsonPath('status_changed', false);

        $this->assertSame($before, $task->refresh()->status_id);
    }

    public function test_target_status_on_enter_effects_are_applied_on_the_event(): void
    {
        [$user, $project, $task] = $this->ownedTask();
        $organization = $project->organization;
        // MERGED carries a default on-enter effect (merged_at = @now).
        $target = $organization->statusForRole(StatusRole::MERGED);

        $organization->eventAutomations()->updateOrCreate(
            ['event' => TaskEvent::MERGED->value],
            [
                'target_status_id' => $target->id,
                'overridable_status_ids' => null, // immer überschreiben (Default hätte APPROVED)
                'effects' => null,
            ],
        );

        $this->postJson('/api/events', ['task_id' => $task->id, 'event' => 'MERGED'])
            ->assertOk()
            ->assertJsonPath('status_changed', true);

        $this->assertNotNull($task->refresh()->merged_at);
    }

    public function test_direct_status_call_cannot_override_event_driven_status(): void
    {
        [, $project, $task] = $this->ownedTask();
        $organization = $project->organization;

        // Org treibt den Status ereignisgesteuert: PROCESSING → IN_PROGRESS.
        $inProgress = $organization->statusForRole(StatusRole::IN_PROGRESS);
        $organization->eventAutomations()->updateOrCreate(
            ['event' => TaskEvent::PROCESSING->value],
            ['target_status_id' => $inProgress->id, 'overridable_status_ids' => null, 'effects' => null],
        );

        // Event setzt den Status.
        $this->postJson('/api/events', ['task_id' => $task->id, 'event' => 'PROCESSING'])
            ->assertOk()
            ->assertJsonPath('status', 'IN_PROGRESS');
        $this->assertSame($inProgress->id, $task->refresh()->status_id);

        // Ein (evtl. veralteter) Client sendet trotzdem einen direkten Status-Call.
        // Der Server ignoriert ihn: Status bleibt der per Event zugewiesene, kein 409.
        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/status", ['status' => 'analyze'])
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_PROGRESS');

        $this->assertSame($inProgress->id, $task->refresh()->status_id);
    }

    public function test_direct_status_call_still_applies_without_event_driven_status(): void
    {
        [, $project, $task] = $this->ownedTask();

        // Ereignisgesteuerte Zuweisung abschalten (Default-Seed hätte sie) ⇒ der
        // direkte, rollenbasierte Status-Call wirkt wie zuvor.
        $project->organization->eventAutomations()->update(['target_status_id' => null]);
        $this->assertFalse($project->organization->hasEventDrivenStatus());

        // CLAIMED → ANALYZING ist ein erlaubter Übergang (PICKABLE → ANALYZING nicht).
        $task->update(['status_id' => $project->organization->statusForRole(StatusRole::CLAIMED)->id]);

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/status", ['status' => 'analyze'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ANALYZING');

        $analyzing = $project->organization->statusForRole(StatusRole::ANALYZING);
        $this->assertSame($analyzing->id, $task->refresh()->status_id);
    }

    public function test_invalid_event_is_rejected(): void
    {
        [, , $task] = $this->ownedTask();

        $this->postJson('/api/events', ['task_id' => $task->id, 'event' => 'NOPE'])
            ->assertStatus(422);
    }

    public function test_missing_task_yields_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/events', ['task_id' => 999999, 'event' => 'CLAIMED'])
            ->assertNotFound();
    }

    public function test_non_member_may_not_send_events(): void
    {
        [, , $task] = $this->ownedTask();
        Sanctum::actingAs(User::factory()->create()); // a stranger

        $this->postJson('/api/events', ['task_id' => $task->id, 'event' => 'CLAIMED'])
            ->assertForbidden();
    }

    public function test_event_requires_authentication(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create(['name' => 'E9', 'summary' => 'x']);

        $this->postJson('/api/events', ['task_id' => $task->id, 'event' => 'CLAIMED'])
            ->assertUnauthorized();
    }
}
