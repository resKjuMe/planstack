<?php

namespace Tests\Feature;

use App\Enums\ReviewRecommendation;
use App\Models\Task;
use App\Support\TaskReworkCounts;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `tasks.last_review_recommendation` hält nur das LETZTE Review: wird nach einem
 * REQUEST_CHANGES nachgearbeitet und dann freigegeben, ist die Nacharbeit im Feld
 * nicht mehr zu sehen. Die Auswertungsseiten brauchen aber genau diese Quote, und
 * sie kommt aus dem Änderungsprotokoll.
 *
 * Der wichtigste Test hier ist {@see test_count_survives_a_later_approval} — er
 * hält fest, dass ein späteres Approve den Zähler NICHT löscht. Dazu die
 * Abgrenzung gegen falsche Treffer: „REQUEST_CHANGES" kommt auch in anderen
 * Feldern vor (z. B. GitHubs pr_review_decision), darf dort aber nicht zählen.
 */
class TaskReworkCountsTest extends TestCase
{
    use RefreshDatabase;

    private function counts(Task $task): int
    {
        return app(TaskReworkCounts::class)->forTaskIds([$task->id])[$task->id] ?? 0;
    }

    public function test_counts_request_changes_from_the_audit_log(): void
    {
        $task = Task::factory()->create();

        $task->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);

        $this->assertSame(1, $this->counts($task));
    }

    /**
     * Der Kern: „Änderungen erbeten → nachgearbeitet → freigegeben" darf die
     * Nacharbeit nicht verschwinden lassen.
     */
    public function test_count_survives_a_later_approval(): void
    {
        $task = Task::factory()->create();

        $task->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);
        $task->update(['last_review_recommendation' => ReviewRecommendation::APPROVE]);

        // Der aktuelle Zustand sagt „freigegeben" …
        $this->assertSame(ReviewRecommendation::APPROVE, $task->fresh()->last_review_recommendation);
        // … der Verlauf kennt die Nacharbeit weiterhin.
        $this->assertSame(1, $this->counts($task));
    }

    public function test_counts_every_round_of_rework(): void
    {
        $task = Task::factory()->create();

        foreach ([1, 2, 3] as $round) {
            $task->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);
            $task->update(['last_review_recommendation' => ReviewRecommendation::APPROVE]);

            $this->assertSame($round, $this->counts($task), 'Runde '.$round);
        }
    }

    /**
     * GitHubs `pr_review_decision` trägt denselben Wortlaut. Der LIKE-Vorfilter
     * greift dort mit, der dekodierte Wert entscheidet — sonst zählte jeder
     * PR-Status-Sync als Nacharbeit.
     */
    public function test_ignores_request_changes_in_other_fields(): void
    {
        $task = Task::factory()->create();

        DB::table(EloquentAuditLog::forEntity(Task::class)->getTable())->insert([
            'entity_id' => $task->id,
            'action' => 'updated',
            'old_values' => json_encode([]),
            'new_values' => json_encode(['pr_review_decision' => 'REQUEST_CHANGES']),
            'created_at' => now(),
        ]);

        $this->assertSame(0, $this->counts($task));
    }

    public function test_tasks_without_rework_are_absent_from_the_result(): void
    {
        $task = Task::factory()->create();

        $this->assertSame([], app(TaskReworkCounts::class)->forTaskIds([$task->id]));
        $this->assertSame([], app(TaskReworkCounts::class)->forTaskIds([]));
    }

    /** Mehrere Tasks in EINER Abfrage — die Auswertungsseiten fragen so ab. */
    public function test_counts_are_keyed_per_task(): void
    {
        $a = Task::factory()->create();
        $b = Task::factory()->create();
        $c = Task::factory()->create();

        $a->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);
        $b->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);
        $b->update(['last_review_recommendation' => ReviewRecommendation::APPROVE]);
        $b->update(['last_review_recommendation' => ReviewRecommendation::REQUEST_CHANGES]);

        $counts = app(TaskReworkCounts::class)->forTaskIds([$a->id, $b->id, $c->id]);

        $this->assertSame(1, $counts[$a->id]);
        $this->assertSame(2, $counts[$b->id]);
        $this->assertArrayNotHasKey($c->id, $counts);
    }
}
