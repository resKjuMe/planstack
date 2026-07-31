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
            ['name' => 'SESS', 'summary' => 'a task', 'created_by_id' => $project->created_by_id],
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

    /**
     * Der beobachtete Fehlerfall: eine `fix`-Session zeigte in ihrer Statuszeile
     * „Fix (Fix 75 %) TXSAFE · GAP-MassRun", das Board an derselben Karte nichts.
     * Ursache: `fix` claimt nie — es arbeitet am PR einer Aufgabe, die jemand
     * ANDERES haelt —, und das Session-Label hing ausschliesslich am Claim.
     *
     * Deshalb muss JEDE Ausfuehrung sich vermerken, ohne das fremde Claim-Lease
     * anzutasten.
     */
    public function test_a_session_without_a_claim_is_still_recorded_on_the_task(): void
    {
        [$user, $project] = $this->ownedProject();
        $other = User::factory()->create();

        // Fremder Claim, ohne Session (Board-Klick eines Menschen).
        $task = $this->task($project, ['claimed_by_id' => $other->id, 'pr_number' => 8782]);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => 'fix TXSAFE/GAP-MassRun',
        ])->assertOk();

        $task->refresh();

        // Vermerkt — mit Initialen des Betreibers davor, damit mehrere Personen mit
        // gleich aufgebauten Labels unterscheidbar bleiben.
        $this->assertSame($user->initials().' fix TXSAFE/GAP-MassRun', $task->active_session_label);
        $this->assertNotNull($task->active_session_seen_at);

        // … ohne den fremden Claim oder dessen (leeres) Lease zu veraendern.
        $this->assertSame($other->id, $task->claimed_by_id);
        $this->assertNull($task->claim_session_label);
        $this->assertNull($task->claim_seen_at);
        $this->assertNotSame($user->id, $task->claimed_by_id);
    }

    public function test_the_active_session_is_refreshed_on_every_request_and_exposed(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => 'review MN/A1',
        ])->assertOk();

        $first = $task->refresh()->active_session_seen_at;

        // Eine spaetere Ausfuehrung einer ANDEREN Session uebernimmt den Vermerk —
        // er beantwortet „wer arbeitet jetzt daran", nicht „wer war mal da".
        $this->travel(2)->minutes();
        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => 'fix MN/A1',
        ])->assertOk();

        $task->refresh();
        $this->assertStringEndsWith('fix MN/A1', $task->active_session_label);
        $this->assertTrue($task->active_session_seen_at->gt($first));

        // Sichtbar wird der Vermerk erst im FOLGENDEN Read: der Stempel entsteht in
        // terminate(), also nach dem Rendern der Antwort — bewusst, damit er keine
        // Request-Latenz kostet (wie der Claim-Heartbeat).
        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}?fields=full")
            ->assertOk()
            ->assertJsonPath('data.active_session', $task->active_session_label);
    }

    /**
     * Ohne Header (Mensch im Board, L2LR/LOG) darf nichts gestempelt werden — sonst
     * behauptete jede Kartenansicht, es arbeite gerade eine Session daran.
     */
    public function test_a_request_without_the_session_header_records_nothing(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}")->assertOk();

        $this->assertNull($task->refresh()->active_session_label);
    }

    /**
     * Die Initialen kommen vom Server, nicht aus dem Header: mehrere Personen fahren
     * Worker unter gleich aufgebauten Labels (`fix TXSAFE/GAP-MassRun`) und waeren im
     * Board sonst nicht auseinanderzuhalten.
     */
    public function test_the_active_session_label_is_prefixed_with_the_operator_initials(): void
    {
        $user = User::factory()->create(['name' => 'Christian Mietze']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        Sanctum::actingAs($user);
        $task = $this->task($project);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => 'fix TXSAFE/GAP-MassRun',
        ])->assertOk();

        $this->assertSame('CM fix TXSAFE/GAP-MassRun', $task->refresh()->active_session_label);
        $this->assertSame('CM', $user->initials());
    }

    /**
     * Erster + letzter Namensteil: Namenspartikel („von der") tragen keine
     * Unterscheidungskraft, „Anna von der Wiese" soll also „AW" ergeben und nicht
     * „AVD". Einteiliger Name ⇒ ein Buchstabe.
     */
    public function test_initials_use_the_first_and_last_name_part(): void
    {
        $this->assertSame('CM', (new User(['name' => 'Christian Mietze']))->initials());
        $this->assertSame('AW', (new User(['name' => 'Anna von der Wiese']))->initials());
        $this->assertSame('C', (new User(['name' => 'Christian']))->initials());
        $this->assertSame('', (new User(['name' => '  ']))->initials());
    }

    /**
     * Gekuerzt wird am Label, nicht am Praefix — die Initialen sind der Teil, der die
     * Sessions unterscheidbar macht, und fielen beim Abschneiden von rechts zuerst weg.
     */
    public function test_a_long_active_label_keeps_the_initials_and_fits_the_column(): void
    {
        $user = User::factory()->create(['name' => 'Christian Mietze']);
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        Sanctum::actingAs($user);
        $task = $this->task($project);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->id}", [
            TrackClaimSession::HEADER => str_repeat('x', 200),
        ])->assertOk();

        $label = $task->refresh()->active_session_label;

        $this->assertSame(60, mb_strlen($label));
        $this->assertStringStartsWith('CM ', $label);
    }
}
