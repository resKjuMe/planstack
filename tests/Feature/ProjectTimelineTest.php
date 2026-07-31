<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskTimelinePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Die Zeitachsen-Unterseite zeichnet je Task einen Balken über die letzten 30 Tage.
 * Baum und Balken entstehen clientseitig (resources/js/timeline/derive.js) — der
 * Server liefert zwei Dinge, und genau die werden hier festgenagelt:
 *
 *  1. Die AUFENTHALTE je Task samt Zeitpunkten. Sie stammen aus dem
 *     Änderungsprotokoll (der Task kennt nur seinen jetzigen Status), und was vor
 *     dem Fenster endete, darf nicht mitkommen — sonst zeichnet der Client Balken
 *     für Zeiträume, die die Achse nicht zeigt.
 *  2. Die Label-/TEMPLATE-Strings. Ein Template, das beim Übersetzen seinen
 *     Platzhalter verliert (`:days`, `:status`), rendert eine Zeile ohne die
 *     Angabe, um die es geht — ohne Fehler und ohne Log-Eintrag.
 */
class ProjectTimelineTest extends TestCase
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

    private function owner(?string $locale = null): User
    {
        $owner = $this->organization->owner;
        $owner->organization_id = $this->organization->id;
        if ($locale !== null) {
            $owner->locale = $locale;
        }
        $owner->save();

        return $owner;
    }

    private function task(TaskStatus $status, string $name = 'T1'): Task
    {
        return Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => $name,
            'status' => $status,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function stays(Task $task, int $days = 30): Collection
    {
        $payload = app(TaskTimelinePresenter::class)->payload($this->project, $days);

        return collect($payload['tasks'][$task->id] ?? []);
    }

    /**
     * Der Balken ist die Kette der Aufenthalte: Claim → Review → gemergt, jeder mit
     * seinen Grenzen. Der letzte läuft noch (`open`) — der Client zeichnet ihn bis
     * „jetzt" statt bis zum Rand.
     */
    public function test_stays_carry_status_keys_and_boundaries_in_order(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::CLAIMED);

        $this->travelTo($start->copy()->addDay());
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $this->travelTo($start->copy()->addDays(2));
        $task->update(['status' => TaskStatus::MERGED]);

        $stays = $this->stays($task);

        $this->assertSame(['CLAIMED', 'IN_REVIEW', 'MERGED'], $stays->pluck('key')->all());
        $this->assertFalse($stays[0]['open']);
        $this->assertTrue($stays->last()['open'], 'der aktuelle Status läuft noch');

        // Grenzen als ISO-Zeitstempel, lückenlos aneinander: das Ende eines
        // Aufenthalts ist der Beginn des nächsten.
        $this->assertSame($stays[0]['to'], $stays[1]['from']);
        $this->assertSame($stays[1]['to'], $stays[2]['from']);
        $this->assertEqualsWithDelta(
            86400,
            strtotime($stays[1]['from']) - strtotime($stays[0]['from']),
            5,
            'der Claim-Aufenthalt dauerte einen Tag',
        );
    }

    /**
     * Was vor dem Fenster endete, gehört nicht in die Antwort — es wäre ein Balken
     * ohne Platz auf der Achse. Der laufende Aufenthalt bleibt dagegen drin, auch
     * wenn er lange vor dem Fenster begann (der Client schneidet ihn zu).
     */
    public function test_stays_that_ended_before_the_window_are_left_out(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::CLAIMED);

        $this->travelTo($start->copy()->addHours(2));
        $task->update(['status' => TaskStatus::MERGED]);

        $this->travelTo($start->copy()->addDays(40));

        $stays = $this->stays($task);

        $this->assertSame(['MERGED'], $stays->pluck('key')->all());
        $this->assertTrue($stays->first()['open']);
        $this->assertLessThan(
            strtotime(app(TaskTimelinePresenter::class)->payload($this->project)['from']),
            strtotime($stays->first()['from']),
            'der laufende Aufenthalt begann vor dem Fenster und behält seinen echten Beginn',
        );
    }

    /** Ohne protokollierten Wechsel liegt der Task seit seiner Anlage im Status. */
    public function test_task_without_status_change_yields_one_open_stay(): void
    {
        $task = $this->task(TaskStatus::PICKABLE);

        $stays = $this->stays($task);

        $this->assertCount(1, $stays);
        $this->assertSame('PICKABLE', $stays->first()['key']);
        $this->assertTrue($stays->first()['open']);
    }

    /** Das Fenster ist begrenzt: `days` wird auf einen sinnvollen Bereich gestutzt. */
    public function test_window_size_is_clamped(): void
    {
        $presenter = app(TaskTimelinePresenter::class);

        $this->assertSame(30, $presenter->payload($this->project)['days']);
        $this->assertSame(7, $presenter->payload($this->project, 1)['days']);
        $this->assertSame(120, $presenter->payload($this->project, 9000)['days']);

        $payload = $presenter->payload($this->project, 30);
        $this->assertEqualsWithDelta(
            29 * 86400,
            strtotime($payload['to']) - strtotime($payload['from']),
            86400,
            'das Fenster umfasst 30 Kalendertage bis jetzt',
        );
    }

    public function test_endpoint_serves_the_window_to_project_members(): void
    {
        $task = $this->task(TaskStatus::CLAIMED);
        Sanctum::actingAs($this->owner());

        $this->getJson("/api/projects/{$this->project->alias}/timeline")
            ->assertOk()
            ->assertJsonPath('days', 30)
            ->assertJsonPath("tasks.{$task->id}.0.key", 'CLAIMED');
    }

    public function test_endpoint_is_denied_for_a_foreign_project(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$this->project->alias}/timeline")
            ->assertForbidden();
    }

    /** Die Route rendert den Workspace mit aktivem Zeitachsen-Tab. */
    public function test_page_renders_with_the_timeline_tab_active(): void
    {
        $response = $this->actingAs($this->owner())
            ->get(route('projects.timeline', $this->project))
            ->assertOk();

        $this->assertSame('timeline', $response->inertiaProps('activeTab'));
        $this->assertContains('timeline', array_column($response->inertiaProps('tabs'), 'key'));
        $this->assertNotEmpty($response->inertiaProps('timeline.strings.title'));
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function locales(): array
    {
        return [['de'], ['en']];
    }

    /**
     * Erwartete Platzhalter je Template-String — der Client interpoliert sie
     * selbst, ein verlorener Platzhalter fällt sonst nirgends auf.
     *
     * @dataProvider locales
     */
    public function test_templates_keep_their_placeholders(string $locale): void
    {
        $strings = $this->actingAs($this->owner($locale))
            ->get(route('projects.timeline', $this->project))
            ->assertOk()
            ->inertiaProps('timeline.strings');

        $this->assertStringContainsString(':days', $strings['windowDays']);
        foreach ([':status', ':from', ':to', ':duration'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $strings['barTooltip'], 'barTooltip ('.$locale.')');
        }
        foreach ([':count', ':from', ':to', ':duration'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $strings['mixedTooltip'], 'mixedTooltip ('.$locale.')');
        }

        // trans_choice-Templates wertet der Client selbst aus — ohne den Trenner
        // zeigt er immer dieselbe Form.
        foreach (['daysCount', 'hoursCount', 'minutesCount', 'dependentsCount'] as $key) {
            $this->assertStringContainsString('|', $strings[$key], $key.' ('.$locale.')');
            $this->assertStringContainsString(':count', $strings[$key], $key.' ('.$locale.')');
        }
    }

    /**
     * @dataProvider locales
     */
    public function test_carries_no_unresolved_translation_keys(string $locale): void
    {
        $strings = $this->actingAs($this->owner($locale))
            ->get(route('projects.timeline', $this->project))
            ->assertOk()
            ->inertiaProps('timeline.strings');

        $matched = preg_match(
            '/"(?:status|common|components|projects)\.[a-z0-9_]+"/',
            (string) json_encode($strings, JSON_UNESCAPED_UNICODE),
            $hit
        );

        $this->assertSame(0, $matched, 'Unaufgelöster Übersetzungsschlüssel ('.$locale.'): '.($hit[0] ?? ''));
    }
}
