<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskStatusDurations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Verweildauer je Status, rekonstruiert aus dem Änderungsprotokoll. Der Task
 * selbst kennt nur seinen jetzigen Status — die Dauer steckt in den Abständen
 * zwischen den protokollierten Wechseln.
 *
 * Der zentrale Fall ist der RÜCKLÄUFER: „in Review → in Arbeit → in Review" muss
 * BEIDE Review-Aufenthalte zählen und aufsummieren, nicht nur den letzten. Genau
 * das prüft {@see test_counts_every_stay_when_a_task_returns_to_a_status}.
 *
 * Gezeigt werden nur Bearbeitungs-Status (kind active/review). Wartezeit vor der
 * Bearbeitung (pickbar), die Zeit nach der Fertigstellung (gemergt/erledigt) und
 * Ausnahmen (blockiert/problematisch) gehören nicht in eine Bearbeitungsdauer.
 */
class TaskStatusDurationsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->project = $this->makeProject();
    }

    private function makeProject(): Project
    {
        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_id' => $this->organization->created_by_id,
        ]);
    }

    private function task(TaskStatus $status, ?Project $project = null): Task
    {
        return Task::factory()->create([
            'project_id' => ($project ?? $this->project)->id,
            'status' => $status,
        ]);
    }

    /**
     * @return array{statuses: array<int, array<string, mixed>>, perTask: array<int|string, array<string, mixed>>, perProject: array<int|string, array<string, mixed>>}
     */
    private function aggregate(Task ...$tasks): array
    {
        return app(TaskStatusDurations::class)->aggregate(
            collect($tasks),
            $this->organization->statuses()->get()->keyBy('id'),
        );
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function rows(Task ...$tasks): Collection
    {
        return collect($this->aggregate(...$tasks)['statuses'])->keyBy('key');
    }

    /** Stunden als Bruchteil eines Tages — die Werte kommen in Tagen zurück. */
    private function hours(float $hours): float
    {
        return $hours / 24;
    }

    /**
     * DER Fall aus der Anforderung: zwei getrennte Review-Aufenthalte, dazwischen
     * Nacharbeit. Beide Review-Zeiten müssen aufaddiert werden.
     */
    public function test_counts_every_stay_when_a_task_returns_to_a_status(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::CLAIMED);

        $this->travelTo($start->copy()->addHour());
        $task->update(['status' => TaskStatus::IN_REVIEW]);      // beansprucht: 1 h

        $this->travelTo($start->copy()->addHours(3));
        $task->update(['status' => TaskStatus::IN_PROGRESS]);    // Review #1: 2 h

        $this->travelTo($start->copy()->addHours(6));
        $task->update(['status' => TaskStatus::IN_REVIEW]);      // in Arbeit: 3 h

        $this->travelTo($start->copy()->addHours(10));
        $task->update(['status' => TaskStatus::MERGED]);         // Review #2: 4 h

        $rows = $this->rows($task);

        $review = $rows->get('IN_REVIEW');
        $this->assertNotNull($review, 'IN_REVIEW fehlt in der Auswertung');
        $this->assertSame(2, $review['visits'], 'beide Review-Aufenthalte müssen gezählt werden');
        $this->assertSame(1, $review['tasks']);
        // 2 h + 4 h kumuliert je Task …
        $this->assertEqualsWithDelta($this->hours(6), $review['avgPerTaskDays'], $this->hours(0.02));
        $this->assertEqualsWithDelta($this->hours(6), $review['totalDays'], $this->hours(0.02));
        // … und 3 h im Schnitt je Aufenthalt.
        $this->assertEqualsWithDelta($this->hours(3), $review['avgPerVisitDays'], $this->hours(0.02));

        $this->assertEqualsWithDelta($this->hours(1), $rows->get('CLAIMED')['totalDays'], $this->hours(0.02));
        $this->assertEqualsWithDelta($this->hours(3), $rows->get('IN_PROGRESS')['totalDays'], $this->hours(0.02));
    }

    /**
     * „Aufenthalte" über „Tasks" ist das Signal für Rückläufer — die Ansicht weist
     * die Differenz aus, also muss sie stimmen.
     */
    public function test_visits_exceed_tasks_only_when_there_were_returns(): void
    {
        $start = now()->startOfSecond();
        $looping = $this->task(TaskStatus::IN_REVIEW);
        $straight = $this->task(TaskStatus::IN_REVIEW);

        $this->travelTo($start->copy()->addHour());
        $looping->update(['status' => TaskStatus::IN_PROGRESS]);
        $straight->update(['status' => TaskStatus::MERGED]);

        $this->travelTo($start->copy()->addHours(2));
        $looping->update(['status' => TaskStatus::IN_REVIEW]);

        $this->travelTo($start->copy()->addHours(3));
        $looping->update(['status' => TaskStatus::MERGED]);

        $review = $this->rows($looping, $straight)->get('IN_REVIEW');

        $this->assertSame(2, $review['tasks']);
        $this->assertSame(3, $review['visits'], 'zwei Tasks, einer davon zweimal im Review');
    }

    /**
     * Ohne protokollierten Wechsel liegt der Task seit seiner Anlage im aktuellen
     * Status — ein LAUFENDER Aufenthalt, der als solcher ausgewiesen wird.
     */
    public function test_task_without_status_changes_counts_from_creation(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::CLAIMED);

        $this->travelTo($start->copy()->addHours(5));

        $row = $this->rows($task)->get('CLAIMED');

        $this->assertSame(1, $row['visits']);
        $this->assertSame(1, $row['openVisits'], 'der Aufenthalt läuft noch');
        $this->assertEqualsWithDelta($this->hours(5), $row['totalDays'], $this->hours(0.05));
    }

    /**
     * Wartezeit (pickbar), Zeit nach der Fertigstellung (gemergt) und Ausnahmen
     * (problematisch) sind keine Bearbeitungsdauer und dürfen die Verteilung nicht
     * verwässern.
     */
    public function test_waiting_done_and_exception_statuses_are_excluded(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::PICKABLE);

        $this->travelTo($start->copy()->addHours(2));
        $task->update(['status' => TaskStatus::IN_PROGRESS]);   // pickbar: 2 h

        $this->travelTo($start->copy()->addHours(4));
        $task->update(['status' => TaskStatus::CONCERNED]);     // in Arbeit: 2 h

        $this->travelTo($start->copy()->addHours(9));
        $task->update(['status' => TaskStatus::MERGED]);        // problematisch: 5 h

        $this->travelTo($start->copy()->addHours(30));

        $rows = $this->rows($task);

        $this->assertNull($rows->get('PICKABLE'), 'Wartezeit ist keine Bearbeitungszeit');
        $this->assertNull($rows->get('CONCERNED'), 'Ausnahmen bleiben draußen');
        $this->assertNull($rows->get('MERGED'), 'die Zeit nach der Fertigstellung zählt nicht');
        $this->assertSame(['IN_PROGRESS'], $rows->keys()->all());
    }

    /** Reihenfolge = Lebenszyklus, damit die Liste als Trichter lesbar ist. */
    public function test_rows_follow_the_lifecycle_order(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::ANALYZING);

        $this->travelTo($start->copy()->addHour());
        $task->update(['status' => TaskStatus::IN_PROGRESS]);

        $this->travelTo($start->copy()->addHours(2));
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $this->travelTo($start->copy()->addHours(3));
        $task->update(['status' => TaskStatus::MERGED]);

        $this->assertSame(
            ['ANALYZING', 'IN_PROGRESS', 'IN_REVIEW'],
            $this->rows($task)->keys()->all(),
        );
    }

    /**
     * Die Aufschlüsselung je Task speist den Balken in „Zuletzt geliefert":
     * Segmente in Lebenszyklus-Reihenfolge, Summe = Gesamtdauer.
     */
    public function test_per_task_breakdown_carries_segments_and_total(): void
    {
        $start = now()->startOfSecond();
        $task = $this->task(TaskStatus::IN_PROGRESS);

        $this->travelTo($start->copy()->addHours(2));
        $task->update(['status' => TaskStatus::IN_REVIEW]);

        $this->travelTo($start->copy()->addHours(3));
        $task->update(['status' => TaskStatus::MERGED]);

        $breakdown = $this->aggregate($task)['perTask'][$task->id];

        $this->assertSame(['IN_PROGRESS', 'IN_REVIEW'], array_column($breakdown['segments'], 'key'));
        $this->assertEqualsWithDelta($this->hours(3), $breakdown['totalDays'], $this->hours(0.05));
        $this->assertEqualsWithDelta(
            $breakdown['totalDays'],
            array_sum(array_column($breakdown['segments'], 'days')),
            0.0001,
            'die Segmente müssen die Gesamtdauer ergeben — sonst passt der Balken nicht',
        );
        $this->assertNotEmpty($breakdown['segments'][0]['label'], 'Tooltip braucht das Label');
        $this->assertNotEmpty($breakdown['segments'][0]['bar'], 'Balken braucht die Farbklasse');

        // Die Balkenbreite trägt den MEDIAN der Aufenthalte, nicht die Summe:
        // 2 h und 1 h → 1,5 h.
        $this->assertEqualsWithDelta($this->hours(1.5), $breakdown['medianDays'], $this->hours(0.05));
    }

    /** Die Aufschlüsselung je Projekt bündelt alle Tasks des Projekts. */
    public function test_per_project_breakdown_sums_its_tasks(): void
    {
        $other = $this->makeProject();
        $start = now()->startOfSecond();

        $mine = $this->task(TaskStatus::IN_PROGRESS);
        $theirs = $this->task(TaskStatus::IN_PROGRESS, $other);

        $this->travelTo($start->copy()->addHours(2));
        $mine->update(['status' => TaskStatus::MERGED]);

        $this->travelTo($start->copy()->addHours(5));
        $theirs->update(['status' => TaskStatus::MERGED]);

        $perProject = $this->aggregate($mine, $theirs)['perProject'];

        $this->assertEqualsWithDelta($this->hours(2), $perProject[$this->project->id]['totalDays'], $this->hours(0.05));
        $this->assertEqualsWithDelta($this->hours(5), $perProject[$other->id]['totalDays'], $this->hours(0.05));
    }

    public function test_no_tasks_yields_no_rows(): void
    {
        $result = app(TaskStatusDurations::class)->aggregate(collect(), collect());

        $this->assertSame(['statuses' => [], 'perTask' => [], 'perProject' => []], $result);
    }
}
