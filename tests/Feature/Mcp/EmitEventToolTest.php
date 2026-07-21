<?php

namespace Tests\Feature\Mcp;

use App\Enums\StatusRole;
use App\Enums\TaskEvent;
use App\Models\Project;
use App\Models\User;
use App\Support\Mcp\McpServer;
use App\Support\Mcp\McpToolException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmitEventToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_emit_event_tool_applies_config_and_logs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $task = $project->tasks()->create(['name' => 'M1', 'summary' => 'MCP-Event']);
        $target = $project->organization->statusForRole(StatusRole::IN_PROGRESS);
        // PROCESSING ist per Default vorkonfiguriert → updateOrCreate; overridable
        // leeren, damit aus PICKABLE heraus immer überschrieben wird.
        $project->organization->eventAutomations()->updateOrCreate(
            ['event' => TaskEvent::PROCESSING->value],
            ['target_status_id' => $target->id, 'overridable_status_ids' => null],
        );
        $this->actingAs($user);

        $server = app(McpServer::class);
        $json = $server->callTool($project, $user, 'emit_event', ['task' => 'M1', 'event' => 'PROCESSING']);
        $data = json_decode($json, true);

        $this->assertSame('PROCESSING', $data['event']);
        $this->assertTrue($data['status_changed']);
        $this->assertSame($target->id, $task->refresh()->status_id);
        $this->assertDatabaseHas('task_events', ['task_id' => $task->id, 'event' => 'PROCESSING']);
    }

    public function test_emit_event_tool_resolves_task_by_id_and_logs_without_config(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $task = $project->tasks()->create(['name' => 'M2', 'summary' => 'x']);
        $before = $task->status_id;
        $this->actingAs($user);

        $server = app(McpServer::class);
        // PUBLISHED hat per Default keine Automation ⇒ reine Meldung.
        $json = $server->callTool($project, $user, 'emit_event', ['task' => (string) $task->id, 'event' => 'PUBLISHED']);
        $data = json_decode($json, true);

        $this->assertFalse($data['configured']);
        $this->assertSame($before, $task->refresh()->status_id);
        $this->assertDatabaseHas('task_events', ['task_id' => $task->id, 'event' => 'PUBLISHED']);
    }

    public function test_emit_event_tool_rejects_invalid_event(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $project->tasks()->create(['name' => 'M3', 'summary' => 'x']);
        $this->actingAs($user);

        $this->expectException(McpToolException::class);

        app(McpServer::class)->callTool($project, $user, 'emit_event', ['task' => 'M3', 'event' => 'NOPE']);
    }
}
