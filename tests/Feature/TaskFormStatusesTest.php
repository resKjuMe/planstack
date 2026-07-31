<?php

namespace Tests\Feature;

use App\Enums\StatusRole;
use App\Models\Organization;
use App\Models\OrgStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskFormPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Die Status-Auswahl des Task-Formulars muss aus der ORG-KONFIGURATION kommen, nicht
 * aus dem Alt-Enum App\Enums\TaskStatus. Am Enum hängen drei Fehler zusammen:
 *
 *  1. Status ohne kanonisches Enum-Gegenstück fehlen in der Liste — im
 *     Standardsatz sind das REVIEWBAR („reviewbar") und APPROVED, dazu jeder
 *     eigene Status einer Organisation.
 *  2. Ein Task, der in so einem Status liegt, hat `status === null`. Die
 *     Bearbeitenmaske zeigte deshalb „UNKNOWN" als ausgewählt — Speichern hätte
 *     ihn stillschweigend auf pickbar zurückgesetzt.
 *  3. Umbenannte Status standen unter ihrem Enum-Namen statt unter dem, den die
 *     Organisation vergeben hat.
 *
 * Zusätzlich darf der merged_at-Stempel nicht am NAMEN „MERGED" hängen, sondern an
 * der Rolle — der Schlüssel ist org-konfigurierbar.
 */
class TaskFormStatusesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_id' => $this->organization->created_by_id,
        ]);
    }

    private function owner(): User
    {
        $owner = $this->organization->owner;
        $owner->organization_id = $this->organization->id;
        $owner->save();

        return $owner;
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function statusOptions(): Collection
    {
        return collect(app(TaskFormPresenter::class)->shared($this->project)['statuses']);
    }

    private function statusKeyOf(Task $task): ?string
    {
        return $task->fresh()->orgStatus?->key;
    }

    /** DER gemeldete Fehler: „reviewbar" fehlte in der Auswahl. */
    public function test_offers_statuses_without_a_canonical_enum_equivalent(): void
    {
        $keys = $this->statusOptions()->pluck('value');

        $this->assertContains('REVIEWBAR', $keys->all(), 'reviewbar muss wählbar sein');
        $this->assertContains('APPROVED', $keys->all(), 'approved muss wählbar sein');
        $this->assertNotContains('UNKNOWN', $keys->all(), 'UNKNOWN ist ein Alt-Enum-Rest, kein echter Status');
    }

    /** Vollständig und in Board-Reihenfolge — die Liste IST die Konfiguration. */
    public function test_offers_every_configured_status_in_board_order(): void
    {
        $expected = $this->organization->statuses()->pluck('key')->all();

        $this->assertSame($expected, $this->statusOptions()->pluck('value')->all());
    }

    /** Auch ein eigener (rollenloser) Status der Organisation ist wählbar. */
    public function test_offers_a_custom_organization_status_with_its_own_label(): void
    {
        OrgStatus::create([
            'organization_id' => $this->organization->id,
            'role' => null,
            'key' => 'WAITING_FOR_CUSTOMER',
            'label' => 'wartet auf Kunde',
            'kind' => 'waiting',
            'color_token' => 'amber',
            'position' => 99,
        ]);

        $option = $this->statusOptions()->firstWhere('value', 'WAITING_FOR_CUSTOMER');

        $this->assertNotNull($option, 'eigene Status müssen in der Auswahl stehen');
        $this->assertSame('wartet auf Kunde', $option['label']);
    }

    /** Umbenannter Status → der Name der Organisation, nicht der Enum-Name. */
    public function test_uses_the_label_the_organization_gave_a_status(): void
    {
        $this->organization->statuses()->where('key', 'IN_PROGRESS')->update(['label' => 'wird gebaut']);

        $this->assertSame(
            'wird gebaut',
            $this->statusOptions()->firstWhere('value', 'IN_PROGRESS')['label'],
        );
    }

    /** Beim Anlegen ist der pickbare Status vorbelegt (es gibt kein UNKNOWN mehr). */
    public function test_default_status_for_new_tasks_is_the_pickable_one(): void
    {
        $expected = $this->organization->statuses()
            ->where('role', StatusRole::PICKABLE->value)
            ->value('key');

        $this->assertSame($expected, app(TaskFormPresenter::class)->shared($this->project)['defaultStatus']);
    }

    /**
     * Der zweite, gefährlichere Teil des Fehlers: die Maske zeigte für einen Task im
     * Review-Pool „UNKNOWN" — wer nur die Zusammenfassung ändern wollte, hätte den
     * Task beim Speichern aus dem Review geworfen.
     */
    public function test_edit_form_preselects_the_current_status_without_enum_equivalent(): void
    {
        $task = $this->task('REVIEWBAR');

        $values = app(TaskFormPresenter::class)->values($task);

        $this->assertSame('REVIEWBAR', $values['status']);
    }

    /** Speichern setzt einen Status ohne Enum-Gegenstück wirklich. */
    public function test_saving_applies_a_status_without_enum_equivalent(): void
    {
        $task = $this->task('IN_PROGRESS');

        $this->actingAs($this->owner())
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload($task, 'REVIEWBAR'))
            ->assertRedirect();

        $this->assertSame('REVIEWBAR', $this->statusKeyOf($task));
    }

    /** Ein Status einer FREMDEN Organisation ist kein gültiges Ziel. */
    public function test_rejects_a_status_of_another_organization(): void
    {
        $task = $this->task('IN_PROGRESS');

        $foreign = OrgStatus::create([
            'organization_id' => Organization::factory()->create()->id,
            'role' => null,
            'key' => 'FOREIGN_ONLY',
            'label' => 'fremd',
            'kind' => 'active',
            'color_token' => 'gray',
            'position' => 50,
        ]);

        $this->actingAs($this->owner())
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload($task, $foreign->key))
            ->assertSessionHasErrors('status');

        $this->assertSame('IN_PROGRESS', $this->statusKeyOf($task));
    }

    /**
     * merged_at hängt an der ROLLE, nicht am Schlüsselnamen: eine Organisation darf
     * ihren Merge-Status umbenennen, ohne die Lieferzeit-Stempel zu verlieren.
     */
    public function test_stamps_merged_at_by_role_even_when_the_key_was_renamed(): void
    {
        $this->organization->statuses()
            ->where('role', StatusRole::MERGED->value)
            ->update(['key' => 'FERTIG_GELIEFERT']);

        $task = $this->task('IN_PROGRESS');
        $this->assertNull($task->merged_at);

        $this->actingAs($this->owner())
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload($task, 'FERTIG_GELIEFERT'))
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('FERTIG_GELIEFERT', $task->orgStatus?->key);
        $this->assertNotNull($task->merged_at, 'der Merge-Zeitpunkt muss gestempelt werden');
    }

    /** Anlegen ohne Status → pickbar (das Modell setzt den Vorgabestatus). */
    public function test_creating_without_a_status_lands_in_the_pickable_status(): void
    {
        $this->actingAs($this->owner())
            ->post(route('projects.tasks.store', $this->project), [
                'name' => 'NEU-1',
                'summary' => 'Ohne Status angelegt',
            ])
            ->assertRedirect();

        $task = $this->project->tasks()->where('name', 'NEU-1')->firstOrFail();

        $this->assertSame(StatusRole::PICKABLE, $task->orgStatus?->role);
    }

    /**
     * Die Review-Felder gehören zu JEDEM Review-Status — ein Task im Review-Pool
     * (REVIEWBAR) ist im Review, auch ohne kanonisches IN_REVIEW.
     */
    public function test_edit_page_shows_the_review_fields_for_the_review_pool(): void
    {
        $task = $this->task('REVIEWBAR');

        $response = $this->actingAs($this->owner())
            ->get(route('projects.tasks.edit', [$this->project, $task]))
            ->assertOk();

        $this->assertTrue($response->inertiaProps('showReview'));
    }

    private function task(string $statusKey): Task
    {
        return Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'T1',
            'summary' => 'Ein Task',
            'status' => $statusKey,
        ]);
    }

    /**
     * Das Formular schickt immer alle Felder mit — Name und Zusammenfassung sind
     * Pflicht, sonst schlägt die Validierung schon vor dem Status fehl.
     *
     * @return array<string, mixed>
     */
    private function payload(Task $task, string $statusKey): array
    {
        return [
            'name' => $task->name,
            'summary' => $task->summary,
            'status' => $statusKey,
        ];
    }
}
