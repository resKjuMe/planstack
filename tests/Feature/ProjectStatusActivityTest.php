<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\StatusActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Die Heatmap der Performance-Unterseite zählt Statusupdates je Kalendertag und
 * Stunde. Das Raster zeichnet der Client (resources/js/performance/heatmap.js), die
 * Zähler kommen vom Server — und daran hängen drei Dinge, die schweigend falsch sein
 * können:
 *
 *  1. Gezählt werden PROTOKOLLIERTE Statuswechsel, nicht Tasks: fällt ein Task
 *     zurück, zählt jeder Wechsel erneut. Die Anlage ist kein Statusupdate.
 *  2. Gebucketet wird in der Zone des Betrachters. Ohne Umrechnung landet Arbeit um
 *     23 Uhr Ortszeit in der UTC-Stunde davor — und damit im falschen Kästchen und
 *     womöglich am falschen Tag.
 *  3. Das Fenster begrenzt: was älter ist, darf nicht mitkommen (der Client hat für
 *     ältere Spalten keinen Platz).
 *  4. Je Kästchen kommt der VERURSACHER mit, damit der Client auf eine Person
 *     filtern kann, ohne nachzuladen. Vorgabe bleibt die Summe über alle.
 */
class ProjectStatusActivityTest extends TestCase
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

    private function task(string $name = 'T1'): Task
    {
        return Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => $name,
            'status' => TaskStatus::CLAIMED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $days = 182, string $tz = 'UTC'): array
    {
        return app(StatusActivityPresenter::class)->payload($this->project, $days, $tz);
    }

    /** Jeder protokollierte Wechsel zählt — auch der zweite Besuch desselben Status. */
    public function test_counts_every_logged_status_change(): void
    {
        $at = now()->startOfDay()->addHours(10);
        $task = $this->task();

        $this->travelTo($at);
        $task->update(['status' => TaskStatus::IN_REVIEW]);
        $task->update(['status' => TaskStatus::IN_PROGRESS]);
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $payload = $this->payload();

        $this->assertSame(3, $payload['total']);
        $this->assertSame(
            ['review' => 2, 'work' => 1],
            collect($payload['buckets'])->groupBy('group')->map->sum('count')->all(),
            'zwei Review-Wechsel und einer zurück in die Bearbeitung — Rückläufer zählen einzeln',
        );
        $this->assertSame(
            [[$at->format('Y-m-d'), 10], [$at->format('Y-m-d'), 10]],
            collect($payload['buckets'])->map(fn ($b) => [$b['date'], $b['hour']])->all(),
            'alles in derselben Stunde, nur nach Familie getrennt',
        );
    }

    /**
     * Der Farbton der Kästchen kommt aus der Status-FAMILIE, und die leitet sich aus
     * `kind` der Org-Konfiguration ab — nicht aus einer Liste von Schlüsseln. Sonst
     * fiele jeder eigene Status einer Organisation („Polishing") aus der Einordnung.
     */
    public function test_groups_follow_the_kind_of_the_target_status(): void
    {
        $task = $this->task();

        $this->travelTo(now()->startOfDay()->addHours(11));
        $task->update(['status' => TaskStatus::IN_PROGRESS]);   // kind active  → work
        $task->update(['status' => TaskStatus::IN_REVIEW]);     // kind review  → review
        $task->update(['status' => TaskStatus::MERGED]);        // kind done    → other
        $task->update(['status' => TaskStatus::BLOCKED]);       // kind exception → other

        $this->assertSame(
            ['work' => 1, 'review' => 1, 'other' => 2],
            collect($this->payload()['buckets'])->groupBy('group')->map->sum('count')->all(),
        );
    }

    /**
     * Der Verursacher steht je Kästchen — sonst könnte der Client nicht auf eine
     * Person filtern, ohne ein zweites Raster nachzuladen. Die Filterliste nennt die
     * Namen samt Anzahl, der aktivste zuerst.
     */
    public function test_buckets_carry_the_causer_and_the_filter_list(): void
    {
        $owner = $this->owner();
        $other = User::factory()->create(['name' => 'Ada Lovelace', 'organization_id' => $this->organization->id]);
        $at = now()->startOfDay()->addHours(9);
        $task = $this->task();

        $this->travelTo($at);
        $this->actingAs($owner);
        $task->update(['status' => TaskStatus::IN_REVIEW]);
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        $this->actingAs($other);
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $payload = $this->payload();

        $this->assertSame(3, $payload['total'], 'die Summe zählt beide Personen zusammen');
        $this->assertSame(
            [$owner->id => 2, $other->id => 1],
            collect($payload['buckets'])->groupBy('actor')->map->sum('count')->all(),
        );
        $this->assertSame(
            [['id' => $owner->id, 'name' => $owner->name, 'count' => 2], ['id' => $other->id, 'name' => 'Ada Lovelace', 'count' => 1]],
            $payload['people'],
        );
    }

    /**
     * Wechsel ohne angemeldeten Nutzer (Konsole, Automationen) haben keinen
     * Verursacher. Sie zählen in der Summe mit, tauchen aber in keiner Personenzeile
     * auf — sonst behauptete die Auswahl eine Zuordnung, die es nicht gibt.
     */
    public function test_changes_without_a_causer_count_but_belong_to_nobody(): void
    {
        $task = $this->task();

        $this->travelTo(now()->startOfDay()->addHours(3));
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $payload = $this->payload();

        $this->assertSame(1, $payload['total']);
        $this->assertNull($payload['buckets'][0]['actor']);
        $this->assertSame([], $payload['people']);
    }

    /** Ein Task ohne Wechsel liefert nichts: die Anlage ist kein Statusupdate. */
    public function test_a_task_without_a_change_produces_no_bucket(): void
    {
        $this->task();

        $payload = $this->payload();

        $this->assertSame(0, $payload['total']);
        $this->assertSame([], $payload['buckets']);
    }

    /** Die Stunde ist die des Betrachters, nicht die der Datenbank. */
    public function test_buckets_use_the_requested_timezone(): void
    {
        $task = $this->task();

        // 22:30 UTC — in Tokio (+09:00, ganzjährig ohne Umstellung) ist das der
        // nächste Tag, 07:30. Eine Zone MIT Sommerzeit wäre hier vom Datum des
        // Testlaufs abhängig.
        $at = now()->startOfDay()->addHours(22)->addMinutes(30);
        $this->travelTo($at);
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $this->assertSame(
            [['date' => $at->format('Y-m-d'), 'hour' => 22, 'actor' => null, 'group' => 'review', 'count' => 1]],
            $this->payload(182, 'UTC')['buckets'],
        );
        $this->assertSame(
            [['date' => $at->copy()->addDay()->format('Y-m-d'), 'hour' => 7, 'actor' => null, 'group' => 'review', 'count' => 1]],
            $this->payload(182, 'Asia/Tokyo')['buckets'],
        );
    }

    /** Eine unsinnige Zonen-Kennung darf keinen 500er auslösen. */
    public function test_an_unknown_timezone_falls_back_to_the_app_timezone(): void
    {
        $this->assertSame(
            config('app.timezone'),
            app(StatusActivityPresenter::class)->payload($this->project, 182, 'Mars/Olympus')['timezone'],
        );
    }

    /** Was vor dem Fenster liegt, gehört nicht in die Antwort. */
    public function test_changes_before_the_window_are_left_out(): void
    {
        $start = now()->startOfDay();
        $task = $this->task();

        $this->travelTo($start->copy()->subDays(40)->addHours(9));
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $this->travelTo($start->copy()->addHours(9));
        $task->update(['status' => TaskStatus::MERGED]);

        $this->assertSame(2, $this->payload(182)['total'], 'im großen Fenster sind beide drin');

        $narrow = $this->payload(14);
        $this->assertSame(1, $narrow['total']);
        $this->assertSame($start->format('Y-m-d'), $narrow['buckets'][0]['date']);
    }

    /** Das Fenster ist begrenzt: `days` wird gestutzt. */
    public function test_window_size_is_clamped(): void
    {
        $this->assertSame(182, $this->payload()['days']);
        $this->assertSame(7, $this->payload(1)['days']);
        $this->assertSame(366, $this->payload(9000)['days']);
    }

    /** Der Endpunkt liefert dem Organisations-Owner die Zähler. */
    public function test_endpoint_serves_the_counts_to_the_org_owner(): void
    {
        $task = $this->task();
        $at = now()->startOfDay()->addHours(8);

        $this->travelTo($at);
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        Sanctum::actingAs($this->owner());

        $this->getJson("/api/projects/{$this->project->alias}/status-activity?tz=UTC")
            ->assertOk()
            ->assertJsonPath('days', 182)
            ->assertJsonPath('timezone', 'UTC')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('buckets.0.hour', 8)
            ->assertJsonPath('buckets.0.group', 'review');
    }

    /**
     * Owner-only wie die Unterseite: ein gewöhnliches Projektmitglied sieht die
     * Zahlen nicht, sonst reichte die API weiter als die Seite, die sie zeigt.
     */
    public function test_endpoint_is_denied_for_a_member_who_is_not_the_org_owner(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($member);

        $this->getJson("/api/projects/{$this->project->alias}/status-activity")
            ->assertForbidden();
    }

    /** Die Beschriftungen der Heatmap kommen mit den Performance-Strings. */
    public function test_heatmap_strings_travel_with_the_page(): void
    {
        $strings = $this->actingAs($this->owner())
            ->get(route('projects.performance', $this->project))
            ->assertOk()
            ->inertiaProps('performance.strings');

        $this->assertNotEmpty($strings['heatmapTitle']);
        $this->assertNotEmpty($strings['heatmapPersonAll']);
        foreach (['heatmapGroupWork', 'heatmapGroupReview', 'heatmapGroupOther'] as $key) {
            $this->assertNotEmpty($strings[$key], $key);
        }
        $this->assertStringContainsString(':weeks', $strings['heatmapRangeWeeks']);
        foreach ([':name', ':count'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $strings['heatmapPersonOption']);
        }

        // trans_choice-Templates wertet der Client selbst aus — ohne Trenner zeigt er
        // immer dieselbe Form.
        foreach (['heatmapTotal', 'heatmapCell'] as $key) {
            $this->assertStringContainsString('|', $strings[$key], $key);
            $this->assertStringContainsString(':count', $strings[$key], $key);
        }
        foreach ([':date', ':hour'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $strings['heatmapCell']);
        }
        $this->assertStringContainsString(':when', $strings['heatmapBusiest']);
    }
}
