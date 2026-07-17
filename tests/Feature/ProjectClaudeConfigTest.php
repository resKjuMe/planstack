<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectClaudeConfigTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project} */
    private function ownedProject(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $this->actingAs($user);

        return [$user, $project];
    }

    public function test_edit_page_renders_for_the_owner(): void
    {
        [, $project] = $this->ownedProject();

        $this->get(route('projects.claude.edit', $project))
            ->assertOk()
            ->assertSee('Claude-Konfiguration')
            ->assertSee('Umfang')
            ->assertSee('Ausführungsmodell');
    }

    public function test_saving_a_profile_bumps_the_version_and_persists(): void
    {
        [, $project] = $this->ownedProject();

        $this->put(route('projects.claude.update', $project), [
            'profile' => 'economy',
            'overrides' => [],
        ])->assertRedirect(route('projects.claude.edit', $project));

        $project->refresh();
        $this->assertSame(2, $project->config_version);
        $this->assertSame('economy', $project->config['profile']);
        $this->assertSame('next_only', $project->effectiveConfig()['board.scope']);
    }

    public function test_explicit_overrides_persist_and_empty_values_are_dropped(): void
    {
        [, $project] = $this->ownedProject();

        $this->put(route('projects.claude.update', $project), [
            'profile' => '',
            'overrides' => [
                'board.scope' => 'next_only',
                'board.format' => '', // "use default" ⇒ dropped
            ],
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(['board.scope' => 'next_only'], $project->config['overrides']);
    }

    public function test_invalid_override_redirects_with_errors_and_does_not_bump(): void
    {
        [, $project] = $this->ownedProject();

        $this->put(route('projects.claude.update', $project), [
            'overrides' => ['board.scope' => 'bogus'],
        ])->assertSessionHasErrors('overrides.board.scope');

        $this->assertSame(1, $project->refresh()->config_version);
    }

    public function test_a_non_member_cannot_edit(): void
    {
        [, $project] = $this->ownedProject();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('projects.claude.edit', $project))
            ->assertForbidden();
    }
}
