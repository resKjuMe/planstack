<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Enums\TaskEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskEventLog;
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
        // created_by_id explizit: die Spalte ist NOT NULL ohne Default, MySQL im
        // Strict-Mode weist das Insert sonst ab (SQLite verzeiht es stillschweigend).
        $task = $project->tasks()->create([
            'name' => 'E1',
            'summary' => 'Event-Task',
            'created_by_id' => $user->id,
        ]);
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

    public function test_project_scoped_route_emits_event_addressing_task_by_name(): void
    {
        [$user, $project, $task] = $this->ownedTask();
        $organization = $project->organization;
        $target = $organization->statusForRole(StatusRole::IN_PROGRESS);

        $organization->eventAutomations()->updateOrCreate(
            ['event' => TaskEvent::PROCESSING->value],
            ['target_status_id' => $target->id, 'overridable_status_ids' => null, 'effects' => null],
        );

        // Task per Name (nicht id) im Pfad — wie der Skill ihn kennt.
        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->name}/events", ['event' => 'PROCESSING'])
            ->assertOk()
            ->assertJsonPath('event', 'PROCESSING')
            ->assertJsonPath('status', 'IN_PROGRESS');

        $this->assertSame($target->id, $task->refresh()->status_id);
        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'event' => 'PROCESSING',
        ]);
    }

    public function test_project_scoped_route_rejects_task_from_another_project(): void
    {
        [, $project, ] = $this->ownedTask();
        // Fremdes Projekt/Task desselben Users — scopeBindings muss ihn ablehnen.
        $other = Project::factory()->create(['created_by_id' => $project->created_by_id]);
        $otherTask = $other->tasks()->create([
            'name' => 'X1',
            'summary' => 'fremd',
            'created_by_id' => $other->created_by_id,
        ]);

        $this->postJson("/api/projects/{$project->alias}/tasks/{$otherTask->name}/events", ['event' => 'CLAIMED'])
            ->assertNotFound();
    }

    public function test_project_scoped_route_forbids_non_member(): void
    {
        [, $project, $task] = $this->ownedTask();
        Sanctum::actingAs(User::factory()->create()); // a stranger

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->name}/events", ['event' => 'CLAIMED'])
            ->assertForbidden();
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
        $task = $project->tasks()->create([
            'name' => 'E9',
            'summary' => 'x',
            'created_by_id' => $project->created_by_id,
        ]);

        $this->postJson('/api/events', ['task_id' => $task->id, 'event' => 'CLAIMED'])
            ->assertUnauthorized();
    }

    /**
     * Der Fortschritt innerhalb eines Events lebte bisher nur in der lokalen
     * Sticky-Statuszeile des Workers — im Fenster sichtbar, sonst nirgends. detail +
     * progress heben ihn auf den Server: protokolliert je Event UND denormalisiert auf
     * der Aufgabe, damit das Board ihn ohne Join auf der Karte zeigt.
     */
    public function test_event_records_progress_detail_on_task_and_log(): void
    {
        [, $project, $task] = $this->ownedTask();

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->name}/events", [
            'event' => 'PROCESSING',
            'detail' => '4/9 Dateien: TaskController.php',
            'progress' => 44,
        ])->assertOk();

        $task->refresh();
        $this->assertSame('4/9 Dateien: TaskController.php', $task->progress_detail);
        $this->assertSame(44, $task->progress_percent);
        $this->assertNotNull($task->progress_at);

        // Historie: die Protokollzeile traegt denselben Stand.
        $log = TaskEventLog::where('task_id', $task->id)->latest('id')->first();
        $this->assertSame('4/9 Dateien: TaskController.php', $log->detail);
        $this->assertSame(44, $log->progress);
    }

    /**
     * Ein Event ohne detail/progress ist eine reine Meldung — es darf den zuletzt
     * gemeldeten Stand NICHT loeschen, sonst wuerde jedes Zwischen-Event (`ANALYZED`,
     * `PUBLISHING`) die Karte leerraeumen.
     */
    public function test_event_without_progress_keeps_the_previous_value(): void
    {
        [, $project, $task] = $this->ownedTask();

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->name}/events", [
            'event' => 'PROCESSING',
            'detail' => '4/9 Dateien',
            'progress' => 44,
        ])->assertOk();

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->name}/events", [
            'event' => 'PROCESSED',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('4/9 Dateien', $task->progress_detail);
        $this->assertSame(44, $task->progress_percent);
    }

    /**
     * `progress` ist eine gerechnete Prozentzahl — Werte ausserhalb 0–100 oder
     * ueberlange Detailtexte sind ein Client-Fehler, kein stillschweigend
     * abgeschnittener Wert.
     */
    public function test_progress_is_validated(): void
    {
        [, $project, $task] = $this->ownedTask();
        $url = "/api/projects/{$project->alias}/tasks/{$task->name}/events";

        $this->postJson($url, ['event' => 'PROCESSING', 'progress' => 101])->assertStatus(422);
        $this->postJson($url, ['event' => 'PROCESSING', 'progress' => -1])->assertStatus(422);
        $this->postJson($url, ['event' => 'PROCESSING', 'detail' => str_repeat('x', 201)])->assertStatus(422);

        // Und die Felder bleiben freiwillig: ohne sie funktioniert der Aufruf wie bisher.
        $this->postJson($url, ['event' => 'PROCESSING'])->assertOk();
    }

    /**
     * Auch der top-level Einstieg (`POST /api/events`, Task per numerischer id) nimmt
     * die Felder — beide Wege sind laut Betriebshandbuch wirkungsgleich.
     */
    public function test_top_level_entry_accepts_progress_too(): void
    {
        [, , $task] = $this->ownedTask();

        $this->postJson('/api/events', [
            'task_id' => $task->id,
            'event' => 'PROCESSING',
            'detail' => '2/5 Checks: phpstan',
            'progress' => 40,
        ])->assertOk();

        $task->refresh();
        $this->assertSame('2/5 Checks: phpstan', $task->progress_detail);
        $this->assertSame(40, $task->progress_percent);
    }
}
