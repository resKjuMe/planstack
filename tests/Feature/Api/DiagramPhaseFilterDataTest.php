<?php

namespace Tests\Feature\Api;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header-chip phase filter in diagram.js needs each graph node to carry its
 * phase and each header entry to carry the phase id. This locks that contract.
 */
class DiagramPhaseFilterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagram_graph_nodes_carry_their_phase_and_header_carries_the_id(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $phase = Phase::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $user->id,
            'phase_id' => $phase->id,
            'name' => 'T1',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.diagram', $project));

        $response->assertOk();

        $graph = $response->viewData('graph');
        $this->assertNotEmpty($graph['nodes']);
        foreach ($graph['nodes'] as $node) {
            $this->assertArrayHasKey('phase', $node);
        }
        $ours = collect($graph['nodes'])->firstWhere('name', 'T1');
        $this->assertSame($phase->id, $ours['phase']);

        $header = $response->viewData('phases');
        $this->assertSame($phase->id, $header[0]['id']);
    }
}
