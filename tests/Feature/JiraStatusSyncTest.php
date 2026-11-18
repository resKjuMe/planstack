<?php

namespace Tests\Feature;

use App\Enums\StatusRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Jira\JiraStatusSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JiraStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private const JB = 'https://jira.test';

    private function setUpProject(): array
    {
        $syncUser = User::factory()->create([
            'name' => 'Sync User', 'email' => 'sync@example.com',
            'jira_base_url' => self::JB, 'jira_email' => 'sync@example.com', 'jira_api_token' => 'secret',
        ]);
        $project = Project::factory()->create(['created_by_id' => $syncUser->id]);
        $syncUser->forceFill(['organization_id' => $project->organization_id])->save();
        $project->forceFill(['jira_sync_user_id' => $syncUser->id])->save();
        $worker = User::factory()->create(['name' => 'Torben', 'email' => 'torben@example.com', 'organization_id' => $project->organization_id]);

        return [$project->fresh(), $worker];
    }

    private function task(Project $project, StatusRole $role, array $attrs = []): Task
    {
        return $project->tasks()->create([
            'created_by_id' => $project->created_by_id,
            'name' => 'T1',
            'summary' => 'T1',
            'jira_key' => 'MN-1',
            'status_id' => $project->organization->statusForRole($role)->id,
            ...$attrs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $issue  status name/category + assignee
     * @param  array<int, array<string, mixed>>  $changelog
     */
    private function fakeJira(array $issue, array $changelog = []): void
    {
        Http::fake([
            self::JB.'/rest/api/3/search/jql' => Http::response(['issues' => [[
                'key' => 'MN-1',
                'fields' => [
                    'status' => ['name' => $issue['status'], 'statusCategory' => ['key' => $issue['category']]],
                    'assignee' => $issue['assignee'] ? ['accountId' => $issue['assignee']] : null,
                ],
            ]]]),
            self::JB.'/rest/api/3/user/search*' => fn (Request $r) => Http::response(
                str_contains($r->url(), 'torben') ? [['accountId' => 'acc-torben', 'displayName' => 'Torben', 'active' => true, 'accountType' => 'atlassian']] : []
            ),
            self::JB.'/rest/api/3/issue/MN-1/transitions' => fn (Request $r) => $r->method() === 'GET'
                ? Http::response(['transitions' => [
                    ['id' => '21', 'to' => ['name' => 'In Progress', 'statusCategory' => ['key' => 'indeterminate']]],
                    ['id' => '101', 'to' => ['name' => 'Product-Review', 'statusCategory' => ['key' => 'done']]],
                    ['id' => '31', 'to' => ['name' => 'Code Review', 'statusCategory' => ['key' => 'indeterminate']]],
                    ['id' => '41', 'to' => ['name' => 'In Review', 'statusCategory' => ['key' => 'indeterminate']]],
                    ['id' => '102', 'to' => ['name' => 'Done', 'statusCategory' => ['key' => 'done']]],
                    ['id' => '111', 'to' => ['name' => 'Closed', 'statusCategory' => ['key' => 'done']]],
                    ['id' => '121', 'to' => ['name' => 'Abgelehnt', 'statusCategory' => ['key' => 'done']]],
                ]])
                : Http::response(null, 204),
            self::JB.'/rest/api/3/issue/MN-1/changelog*' => Http::response(['total' => count($changelog), 'values' => $changelog]),
            self::JB.'/rest/api/3/issue/MN-1/assignee' => Http::response(null, 204),
            self::JB.'/rest/api/3/issue/MN-1*' => Http::response(['fields' => ['assignee' => $issue['assignee'] ? ['accountId' => $issue['assignee']] : null]]),
        ]);
    }

    public function test_first_run_pushes_planstack_state_to_jira(): void
    {
        [$project, $worker] = $this->setUpProject();
        $task = $this->task($project, StatusRole::MERGED, ['claimed_by_id' => $worker->id]);
        $this->fakeJira(['status' => 'Todo', 'category' => 'new', 'assignee' => null]);

        $result = app(JiraStatusSync::class)->sync($project);

        $this->assertSame([], $result['errors']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/transitions') && $r->method() === 'POST' && $r['transition']['id'] === '111');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/assignee') && $r['accountId'] === 'acc-torben');
        $this->assertSame(['class' => 'done', 'account' => 'acc-torben'], $task->fresh()->jira_sync_base);
    }

    public function test_change_made_only_in_jira_is_imported(): void
    {
        [$project, $worker] = $this->setUpProject();
        $task = $this->task($project, StatusRole::PICKABLE, ['jira_sync_base' => ['class' => 'todo', 'account' => null]]);
        $this->fakeJira(['status' => 'In Progress', 'category' => 'indeterminate', 'assignee' => 'acc-torben']);

        $result = app(JiraStatusSync::class)->sync($project);

        $this->assertSame([], $result['errors']);
        $task->refresh()->load('orgStatus');
        $this->assertSame('progress', JiraStatusSync::classOfTask($task));
        $this->assertSame($worker->id, $task->claimed_by_id);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/transitions'));
    }

    public function test_on_conflict_the_newer_change_wins(): void
    {
        [$project] = $this->setUpProject();
        // Planstack: todo → review; Jira: todo → done, laut Changelog jünger.
        $task = $this->task($project, StatusRole::IN_REVIEW, ['jira_sync_base' => ['class' => 'todo', 'account' => null]]);
        $this->fakeJira(
            ['status' => 'Closed', 'category' => 'done', 'assignee' => null],
            [['created' => now()->addMinute()->toIso8601String(), 'items' => [['field' => 'status']]]],
        );

        app(JiraStatusSync::class)->sync($project);

        $task->refresh()->load('orgStatus');
        $this->assertSame('done', JiraStatusSync::classOfTask($task));
    }

    public function test_on_conflict_an_older_jira_change_loses(): void
    {
        [$project] = $this->setUpProject();
        $task = $this->task($project, StatusRole::IN_REVIEW, ['jira_sync_base' => ['class' => 'todo', 'account' => null]]);
        $this->fakeJira(
            ['status' => 'Closed', 'category' => 'done', 'assignee' => null],
            [['created' => now()->subDay()->toIso8601String(), 'items' => [['field' => 'status']]]],
        );

        app(JiraStatusSync::class)->sync($project);

        $task->refresh()->load('orgStatus');
        $this->assertSame('review', JiraStatusSync::classOfTask($task));
    }

    public function test_rejected_tickets_are_left_alone(): void
    {
        [$project] = $this->setUpProject();
        $task = $this->task($project, StatusRole::PICKABLE, ['jira_sync_base' => ['class' => 'todo', 'account' => null]]);
        $this->fakeJira(['status' => 'Abgelehnt', 'category' => 'done', 'assignee' => null]);

        app(JiraStatusSync::class)->sync($project);

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/transitions'));
        $this->assertSame('todo', JiraStatusSync::classOfTask($task->refresh()->load('orgStatus')));
    }

    public function test_in_review_goes_to_jira_in_review_never_product_review(): void
    {
        [$project] = $this->setUpProject();
        $this->task($project, StatusRole::IN_REVIEW, ['jira_sync_base' => ['class' => 'progress', 'account' => null]]);
        $this->fakeJira(['status' => 'In Progress', 'category' => 'indeterminate', 'assignee' => null]);

        $result = app(JiraStatusSync::class)->sync($project);

        $this->assertSame([], $result['errors']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/transitions') && $r['transition']['id'] === '41');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/transitions') && $r['transition']['id'] === '101');
    }
}
