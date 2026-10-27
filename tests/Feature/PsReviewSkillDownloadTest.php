<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PsReviewSkillDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_contains_the_skill_and_a_config_with_a_fresh_token(): void
    {
        $user = User::factory()->create();
        $user->organization_id = Organization::factory()->create(['created_by_id' => $user->id])->id;
        $user->save();

        $response = $this->actingAs($user)->get(route('skill.ps-review.download'));

        $response->assertOk()->assertDownload('ps-review-skill.zip');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $this->assertStringContainsString('name: ps-review', (string) $zip->getFromName('ps-review/SKILL.md'));
        $this->assertNotFalse($zip->getFromName('ps-review/settings.json'));

        $config = json_decode((string) $zip->getFromName('ps-review/config.json'), true);
        $this->assertSame(config('planstack.skill_api_url'), $config['base_url']);
        $this->assertNotEmpty($config['token']);
        $zip->close();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_download_requires_login(): void
    {
        $this->get(route('skill.ps-review.download'))->assertRedirect(route('login'));
    }
}
