<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Http\Middleware\AttachCommandInstructions as Attach;
use App\Http\Middleware\TrackClaimSession;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\SkillTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Die Anleitungen fuer `review`/`fix`/`auto` stehen nicht in der ausgelieferten
 * SKILL.md, sondern kommen mit der Antwort des Aufrufs, ohne den das Kommando nicht
 * stattfinden kann. Das ist der Kern der Ersparnis (ein Lauf zahlt nur, was er
 * ausfuehrt) UND der Verlaesslichkeit (dieser Aufruf laesst sich nicht ueberspringen,
 * eine nachzulesende Datei schon).
 *
 * Entsprechend pruefen diese Tests beide Richtungen: dass die Anleitung ankommt, und
 * dass sie NICHT ankommt, wo sie nur Tokens kosten wuerde — vor allem bei den
 * projektgebundenen Skills (L2LR/LOG), die dieselben Endpunkte ohne Session-Header
 * aufrufen.
 */
class CommandInstructionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project} */
    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        Sanctum::actingAs($user);

        return [$user, $project];
    }

    private function task(Project $project, string $name, array $attrs = []): Task
    {
        return $project->tasks()->create([
            'created_by_id' => $project->created_by_id,
            'name' => $name,
            'summary' => $name,
            ...$attrs,
        ]);
    }

    /** @return array<string, string> */
    private function sessionHeader(string $value): array
    {
        return [TrackClaimSession::HEADER => $value];
    }

    public function test_review_session_gets_the_review_instructions_on_review_next(): void
    {
        [, $project] = $this->ownedProject();
        $reviewable = $project->organization->statusForRole(StatusRole::IN_REVIEW);
        $this->task($project, 'REVVY', ['pr_number' => 7, 'status_id' => $reviewable->id]);

        $response = $this->postJson(
            "/api/projects/{$project->alias}/review-next",
            [],
            $this->sessionHeader('review '.$project->alias.'/REVVY'),
        )->assertOk();

        $instructions = $response->json(Attach::FIELD);

        $this->assertNotEmpty($instructions);
        $this->assertStringContainsString('/planstack review', $instructions);
        // Die Review-Prozedur muss vollstaendig ankommen — vor allem der Grundsatz,
        // vor dem Erfassen nichts ohne Bestaetigung zu schreiben.
        $this->assertStringContainsString('nie nach der Entscheidung fragen', $instructions);

        // Und NICHT die Anleitungen der anderen Kommandos (das waere der Prolog zurueck).
        $this->assertStringNotContainsString('Supervisor-Schleife', $instructions);
    }

    public function test_auto_session_gets_the_auto_instructions_on_next_action(): void
    {
        [, $project] = $this->ownedProject();
        $this->task($project, 'WORKY');

        $response = $this->postJson(
            "/api/projects/{$project->alias}/next-action",
            [],
            $this->sessionHeader('auto '.$project->alias),
        )->assertOk();

        $instructions = $response->json(Attach::FIELD);

        $this->assertNotEmpty($instructions);
        $this->assertStringContainsString('Supervisor-Schleife', $instructions);
        // Der Auto-Run darf die Priorität nicht selbst bauen — die Regel muss mitkommen.
        $this->assertStringContainsString('next-action', $instructions);
    }

    public function test_fix_session_gets_the_fix_instructions_on_the_task_read(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project, 'FIXY', ['pr_number' => 9]);

        $response = $this->getJson(
            "/api/projects/{$project->alias}/tasks/{$task->name}",
            $this->sessionHeader('fix '.$project->alias.'/FIXY'),
        )->assertOk();

        $instructions = $response->json(Attach::FIELD);

        $this->assertNotEmpty($instructions);
        $this->assertStringContainsString('resolveReviewThread', $instructions);
    }

    /**
     * Der entscheidende Nicht-Regressions-Test: L2LR und LOG rufen dieselben
     * Endpunkte auf, senden aber keinen Session-Header. Wuerde die Anleitung
     * trotzdem angehaengt, bekaeme jeder ihrer Aufrufe mehrere Kilobyte Text, den
     * sie nie brauchen.
     */
    public function test_a_call_without_the_session_header_gets_nothing_extra(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project, 'PLAIN', ['pr_number' => 3]);

        $this->getJson("/api/projects/{$project->alias}/tasks/{$task->name}")
            ->assertOk()
            ->assertJsonMissingPath(Attach::FIELD);

        $this->postJson("/api/projects/{$project->alias}/next-action")
            ->assertOk()
            ->assertJsonMissingPath(Attach::FIELD);
    }

    /**
     * `work` und `settings` sind vollstaendig im Bootstrap beschrieben — fuer sie gibt
     * es nichts anzuhaengen, auch wenn der Header gesetzt ist.
     */
    public function test_a_work_session_gets_nothing_extra(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->task($project, 'WORKY');

        $this->getJson(
            "/api/projects/{$project->alias}/tasks/{$task->name}",
            $this->sessionHeader('work '.$project->alias.'/WORKY'),
        )->assertOk()->assertJsonMissingPath(Attach::FIELD);
    }

    /**
     * Der Rueckfallweg muss tragen: liefert die Antwort keine Anleitung (aelterer
     * Server, Header nicht gesetzt), holt der Skill sie ueber
     * `?parts=skill_instructions` — dort muessen ALLE Kommando-Anleitungen stecken.
     */
    public function test_config_fallback_carries_every_command_instruction(): void
    {
        [, $project] = $this->ownedProject();

        $instructions = $this->getJson(
            "/api/projects/{$project->alias}/config?parts=skill_instructions"
        )->assertOk()->json('skill_instructions');

        foreach (SkillTemplate::COMMANDS as $command) {
            $partial = trim(SkillTemplate::commandInstructions($command));
            $this->assertNotEmpty($partial, "Anleitung fuer {$command} fehlt");
            $this->assertStringContainsString($partial, (string) $instructions);
        }
    }

    /**
     * Die ausgelieferte SKILL.md darf die verlagerten Abschnitte nicht mehr enthalten
     * — sonst zaehlen sie weiter zum Prolog jedes Aufrufs und die Verlagerung waere
     * wirkungslos. Sie muss aber erklaeren, woher sie kommen.
     */
    public function test_the_served_skill_no_longer_carries_the_command_sections(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $skill = $this->getJson('/api/skill')->assertOk()->json('skill_md');

        $this->assertStringNotContainsString('## Review (`/planstack review', $skill);
        $this->assertStringNotContainsString('## Fix (`/planstack fix', $skill);
        $this->assertStringNotContainsString('## Auto-Modus (`/planstack auto', $skill);

        // Stattdessen der Verweis auf den Laufzeit-Weg und den Rueckfallweg.
        $this->assertStringContainsString(Attach::FIELD, $skill);
        $this->assertStringContainsString('?parts=skill_instructions', $skill);
    }
}
