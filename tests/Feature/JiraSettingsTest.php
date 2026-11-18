<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JiraSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_jira_access_is_verified_and_stored_encrypted(): void
    {
        Http::fake(['https://jira.test/rest/api/3/myself' => Http::response(['accountId' => 'x'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/jira', [
            'jira_base_url' => 'https://jira.test/',
            'jira_email' => 'me@example.com',
            'jira_api_token' => 'secret-token',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('https://jira.test', $user->jira_base_url);
        $this->assertSame('secret-token', $user->jira_api_token);
        $this->assertDatabaseMissing('users', ['id' => $user->id, 'jira_api_token' => 'secret-token']);
    }

    public function test_rejected_jira_access_is_not_stored(): void
    {
        Http::fake(['https://jira.test/rest/api/3/myself' => Http::response(null, 401)]);
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/jira', [
            'jira_base_url' => 'https://jira.test',
            'jira_email' => 'me@example.com',
            'jira_api_token' => 'wrong',
        ])->assertSessionHasErrorsIn('jira', 'jira_api_token');

        $this->assertNull($user->refresh()->jira_api_token);
    }

    public function test_enabling_the_project_sync_requires_own_jira_access(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by_id' => $user->id]);
        $user->forceFill(['organization_id' => $project->organization_id])->save();
        $payload = ['alias' => $project->alias, 'name' => $project->name, 'jira_auto_sync' => true];

        $this->actingAs($user)->patch("/projects/{$project->alias}", $payload)
            ->assertSessionHasErrors('jira_auto_sync');
        $this->assertNull($project->refresh()->jira_sync_user_id);

        $user->forceFill(['jira_base_url' => 'https://jira.test', 'jira_email' => 'me@example.com', 'jira_api_token' => 't'])->save();
        $this->actingAs($user)->patch("/projects/{$project->alias}", $payload)->assertSessionHasNoErrors();
        $this->assertSame($user->id, $project->refresh()->jira_sync_user_id);

        $this->actingAs($user)->patch("/projects/{$project->alias}", [...$payload, 'jira_auto_sync' => false]);
        $this->assertNull($project->refresh()->jira_sync_user_id);
    }
}
