<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\StatusActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dieselbe Aktivitäts-Heatmap steht auf drei Seiten, mit drei Geltungsbereichen:
 * ein Projekt („Performance"), die ganze Organisation („Aktivität") und die EIGENEN
 * Updates einer Person (persönliche Statistik).
 *
 * Was hier festgenagelt wird, sind die Grenzen dieser Bereiche — sie sind der
 * einzige Unterschied zwischen den drei Aufrufen, und eine falsch gezogene Grenze
 * zeigt entweder zu wenig (Projekt fehlt) oder personenbezogene Daten an der
 * falschen Stelle:
 *
 *  1. Organisation: ALLE Projekte, nicht nur das erste.
 *  2. Person: nur ihre eigenen Updates, und nur aus Projekten, die sie sehen darf.
 *  3. Rechte je Endpunkt wie auf der Seite, die die Karte zeigt.
 */
class StatusActivityScopesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->owner = $this->organization->owner;
        $this->owner->organization_id = $this->organization->id;
        $this->owner->save();
    }

    private function project(?User $creator = null): Project
    {
        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_id' => ($creator ?? $this->owner)->id,
        ]);
    }

    /** Ein Nutzer der Organisation mit Zugang zu genau diesem Projekt (über ein Team). */
    private function member(string $name, Project $project): User
    {
        $user = User::factory()->create(['name' => $name, 'organization_id' => $this->organization->id]);

        $team = Team::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_id' => $this->owner->id,
        ]);
        $project->teams()->attach($team->id);
        $team->members()->attach($user->id);

        return $user;
    }

    private function task(Project $project, string $name = 'T1'): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'name' => $name,
            'status' => TaskStatus::CLAIMED,
        ]);
    }

    private function presenter(): StatusActivityPresenter
    {
        return app(StatusActivityPresenter::class);
    }

    /** Die Organisations-Ansicht zählt über alle Projekte, nicht nur eines. */
    public function test_organization_scope_spans_all_projects(): void
    {
        $first = $this->project();
        $second = $this->project();

        $this->travelTo(now()->startOfDay()->addHours(9));
        $this->actingAs($this->owner);
        $this->task($first, 'A1')->update(['status' => TaskStatus::IN_REVIEW]);
        $this->task($second, 'B1')->update(['status' => TaskStatus::IN_REVIEW]);

        $this->assertSame(1, $this->presenter()->forProject($first, 182, 'UTC')['total']);
        $this->assertSame(2, $this->presenter()->forOrganization($this->organization, 182, 'UTC')['total']);
    }

    /**
     * Die persönliche Ansicht zeigt die EIGENEN Updates: „wann arbeite ich" ist die
     * Frage der Seite, nicht „wann wird an meinen Projekten gearbeitet".
     */
    public function test_user_scope_counts_only_their_own_updates(): void
    {
        $project = $this->project();
        $worker = $this->member('Ada Lovelace', $project);
        $task = $this->task($project);

        $this->travelTo(now()->startOfDay()->addHours(9));

        $this->actingAs($worker);
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $this->actingAs($this->owner);
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        $this->assertSame(2, $this->presenter()->forProject($project, 182, 'UTC')['total']);

        $mine = $this->presenter()->forUser($worker, 182, 'UTC');
        $this->assertSame(1, $mine['total']);
        $this->assertSame($worker->id, $mine['buckets'][0]['actor']);
    }

    /**
     * Und nur aus Projekten, die die Person sehen darf — sonst verriete die eigene
     * Statistik, dass es weitere Projekte gibt und wann dort gearbeitet wurde.
     */
    public function test_user_scope_ignores_projects_the_person_cannot_see(): void
    {
        $mine = $this->project();
        $foreign = $this->project();
        $worker = $this->member('Ada Lovelace', $mine);

        $this->travelTo(now()->startOfDay()->addHours(9));
        $this->actingAs($worker);
        $this->task($mine, 'A1')->update(['status' => TaskStatus::IN_REVIEW]);
        // Dasselbe Update in einem Projekt ohne Zugang (technisch möglich, fachlich
        // außerhalb ihrer Sicht).
        $this->task($foreign, 'B1')->update(['status' => TaskStatus::IN_REVIEW]);

        $this->assertSame(1, $this->presenter()->forUser($worker, 182, 'UTC')['total']);
        $this->assertSame(
            2,
            $this->presenter()->forOrganization($this->organization, 182, 'UTC')['total'],
            'beide Updates gab es — sie stehen nur nicht in der Sicht dieser Person',
        );
        $this->assertSame(
            0,
            $this->presenter()->forUser($this->owner, 182, 'UTC')['total'],
            'der Owner sieht alle Projekte, hat hier aber selbst nichts geändert',
        );
    }

    /** Der Organisations-Endpunkt ist Gründer-Sache wie die Unterseite. */
    public function test_organization_endpoint_is_owner_only(): void
    {
        $member = $this->member('Ada Lovelace', $this->project());

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/organization/status-activity?tz=UTC')->assertOk();

        Sanctum::actingAs($member);
        $this->getJson('/api/organization/status-activity?tz=UTC')->assertForbidden();
    }

    /**
     * Die persönliche Ansicht sieht man selbst; fremde nur als Organisations-Owner,
     * und über Organisationsgrenzen gar nicht (404 statt 403 — die Existenz des
     * Nutzers ist dort nichts, was die Antwort verraten müsste).
     */
    public function test_user_endpoint_follows_the_rules_of_the_statistics_page(): void
    {
        $worker = $this->member('Ada Lovelace', $this->project());
        $colleague = $this->member('Grace Hopper', $this->project());
        $stranger = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        Sanctum::actingAs($worker);
        $this->getJson("/api/users/{$worker->slug}/status-activity")->assertOk();
        $this->getJson("/api/users/{$colleague->slug}/status-activity")->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/users/{$worker->slug}/status-activity")->assertOk();

        Sanctum::actingAs($stranger);
        $this->getJson("/api/users/{$worker->slug}/status-activity")->assertNotFound();
    }

    /** Die Organisations-Unterseite rendert samt Reiter und Endpunkt-URL. */
    public function test_organization_activity_page_renders_for_the_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('organization.activity'))
            ->assertOk();

        $this->assertNotEmpty($response->inertiaProps('strings.title'));
        $this->assertNotEmpty($response->inertiaProps('strings.heatmapTitle'));
        $this->assertStringContainsString(
            '/api/organization/status-activity',
            $response->inertiaProps('urls.activity'),
        );

        $tabs = collect($response->inertiaProps('tabs'))->keyBy('key');
        $this->assertTrue($tabs->has('activity'), 'die Unterseite braucht ihren Reiter');
        $this->assertTrue($tabs['activity']['active']);
    }

    /** Für ein gewöhnliches Mitglied gibt es die Seite nicht. */
    public function test_organization_activity_page_is_denied_for_a_member(): void
    {
        $member = $this->member('Ada Lovelace', $this->project());

        $this->actingAs($member)->get(route('organization.activity'))->assertForbidden();
    }

    /** Die persönliche Statistik schickt die Endpunkt-URL und die Labels mit. */
    public function test_statistics_page_carries_the_heatmap_url_and_strings(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('statistics', $this->owner))
            ->assertOk();

        $this->assertStringContainsString(
            "/api/users/{$this->owner->slug}/status-activity",
            $response->inertiaProps('urls.activity'),
        );
        $this->assertNotEmpty($response->inertiaProps('strings.heatmapTitle'));
    }
}
