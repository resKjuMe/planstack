<?php

namespace Tests\Feature\Api;

use App\Enums\StatusRole;
use App\Enums\TaskEvent;
use App\Enums\TaskStatus;
use App\Models\OrgEventAutomation;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectConfigTest extends TestCase
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

    private function pickableTask(Project $project, string $name): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'name' => $name,
            'status' => TaskStatus::UNKNOWN,
            'claimed_by_id' => null,
            'pr_number' => null,
        ]);
    }

    public function test_board_uses_recommended_defaults(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $this->pickableTask($project, 'C2');

        $response = $this->getJson("/api/projects/{$project->alias}/board");

        $response->assertOk()
            ->assertHeader('X-Planstack-Config-Version', '1')
            ->assertJsonPath('config_version', 1)
            ->assertJsonCount(2, 'pickable')
            // Recommended default: aggregates off.
            ->assertJsonMissingPath('totals')
            ->assertJsonMissingPath('phases');

        // Recommended differs from the shipped defaults → a hint delta is sent.
        $this->assertArrayHasKey('execution.mode', $response->json('client_hints'));
    }

    public function test_config_show_exposes_catalog_and_defaults(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->getJson("/api/projects/{$project->alias}/config")
            ->assertOk()
            ->assertJsonPath('config_version', 1)
            ->assertJsonPath('profile', null)
            ->assertJsonPath('catalog.profiles', ['recommended', 'economy', 'balanced', 'rich']);

        // Effective config uses dotted keys (flat map) — assert the literal key.
        $this->assertSame('pickable', $response->json('effective')['board.scope']);

        // Plan instructions are served with their own revision (self-updating source).
        $this->assertNotEmpty($response->json('plan_instructions'));
        $this->assertNotEmpty($response->json('plan_revision'));
    }

    public function test_board_exposes_status_config_version_header_and_bumps_on_change(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $org = $project->organization;

        $this->getJson("/api/projects/{$project->alias}/board")
            ->assertOk()
            ->assertHeader('X-Planstack-Status-Config-Version', (string) $org->status_config_version);

        // A workflow change bumps the org counter ⇒ the header advances, so a
        // client watching it re-fetches GET /config (the previous behaviour never
        // signalled org-config drift on the hot path).
        $org->increment('status_config_version');

        $this->getJson("/api/projects/{$project->alias}/board")
            ->assertOk()
            ->assertHeader('X-Planstack-Status-Config-Version', (string) $org->fresh()->status_config_version);
    }

    public function test_config_exposes_per_table_config_versions(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->getJson("/api/projects/{$project->alias}/config")->assertOk();

        // Default-seeded org ⇒ statuses and the event automations that come with the
        // default lifecycle (DefaultTaskStatuses::seedEventAutomations) are populated
        // ⇒ a timestamp is present. Tables the org never touched stay null — custom
        // fields are the case that proves it, since nothing seeds them.
        $this->assertNotNull($response->json('config_versions.statuses'));
        $this->assertNotNull($response->json('config_versions.event_automations'));
        $this->assertNull($response->json('config_versions.custom_fields'));
    }

    public function test_event_status_automation_surfaces_in_config_versions_and_status_rules(): void
    {
        [, $project] = $this->ownedProject();
        $org = $project->organization;
        $target = $org->statusForRole(StatusRole::IN_PROGRESS);

        // updateOrCreate, weil die Default-Vorbelegung der Org diesen Event schon
        // verdrahtet (DefaultTaskStatuses::seedEventAutomations) — der Test prüft,
        // dass eine vorhandene Automation durchschlägt, nicht das Einfügen selbst.
        OrgEventAutomation::updateOrCreate(
            ['organization_id' => $org->id, 'event' => TaskEvent::PROCESSING],
            ['target_status_id' => $target->id],
        );

        $response = $this->getJson("/api/projects/{$project->alias}/config")->assertOk();

        // The new event automation bumps its own table timestamp …
        $this->assertNotNull($response->json('config_versions.event_automations'));

        // … and is rendered into status_rules together with the directive that
        // stops the client from clobbering it with direct status calls.
        $statusRules = $response->json('status_rules');
        $this->assertStringContainsString('Ereignis-gesteuerte Status-Zuweisung', $statusRules);
        $this->assertStringContainsString('KEINE direkten', $statusRules);
    }

    public function test_config_skill_revision_matches_header_even_with_org_status_block(): void
    {
        [, $project] = $this->ownedProject();

        // Default-seeded org ⇒ StatusRules::forOrganization renders a non-empty
        // org status block into `status_rules`. The body `skill_revision` must
        // still equal the X-Planstack-Skill-Revision header (shared file content
        // only); otherwise the client writes a baseline that reads as permanent
        // drift, because it compares the header against the stored value.
        $response = $this->getJson("/api/projects/{$project->alias}/config")->assertOk();

        $header = $response->headers->get('X-Planstack-Skill-Revision');
        $this->assertNotEmpty($header);
        $this->assertSame($header, $response->json('skill_revision'));

        // Sanity: the org block really is present in status_rules (so this is not
        // a vacuous match on an org without a status block).
        $this->assertStringContainsString('Status dieser Organisation', $response->json('status_rules'));
    }

    public function test_economy_profile_bumps_version_and_returns_terse_next_only(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $this->pickableTask($project, 'C2');

        $cfg = $this->putJson("/api/projects/{$project->alias}/config", ['profile' => 'economy'])
            ->assertOk()
            ->assertJsonPath('config_version', 2);
        $this->assertSame('next_only', $cfg->json('effective')['board.scope']);

        $board = $this->get("/api/projects/{$project->alias}/board");
        $board->assertOk()
            ->assertHeader('X-Planstack-Config-Version', '2');
        $this->assertStringContainsString('text/plain', $board->headers->get('Content-Type'));

        // next_only ⇒ exactly one task line (lines starting with a task name).
        $taskLines = collect(explode("\n", trim($board->getContent())))
            ->filter(fn ($l) => $l !== '' && ! str_starts_with($l, '#'));
        $this->assertCount(1, $taskLines);
    }

    public function test_etag_yields_304_when_board_unchanged(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        $this->putJson("/api/projects/{$project->alias}/config", ['profile' => 'economy']);

        $first = $this->get("/api/projects/{$project->alias}/board");
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        $this->get("/api/projects/{$project->alias}/board", ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_complete_bundles_pr_and_merge_in_one_call(): void
    {
        [$user, $project] = $this->ownedProject();
        $task = $this->pickableTask($project, 'C1');
        // Aus IN_PROGRESS ist MERGED nicht erreichbar (DefaultTaskStatuses::TRANSITIONS
        // fuehrt ueber REVIEWBAR/IN_REVIEW) — die Uebergangspruefung wuerde 409 liefern.
        // Fuer das Buendeln von pr_number + merge ist IN_REVIEW der legale Startpunkt.
        $task->update(['claimed_by_id' => $user->id, 'status' => TaskStatus::IN_REVIEW]);

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/complete", [
            'pr_number' => 42,
            'merge' => true,
        ])->assertOk();

        $task->refresh();
        $this->assertEquals(42, $task->pr_number);
        $this->assertSame(TaskStatus::MERGED, $task->status);
        $this->assertNotNull($task->merged_at);
    }

    public function test_claim_return_details_false_returns_minimal_ack(): void
    {
        [, $project] = $this->ownedProject();
        $task = $this->pickableTask($project, 'C1');
        $this->putJson("/api/projects/{$project->alias}/config", [
            'overrides' => ['claim.return_details' => false],
        ])->assertOk();

        $this->postJson("/api/projects/{$project->alias}/tasks/{$task->id}/claim")
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'status', 'display_status'])
            ->assertJsonMissingPath('summary')
            ->assertJsonMissingPath('gate');
    }

    public function test_drift_hints_appear_only_when_client_version_is_stale(): void
    {
        [, $project] = $this->ownedProject();
        $this->pickableTask($project, 'C1');
        // A client-hint delta on an otherwise full/JSON board.
        $this->putJson("/api/projects/{$project->alias}/config", [
            'overrides' => ['execution.mode' => 'headless'],
        ])->assertOk();

        // No known-version header ⇒ hints delta included (flat dotted key).
        $board = $this->getJson("/api/projects/{$project->alias}/board")->assertOk();
        $this->assertSame('headless', $board->json('client_hints')['execution.mode']);

        // Client already knows the current version ⇒ no hints block.
        $this->getJson("/api/projects/{$project->alias}/board", [
            'X-Planstack-Client-Config-Version' => (string) $project->fresh()->config_version,
        ])->assertOk()->assertJsonMissingPath('client_hints');
    }

    public function test_invalid_override_value_is_rejected(): void
    {
        [, $project] = $this->ownedProject();

        $this->putJson("/api/projects/{$project->alias}/config", [
            'overrides' => ['board.scope' => 'bogus'],
        ])->assertStatus(422);
    }

    /**
     * The full config payload is ~68 KB of mostly server-maintained markdown. A skill
     * that detected org drift via config_versions only needs `status_rules` back — so
     * `?parts=` must return that block and drop the rest, while still carrying every
     * version field, so one call both re-adopts the block and refreshes the baseline.
     */
    public function test_config_parts_returns_only_requested_blocks_plus_versions(): void
    {
        [, $project] = $this->ownedProject();

        $response = $this->getJson("/api/projects/{$project->alias}/config?parts=status_rules")
            ->assertOk()
            // Requested block is there …
            ->assertJsonStructure(['status_rules'])
            // … the version/drift fields always ship …
            ->assertJsonStructure([
                'config_version',
                'status_config_version',
                'config_versions',
                'skill_revision',
                'plan_revision',
            ])
            // … and everything else is gone.
            ->assertJsonMissingPath('operating_manual')
            ->assertJsonMissingPath('skill_instructions')
            ->assertJsonMissingPath('plan_instructions')
            ->assertJsonMissingPath('catalog')
            ->assertJsonMissingPath('effective')
            ->assertJsonMissingPath('client_hints');

        $this->assertNotEmpty($response->json('status_rules'));

        // The saving is the whole point: the filtered body must be a fraction of the
        // full one, otherwise the granular drift detection buys nothing.
        $full = $this->getJson("/api/projects/{$project->alias}/config")->assertOk();
        $this->assertLessThan(
            strlen((string) $full->getContent()) / 2,
            strlen((string) $response->getContent()),
        );
    }

    public function test_config_without_parts_is_unchanged(): void
    {
        [, $project] = $this->ownedProject();

        // Rein additiv: ohne `parts` liefert der Endpunkt weiterhin alles, damit
        // bestehende Clients unberuehrt bleiben.
        $this->getJson("/api/projects/{$project->alias}/config")
            ->assertOk()
            ->assertJsonStructure([
                'operating_manual',
                'status_rules',
                'skill_instructions',
                'plan_instructions',
                'effective',
                'client_hints',
                'instructions',
                'catalog',
            ]);
    }

    public function test_config_rejects_unknown_parts(): void
    {
        [, $project] = $this->ownedProject();

        $this->getJson("/api/projects/{$project->alias}/config?parts=status_rules,bogus")
            ->assertStatus(422);

        $this->getJson("/api/projects/{$project->alias}/config?parts=")
            ->assertStatus(422);
    }

    /**
     * `status_rules` carries the org status block from the database, and `instructions`
     * the project's own text — so an ETag built from the template files alone would go
     * stale silently. It must be derived from the rendered payload.
     */
    public function test_config_etag_revalidates_and_invalidates_on_org_change(): void
    {
        [, $project] = $this->ownedProject();

        $etag = $this->getJson("/api/projects/{$project->alias}/config")
            ->assertOk()
            ->headers->get('ETag');

        $this->assertNotNull($etag);

        $this->get("/api/projects/{$project->alias}/config", ['If-None-Match' => $etag])
            ->assertStatus(304);

        // Change the organisation's workflow ⇒ the same ETag must no longer match.
        $org = $project->organization;
        OrgEventAutomation::updateOrCreate(
            ['organization_id' => $org->id, 'event' => TaskEvent::ANALYZING],
            ['target_status_id' => $org->statusForRole(StatusRole::IN_PROGRESS)->id],
        );

        $this->get("/api/projects/{$project->alias}/config", ['If-None-Match' => $etag])
            ->assertOk();
    }
}
