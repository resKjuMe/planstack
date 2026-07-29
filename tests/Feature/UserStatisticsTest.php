<?php

namespace Tests\Feature;

use App\Enums\Criticality;
use App\Enums\ReviewRecommendation;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Die persönliche Statistik liegt unter der sprechenden URL /{slug}/stats. Zwei
 * Dinge werden hier festgenagelt:
 *
 *  1. Der Slug: jeder Nutzer bekommt automatisch einen, Gleichnamige kollidieren
 *     nicht, und beim Umbenennen wandert er mit. Ohne Slug wäre die Seite nicht
 *     erreichbar (Route-Binding über `slug`).
 *  2. Der Zugriff: die eigene Seite immer, die eines Kollegen nur der
 *     Organisations-Owner, fremde Organisationen gar nicht. Eine ratbare URL darf
 *     keine personenbezogenen Leistungsdaten preisgeben.
 */
class UserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    /** Nutzer in einer Organisation, deren Gründer er NICHT ist. */
    private function member(Organization $organization, string $name = 'Ada Lovelace'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->organization_id = $organization->id;
        $user->save();

        return $user;
    }

    private function owner(Organization $organization): User
    {
        $owner = $organization->owner;
        $owner->organization_id = $organization->id;
        $owner->save();

        return $owner;
    }

    // --- Slug -----------------------------------------------------------------

    public function test_slug_is_derived_from_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Christian Mietze']);

        $this->assertSame('christian-mietze', $user->slug);
    }

    public function test_same_name_gets_a_counter_suffix(): void
    {
        $first = User::factory()->create(['name' => 'Ada Lovelace']);
        $second = User::factory()->create(['name' => 'Ada Lovelace']);
        $third = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->assertSame('ada-lovelace', $first->slug);
        $this->assertSame('ada-lovelace-2', $second->slug);
        $this->assertSame('ada-lovelace-3', $third->slug);
    }

    public function test_renaming_moves_the_slug(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $user->update(['name' => 'Ada Byron']);

        $this->assertSame('ada-byron', $user->fresh()->slug);
    }

    /**
     * Ein Name ganz ohne slug-fähige Zeichen darf keinen leeren Schlüssel ergeben —
     * sonst wäre die Route /,/stats und der Nutzer unerreichbar.
     */
    public function test_name_without_sluggable_characters_falls_back_to_the_email(): void
    {
        $user = User::factory()->create(['name' => '???', 'email' => 'grace.hopper@example.test']);

        $this->assertSame('grace-hopper', $user->slug);
    }

    // --- Zugriff --------------------------------------------------------------

    public function test_requires_authentication(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->get(route('statistics', $user))->assertRedirect(route('login'));
    }

    public function test_url_uses_the_slug(): void
    {
        $user = User::factory()->create(['name' => 'Christian Mietze']);

        $this->assertStringEndsWith('/christian-mietze/stats', route('statistics', $user));
    }

    public function test_own_page_is_reachable(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->member($organization);

        $this->actingAs($user)
            ->get(route('statistics', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics')
                ->where('person.isSelf', true)
                ->where('person.name', 'Ada Lovelace')
                ->has('stats.kpis')
                ->has('stats.weekly')
                ->has('strings')
            );
    }

    public function test_colleague_cannot_see_another_members_page(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization, 'Ada Lovelace');
        $grace = $this->member($organization, 'Grace Hopper');

        $this->actingAs($grace)
            ->get(route('statistics', $ada))
            ->assertForbidden();
    }

    public function test_organization_owner_can_see_a_members_page(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization, 'Ada Lovelace');

        $this->actingAs($this->owner($organization))
            ->get(route('statistics', $ada))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('person.isSelf', false)
                ->where('person.name', 'Ada Lovelace')
            );
    }

    /**
     * Über Organisationsgrenzen hinweg 404, nicht 403: dass es diesen Nutzer gibt,
     * muss die Antwort nicht verraten.
     */
    public function test_other_organization_is_not_found(): void
    {
        $ada = $this->member(Organization::factory()->create(), 'Ada Lovelace');
        $stranger = $this->owner(Organization::factory()->create());

        $this->actingAs($stranger)
            ->get(route('statistics', $ada))
            ->assertNotFound();
    }

    // --- Inhalt ---------------------------------------------------------------

    /** Projekt derselben Organisation, das `$owner` erstellt hat. */
    private function project(Organization $organization, User $owner): Project
    {
        return Project::factory()->create([
            'organization_id' => $organization->id,
            'created_by_id' => $owner->id,
        ]);
    }

    /**
     * Die Auswertung darf nur Projekte einbeziehen, die die Person sehen darf —
     * ein Task in einem fremden Projekt gehört nicht in ihre Bilanz.
     */
    public function test_only_visible_projects_are_counted(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);

        $own = $this->project($organization, $ada);
        Task::factory()->claimedBy($ada)->create(['project_id' => $own->id]);

        // Fremdes Projekt derselben Organisation, in dem Ada nicht mitspielt.
        $foreign = $this->project($organization, $this->owner($organization));
        Task::factory()->claimedBy($ada)->create(['project_id' => $foreign->id]);

        $stats = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('stats');

        $this->assertSame(1, $stats['kpis']['totalTasks']);
        $this->assertSame([$own->alias], collect($stats['projects'])->pluck('alias')->all());
    }

    /**
     * Geliefert vs. offen richtet sich nach dem org-konfigurierten Status
     * (counts_as_done), nicht nach einem fest verdrahteten Statusnamen.
     */
    public function test_delivered_and_open_are_split_by_the_status_flag(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);
        $project = $this->project($organization, $ada);

        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'claimed_at' => now()->subDays(3),
            'merged_at' => now()->subDay(),
            'status' => TaskStatus::MERGED,
            'effort_story_points' => 5,
        ]);
        Task::factory()->claimedBy($ada)->create([
            'project_id' => $project->id,
            'effort_story_points' => 8,
        ]);

        $kpis = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('stats.kpis');

        $this->assertSame(1, $kpis['deliveredTasks']);
        $this->assertSame(5, $kpis['deliveredSp']);
        $this->assertSame(1, $kpis['openTasks']);
        $this->assertSame(8, $kpis['openSp']);
        // Claim vor 3 Tagen, Merge vor einem → 2 Tage Zykluszeit.
        $this->assertEqualsWithDelta(2.0, $kpis['cycleMedianDays'], 0.05);
    }

    /**
     * Das Verlaufsdiagramm hat immer die volle Fensterbreite (auch Wochen ohne
     * Lieferung), und die laufende Woche trägt den frischen Merge.
     */
    public function test_weekly_window_is_gap_free(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);
        $project = $this->project($organization, $ada);

        Task::factory()->create([
            'project_id' => $project->id,
            'claimed_by_id' => $ada->id,
            'claimed_at' => now()->subHours(5),
            'merged_at' => now(),
            'status' => TaskStatus::MERGED,
            'effort_story_points' => 3,
        ]);

        $weekly = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('stats.weekly');

        $this->assertCount(12, $weekly);
        $this->assertSame(3, collect($weekly)->sum('sp'));
        $this->assertSame(3, end($weekly)['sp'], 'Der frische Merge muss in der laufenden Woche stehen');
    }

    /**
     * Die Qualitätskennzahlen sind bis auf die Nacharbeit Momentaufnahmen. Dieser
     * Test hält beides zugleich fest: „aktuell Änderungen erbeten" fällt nach der
     * Freigabe auf 0, die Nacharbeit bleibt stehen.
     */
    public function test_quality_keeps_the_rework_count_after_an_approval(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);
        $project = $this->project($organization, $ada);

        $task = Task::factory()->claimedBy($ada)->create(['project_id' => $project->id]);
        $task->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);
        $task->update(['last_review_recommendation' => ReviewRecommendation::APPROVE]);

        $quality = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('stats.quality');

        $this->assertSame(0, $quality['requestChanges'], 'Momentaufnahme: nach der Freigabe nichts mehr offen');
        $this->assertSame(1, $quality['approved']);
        $this->assertSame(1, $quality['reworkTasks'], 'Verlauf: die Nacharbeit bleibt sichtbar');
        $this->assertSame(0, $quality['reworkMultiple']);
    }

    /**
     * Ohne PR-Status-Sync gibt es keine CI-Messung. Dann muss `null` kommen (der
     * Client zeigt „—"), nicht 0 — sonst sähe fehlende Datenbasis wie ein grünes
     * Ergebnis aus. Dasselbe für das optionale Pflegefeld `criticality`.
     */
    public function test_unmeasured_values_are_null_not_zero(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);
        $project = $this->project($organization, $ada);

        Task::factory()->claimedBy($ada)->create([
            'project_id' => $project->id,
            'criticality' => null,
            'pr_status_synced_at' => null,
        ]);

        $quality = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('stats.quality');

        $this->assertNull($quality['ciFailed']);
        $this->assertNull($quality['openThreads']);
        $this->assertSame(0, $quality['prSynced']);
        $this->assertNull($quality['critical']);
        $this->assertSame(0, $quality['criticalityKnown']);
    }

    /** Mit Sync-Zeitstempel werden die CI-Werte wieder zu echten Zahlen. */
    public function test_synced_pr_status_yields_real_numbers(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);
        $project = $this->project($organization, $ada);

        Task::factory()->claimedBy($ada)->create([
            'project_id' => $project->id,
            'pr_status_synced_at' => now(),
            'pr_ci_failed' => 2,
            'pr_unresolved_threads' => 3,
            'criticality' => Criticality::HIGH,
        ]);

        $quality = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('stats.quality');

        $this->assertSame(1, $quality['ciFailed']);
        $this->assertSame(3, $quality['openThreads']);
        $this->assertSame(1, $quality['critical']);
        $this->assertSame(1, $quality['criticalityKnown']);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function locales(): array
    {
        return [['de'], ['en']];
    }

    /**
     * Die Seite besteht fast vollständig aus Übersetzungsschlüsseln — fehlt einer,
     * rendert Laravel still den Schlüssel selbst („statistics.m_wip").
     *
     * @dataProvider locales
     */
    public function test_carries_no_unresolved_translation_keys(string $locale): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization);
        $ada->locale = $locale;
        $ada->save();

        $strings = $this->actingAs($ada)
            ->get(route('statistics', $ada))
            ->assertOk()
            ->inertiaProps('strings');

        $matched = preg_match(
            '/"(?:statistics|common)\.[a-z0-9_]+"/',
            (string) json_encode($strings, JSON_UNESCAPED_UNICODE),
            $hit
        );

        $this->assertSame(0, $matched, 'Unaufgelöster Übersetzungsschlüssel ('.$locale.'): '.($hit[0] ?? ''));
    }
}
