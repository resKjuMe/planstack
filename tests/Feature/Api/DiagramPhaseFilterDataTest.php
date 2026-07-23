<?php

namespace Tests\Feature\Api;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Das Diagramm (resources/js/diagram/derive.js) leitet Knoten + Phasen-Kopfzeile
 * clientseitig aus dem geteilten Store ab. Dafür muss die API pro Task die
 * `phase_id` mitliefern und der Phasen-Endpunkt die Phasen-id — dieser Vertrag
 * wird hier festgehalten (früher gegen die serverseitig gebaute Graph-Struktur).
 */
class DiagramPhaseFilterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_delivers_phase_id_per_task_and_phase_list(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $phase = Phase::factory()->create(['project_id' => $project->id]);
        Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'phase_id' => $phase->id,
            'name' => 'T1',
        ]);

        Sanctum::actingAs($user);

        // Board-Read (Datenbasis aller Unterseiten): Task trägt seine phase_id.
        $tasks = $this->getJson("/api/projects/{$project->alias}?fields=full")
            ->assertOk()
            ->json('data.tasks');

        $this->assertNotEmpty($tasks);
        $ours = collect($tasks)->firstWhere('name', 'T1');
        $this->assertNotNull($ours);
        $this->assertSame($phase->id, $ours['phase_id']);

        // Phasen-Endpunkt: die Kopfzeile filtert nach dieser id.
        $phases = $this->getJson("/api/projects/{$project->alias}/phases")
            ->assertOk()
            ->json('data');

        $this->assertSame($phase->id, $phases[0]['id']);
    }
}
