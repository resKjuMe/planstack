<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\TaskFormPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Der Claim ist im Bearbeiten-Formular ein Auswahlfeld — bisher ging er nur über
 * Board-Klick oder API. Zwei Dinge dürfen dabei nicht auseinanderlaufen:
 *
 *  1. `claimed_at` und das Session-Lease (`claim_session_label`, `claim_seen_at`)
 *     gehören ZUM Claim. Wird er im Formular gesetzt, gewechselt oder geräumt,
 *     müssen sie mitgehen — sonst behauptet die Karte weiter eine Worker-Session,
 *     die den Task längst nicht mehr hält, oder eine Auswertung schreibt die
 *     Liegezeit des Vorgängers der neuen Person zu.
 *  2. Bleibt der Claim unberührt, darf das Speichern eines anderen Feldes weder
 *     den Zeitstempel neu setzen noch ein laufendes Worker-Lease abräumen.
 */
class TaskFormClaimTest extends TestCase
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

    /** Ein Teammitglied mit Projektzugang (accessUsers speist die Auswahl). */
    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'organization_id' => $this->organization->id]);

        $team = $this->project->teams()->first();
        if ($team === null) {
            $team = Team::factory()->create([
                'organization_id' => $this->organization->id,
                'created_by_id' => $this->owner->id,
            ]);
            $this->project->teams()->attach($team->id);
            $this->project->unsetRelation('teams');
        }
        $team->members()->attach($user->id);
        $this->project->unsetRelation('teams');

        return $user;
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'T1',
            'summary' => 'Ein Task',
            'status' => TaskStatus::IN_PROGRESS,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Task $task, array $overrides = []): array
    {
        return [
            'name' => $task->name,
            'summary' => $task->summary,
            'status' => $task->orgStatus?->key,
            ...$overrides,
        ];
    }

    private function save(Task $task, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->owner)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload($task, $overrides));
    }

    /** Die Auswahl steht im Formular und listet den Projektzugang. */
    public function test_form_offers_the_project_members_for_the_claim(): void
    {
        $worker = $this->member('Ada Lovelace');

        $shared = app(TaskFormPresenter::class)->shared($this->project);
        $ids = array_column($shared['members'], 'id');

        $this->assertContains($worker->id, $ids);
        $this->assertContains($this->owner->id, $ids, 'der Projekt-Owner hat immer Zugang');
    }

    /** Der aktuelle Wert ist vorbelegt — sonst würde Speichern ihn verwerfen. */
    public function test_form_preselects_the_current_claimer(): void
    {
        $worker = $this->member('Ada Lovelace');
        $task = $this->task(['claimed_by_id' => $worker->id]);

        $this->assertSame($worker->id, app(TaskFormPresenter::class)->values($task)['claimed_by_id']);
    }

    /**
     * Wer den Projektzugang verloren hat, aber am Task steht, bleibt in der Liste:
     * sonst zeigte das Feld leer und das Speichern würde den Claim stillschweigend
     * räumen.
     */
    public function test_form_keeps_a_claimer_without_project_access_in_the_list(): void
    {
        $stranger = User::factory()->create(['name' => 'Ohne Zugang', 'organization_id' => $this->organization->id]);
        $task = $this->task(['claimed_by_id' => $stranger->id]);

        $ids = array_column(app(TaskFormPresenter::class)->shared($this->project, $task)['members'], 'id');

        $this->assertContains($stranger->id, $ids);
    }

    /** Claim setzen stempelt den Zeitpunkt mit. */
    public function test_setting_the_claim_stamps_claimed_at(): void
    {
        $worker = $this->member('Ada Lovelace');
        $task = $this->task(['claimed_by_id' => null, 'claimed_at' => null]);

        $this->save($task, ['claimed_by_id' => $worker->id])->assertRedirect();

        $task->refresh();

        $this->assertSame($worker->id, $task->claimed_by_id);
        $this->assertNotNull($task->claimed_at);
    }

    /**
     * Claim räumen räumt Zeitstempel UND Session-Lease: ein Task ohne Bearbeiter
     * darf nicht weiter eine Worker-Session behaupten.
     */
    public function test_clearing_the_claim_also_clears_timestamp_and_session_lease(): void
    {
        $worker = $this->member('Ada Lovelace');
        $task = $this->task([
            'claimed_by_id' => $worker->id,
            'claimed_at' => now()->subDay(),
            'claim_session_label' => 'worker-7',
            'claim_seen_at' => now()->subMinutes(5),
        ]);

        $this->save($task, ['claimed_by_id' => ''])->assertRedirect();

        $task->refresh();

        $this->assertNull($task->claimed_by_id);
        $this->assertNull($task->claimed_at);
        $this->assertNull($task->claim_session_label, 'das Session-Lease muss mit dem Claim verschwinden');
        $this->assertNull($task->claim_seen_at);
    }

    /**
     * Beim WECHSEL wird neu gestempelt — sonst zählt die Auswertung die Liegezeit
     * des Vorgängers der neuen Person zu.
     */
    public function test_changing_the_claimer_restamps_claimed_at(): void
    {
        $first = $this->member('Ada Lovelace');
        $second = $this->member('Grace Hopper');
        $before = now()->subDays(3);
        $task = $this->task(['claimed_by_id' => $first->id, 'claimed_at' => $before]);

        $this->save($task, ['claimed_by_id' => $second->id])->assertRedirect();

        $task->refresh();

        $this->assertSame($second->id, $task->claimed_by_id);
        $this->assertTrue(
            $task->claimed_at->greaterThan($before),
            'der Claim beginnt für die neue Person jetzt, nicht rückwirkend',
        );
    }

    /**
     * Der Regressionsfall: ein anderes Feld speichern, während eine Worker-Session
     * den Task hält. Claim, Zeitstempel und Lease müssen unberührt bleiben.
     */
    public function test_saving_another_field_leaves_an_untouched_claim_alone(): void
    {
        $worker = $this->member('Ada Lovelace');
        $claimedAt = now()->subDays(2)->startOfSecond();
        $seenAt = now()->subMinutes(3)->startOfSecond();
        $task = $this->task([
            'claimed_by_id' => $worker->id,
            'claimed_at' => $claimedAt,
            'claim_session_label' => 'worker-7',
            'claim_seen_at' => $seenAt,
        ]);

        $this->save($task, ['claimed_by_id' => $worker->id, 'summary' => 'Neue Zusammenfassung'])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('Neue Zusammenfassung', $task->summary);
        $this->assertSame($worker->id, $task->claimed_by_id);
        $this->assertSame($claimedAt->toDateTimeString(), $task->claimed_at->toDateTimeString());
        $this->assertSame('worker-7', $task->claim_session_label, 'das laufende Worker-Lease darf nicht abgeräumt werden');
        $this->assertSame($seenAt->toDateTimeString(), $task->claim_seen_at->toDateTimeString());
    }

    /** Der Status bleibt, was im Statusfeld steht — der Claim schaltet ihn nicht. */
    public function test_setting_the_claim_does_not_change_the_status(): void
    {
        $worker = $this->member('Ada Lovelace');
        $task = $this->task(['claimed_by_id' => null]);

        $this->save($task, ['claimed_by_id' => $worker->id])->assertRedirect();

        $this->assertSame('IN_PROGRESS', $task->fresh()->orgStatus?->key);
    }
}
