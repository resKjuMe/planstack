<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskShowPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Der Session-Vermerk (`claim_session_label` + Heartbeat) verschwindet nur mit dem
 * Claim — ein hart gekillter Worker gibt aber nie frei, sein ⚠-Badge blieb also für
 * immer stehen. Die Task-Detailseite bietet dafür einen Aufräum-Knopf.
 *
 * Zwei Grenzen dürfen dabei nicht verrutschen:
 *
 *  1. Entfernt wird NUR der Vermerk. Der Claim ist eine Reservierung und bleibt
 *     bestehen, bis ihn jemand freigibt — sonst würde „Vermerk entfernen" den Task
 *     stillschweigend an den nächsten Worker verschenken.
 *  2. Eine LEBENDE Session darf ihr Lease nicht verlieren, sonst behauptet das
 *     Board „niemand arbeitet daran", während ein Worker läuft.
 */
class ForgetClaimSessionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Project $project;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->owner = $this->organization->owner;
        $this->owner->organization_id = $this->organization->id;
        $this->owner->save();

        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_id' => $this->owner->id,
        ]);
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'T1',
            'summary' => 'Ein Task',
            'status' => TaskStatus::IN_PROGRESS,
            'claimed_by_id' => $this->owner->id,
            'claimed_at' => now()->subDay(),
            ...$attributes,
        ]);
    }

    private function forget(Task $task, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->owner)
            ->delete(route('projects.tasks.claim-session.destroy', [$this->project, $task]));
    }

    /** Der Normalfall: verwaister Vermerk weg, Reservierung bleibt. */
    public function test_a_stale_session_note_can_be_removed_without_releasing_the_claim(): void
    {
        $ttl = (int) config('planstack.claim_session_ttl_minutes', 30);
        $task = $this->task([
            'claim_session_label' => 'worker-7',
            'claim_seen_at' => now()->subMinutes($ttl + 5),
        ]);

        $this->forget($task)->assertRedirect();

        $task->refresh();

        $this->assertNull($task->claim_session_label);
        $this->assertNull($task->claim_seen_at);
        $this->assertSame($this->owner->id, $task->claimed_by_id, 'der Claim ist eine Reservierung und bleibt');
        $this->assertNotNull($task->claimed_at);
    }

    /** Ein Claim ohne Heartbeat (Alt-Claim von vor dem Lease) zählt als verwaist. */
    public function test_a_note_without_any_heartbeat_counts_as_stale(): void
    {
        $task = $this->task(['claim_session_label' => 'worker-7', 'claim_seen_at' => null]);

        $this->forget($task)->assertRedirect();

        $this->assertNull($task->fresh()->claim_session_label);
    }

    /** Eine Session, die sich gerade gemeldet hat, behält ihr Lease. */
    public function test_a_live_session_keeps_its_note(): void
    {
        $seenAt = now()->subMinute()->startOfSecond();
        $task = $this->task(['claim_session_label' => 'worker-7', 'claim_seen_at' => $seenAt]);

        $this->forget($task)->assertRedirect();

        $task->refresh();

        $this->assertSame('worker-7', $task->claim_session_label);
        $this->assertSame($seenAt->toDateTimeString(), $task->claim_seen_at->toDateTimeString());
    }

    /** Ohne Projektzugang gibt es auch keinen Aufräum-Knopf. */
    public function test_a_stranger_cannot_remove_the_note(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->organization->id]);
        $task = $this->task(['claim_session_label' => 'worker-7', 'claim_seen_at' => null]);

        $this->forget($task, $stranger)->assertForbidden();

        $this->assertSame('worker-7', $task->fresh()->claim_session_label);
    }

    /** Die Detailseite liefert das Lease samt TTL — „verwaist" leitet der Client ab. */
    public function test_the_detail_page_exposes_the_session_lease(): void
    {
        $seenAt = now()->subMinutes(2);
        $task = $this->task(['claim_session_label' => 'worker-7', 'claim_seen_at' => $seenAt]);

        $this->actingAs($this->owner);
        $props = app(TaskShowPresenter::class)->props($this->project, $task);

        $this->assertSame('worker-7', $props['claimSession']['label']);
        $this->assertSame($seenAt->toIso8601String(), $props['claimSession']['seenAt']);
        $this->assertSame(
            (int) config('planstack.claim_session_ttl_minutes', 30),
            $props['claimSession']['ttlMinutes'],
        );
        $this->assertNotNull($props['claimSession']['forgetUrl']);
    }

    /** Ohne Session-Label gibt es keine Zeile — ein Menschen-Claim ist keine Session. */
    public function test_a_task_without_a_session_has_no_lease_prop(): void
    {
        $task = $this->task(['claim_session_label' => null, 'claim_seen_at' => null]);

        $this->actingAs($this->owner);

        $this->assertNull(app(TaskShowPresenter::class)->props($this->project, $task)['claimSession']);
    }

    /**
     * Die Detailseite zeigt zusaetzlich, welche Session gerade ARBEITET — auch wenn
     * sie den Task nicht haelt. `fix` claimt nie und `review` reserviert nur
     * reviewed_by; ohne diesen Prop zeigte die Seite bei laufender Bearbeitung
     * nichts an.
     */
    public function test_the_detail_page_exposes_a_working_session_without_a_claim(): void
    {
        $seenAt = now()->subMinute();
        $task = $this->task([
            // Fremder Claim ohne Session-Lease (Board-Klick eines Menschen) …
            'claim_session_label' => null,
            'claim_seen_at' => null,
            // … waehrend eine fix-Session daran arbeitet.
            'active_session_label' => 'fix TXSAFE/GAP-MassRun',
            'active_session_seen_at' => $seenAt,
        ]);

        $this->actingAs($this->owner);
        $props = app(TaskShowPresenter::class)->props($this->project, $task);

        $this->assertNull($props['claimSession'], 'kein Claim-Lease vorhanden');
        $this->assertSame('fix TXSAFE/GAP-MassRun', $props['activeSession']['label']);
        $this->assertSame($seenAt->toIso8601String(), $props['activeSession']['seenAt']);

        // Kein Aufraeum-Knopf: hier ist nichts belegt, was man freigeben muesste.
        $this->assertArrayNotHasKey('forgetUrl', $props['activeSession']);
    }

    /**
     * Ein abgelaufener Arbeits-Vermerk wird gar nicht gemeldet. Anders als beim Claim
     * bleibt nichts belegt — „vor drei Tagen mal angefasst" waere nur Rauschen, und
     * es gibt folglich auch nichts aufzuraeumen.
     */
    public function test_a_stale_working_session_is_not_reported_at_all(): void
    {
        $ttl = (int) config('planstack.claim_session_ttl_minutes', 30);
        $task = $this->task([
            'active_session_label' => 'fix TXSAFE/GAP-MassRun',
            'active_session_seen_at' => now()->subMinutes($ttl + 5),
        ]);

        $this->actingAs($this->owner);

        $this->assertNull(app(TaskShowPresenter::class)->props($this->project, $task)['activeSession']);
    }
}
