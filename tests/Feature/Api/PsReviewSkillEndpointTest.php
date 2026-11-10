<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\PsReviewSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PsReviewSkillEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/skill/ps-review')->assertUnauthorized();
    }

    public function test_returns_the_current_skill_files_and_version(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/skill/ps-review')->assertOk();

        $this->assertSame(PsReviewSkill::version(), $response->json('version'));
        $this->assertSame(file_get_contents(PsReviewSkill::path()), $response->json('skill_md'));
        $this->assertJson($response->json('settings_json'));
    }
}
