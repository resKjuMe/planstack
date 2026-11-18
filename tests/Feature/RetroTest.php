<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetroTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $jira): User
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $user->organization_id = Organization::factory()->create()->id;
        if ($jira) {
            $user->jira_base_url = 'https://acme.atlassian.net';
            $user->jira_email = $user->email;
            $user->jira_api_token = 'token';
        }
        $user->save();

        return $user;
    }

    public function test_without_jira_the_weeks_period_applies(): void
    {
        $this->actingAs($this->member(jira: false))
            ->get(route('retro', ['weeks' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Retro')
                ->where('period.weeks', 1)
                ->where('data.jira.connected', false)
                ->where('data.sprint', null));
    }

    public function test_last_closed_sprint_is_rated_from_its_tickets(): void
    {
        $sprint = ['id' => 7, 'name' => 'Sprint 7', 'state' => 'closed', 'goal' => 'Export fertig', 'boardId' => 3,
            'startDate' => '2026-11-02T09:00:00.000Z', 'endDate' => '2026-11-14T17:00:00.000Z', 'completeDate' => '2026-11-14T17:00:00.000Z'];
        $issue = fn (string $key, string $category, ?string $resolved, array $items = []) => [
            'key' => $key,
            'fields' => [
                'summary' => "Summary {$key}",
                'status' => ['name' => $category === 'done' ? 'Done' : 'In Progress', 'statusCategory' => ['key' => $category]],
                'assignee' => ['accountId' => 'acc-ada', 'displayName' => 'Ada Lovelace'],
                'reporter' => ['accountId' => 'acc-ada'],
                'issuetype' => ['name' => 'Story'],
                'updated' => '2026-11-10T10:00:00.000Z',
                'resolutiondate' => $resolved,
                'customfield_10016' => 2,
                'customfield_10020' => [$sprint],
            ],
            'changelog' => ['histories' => array_map(fn (array $item) => [
                'created' => $item[0],
                'author' => ['accountId' => 'acc-ada'],
                'items' => [['field' => 'status', 'fromString' => $item[1], 'toString' => $item[2]]],
            ], $items)],
        ];

        Http::fake([
            '*/rest/api/3/field' => Http::response([
                ['id' => 'customfield_10016', 'name' => 'Story Points'],
                ['id' => 'customfield_10020', 'name' => 'Sprint', 'schema' => ['custom' => 'com.pyxis.greenhopper.jira:gh-sprint']],
            ]),
            '*/rest/api/3/user/search*' => Http::response([
                ['accountId' => 'acc-ada', 'active' => true, 'accountType' => 'atlassian', 'displayName' => 'Ada Lovelace'],
            ]),
            '*/rest/agile/1.0/board/3/sprint*' => Http::response(['values' => [$sprint], 'isLast' => true]),
            '*/rest/api/3/search/jql' => function (Request $request) use ($issue) {
                if (str_contains($request['jql'], 'closedSprints()')) {
                    return Http::response(['issues' => [$issue('MN-1', 'done', '2026-11-05T10:00:00.000Z')], 'isLast' => true]);
                }

                return Http::response(['issues' => [
                    $issue('MN-1', 'done', '2026-11-05T10:00:00.000Z', [
                        ['2026-11-04T10:00:00.000Z', 'To Do', 'In Progress'],
                        ['2026-11-05T10:00:00.000Z', 'In Progress', 'Done'],
                    ]),
                    $issue('MN-2', 'indeterminate', null, [
                        ['2026-11-06T10:00:00.000Z', 'In Review', 'In Progress'],
                    ]),
                ], 'isLast' => true]);
            },
        ]);

        $data = $this->actingAs($this->member(jira: true))->get(route('retro'))->assertOk()->inertiaProps('data');

        $this->assertSame(7, $data['sprint']['id']);
        $this->assertSame('Export fertig', $data['sprint']['goal']);
        $this->assertSame(2, $data['stats']['committed']);
        $this->assertSame(1, $data['stats']['done']);
        $this->assertSame(1, $data['stats']['reopened']);
        $this->assertSame(50, $data['rating']['components']['delivery']);
        $this->assertContains('fast_issues', array_column($data['categories']['excellent'], 'key'));
        $this->assertContains('carry_over', array_column($data['categories']['terrible'], 'key'));
        $this->assertContains('definition_of_done', array_column($data['improve'], 'key'));
    }
}
