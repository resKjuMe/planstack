<?php

namespace Tests\Feature;

use App\Enums\StatusRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Organization}
     */
    private function owner(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['created_by_id' => $user->id]);
        $user->update(['organization_id' => $organization->id]);
        $this->actingAs($user);

        return [$user, $organization];
    }

    public function test_owner_can_view_the_event_admin_page(): void
    {
        [, $organization] = $this->owner();

        $this->get(route('organization.events.index'))
            ->assertOk()
            ->assertSee(__('events.title'));
    }

    public function test_update_persists_a_new_event_automation_and_bumps_config_version(): void
    {
        [, $organization] = $this->owner();
        $target = $organization->statusForRole(StatusRole::IN_PROGRESS);
        $pickable = $organization->statusForRole(StatusRole::PICKABLE);
        $versionBefore = $organization->status_config_version;

        $this->put(route('organization.events.update', 'PROCESSING'), [
            'target_status_id' => $target->id,
            'overridable_status_ids' => [$pickable->id],
            'effects' => [
                ['field' => 'affected_files', 'value' => '3', 'only_if_empty' => '1'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('task_event_automations', [
            'organization_id' => $organization->id,
            'event' => 'PROCESSING',
            'target_status_id' => $target->id,
        ]);

        $config = $organization->eventAutomations()->where('event', 'PROCESSING')->first();
        $this->assertSame([$pickable->id], $config->overridable_status_ids);
        $this->assertSame('affected_files', $config->effects[0]['field']);
        $this->assertTrue($config->effects[0]['only_if_empty']);

        $this->assertSame(
            $versionBefore + 1,
            $organization->refresh()->status_config_version
        );
    }

    public function test_update_is_idempotent_per_event(): void
    {
        [, $organization] = $this->owner();
        $target = $organization->statusForRole(StatusRole::IN_REVIEW);

        foreach ([1, 2] as $_) {
            $this->put(route('organization.events.update', 'PUBLISHED'), [
                'target_status_id' => $target->id,
            ])->assertRedirect();
        }

        $this->assertSame(1, $organization->eventAutomations()->where('event', 'PUBLISHED')->count());
    }

    public function test_unknown_event_yields_404(): void
    {
        $this->owner();

        $this->put(route('organization.events.update', 'BOGUS'), [])
            ->assertNotFound();
    }

    public function test_non_owner_is_forbidden(): void
    {
        [, $organization] = $this->owner();
        $stranger = User::factory()->create(['organization_id' => $organization->id]);
        $this->actingAs($stranger);

        $this->get(route('organization.events.index'))->assertForbidden();
    }
}
