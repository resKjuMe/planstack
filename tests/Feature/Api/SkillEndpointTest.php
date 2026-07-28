<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\SkillTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/skill')->assertUnauthorized();
    }

    public function test_returns_the_composed_skill_and_its_revision(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/skill')->assertOk();

        $this->assertSame(SkillTemplate::composed(), $response->json('skill_md'));
        $this->assertSame(SkillTemplate::sharedRevision(), $response->json('skill_revision'));
        $this->assertSame(SkillTemplate::planRevision(), $response->json('plan_revision'));
    }

    /**
     * The endpoint exists so a client can replace its stale SKILL.md — the text it
     * serves must therefore carry the server-maintained conventions, above all the
     * PR-title prefix (a stale copy dropped the project alias).
     */
    public function test_served_skill_carries_the_pr_title_convention(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $skill = $this->getJson('/api/skill')->assertOk()->json('skill_md');

        $this->assertStringContainsString('<PROJECT>-<TASK>: <Kurzbeschreibung>', $skill);
        $this->assertStringContainsString('Snapshot mitschreiben', $skill);
    }
}
