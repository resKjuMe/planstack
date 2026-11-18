<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\User;
use App\Support\DailyPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $organization, string $name, bool $jira = false): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->organization_id = $organization->id;
        if ($jira) {
            $user->jira_base_url = 'https://acme.atlassian.net';
            $user->jira_email = $user->email;
            $user->jira_api_token = 'token';
        }
        $user->save();

        return $user;
    }

    public function test_previous_workday_skips_the_weekend(): void
    {
        $this->assertSame('2026-11-13', DailyPresenter::previousWorkday(CarbonImmutable::parse('2026-11-16'))->toDateString());
        $this->assertSame('2026-11-13', DailyPresenter::previousWorkday(CarbonImmutable::parse('2026-11-15'))->toDateString());
        $this->assertSame('2026-11-17', DailyPresenter::previousWorkday(CarbonImmutable::parse('2026-11-18'))->toDateString());
        $this->assertSame('2026-11-16', DailyPresenter::nextWorkday(CarbonImmutable::parse('2026-11-13'))->toDateString());
    }

    public function test_without_jira_access_the_page_renders_with_a_hint(): void
    {
        $ada = $this->member(Organization::factory()->create(), 'Ada Lovelace');

        $this->actingAs($ada)
            ->get(route('daily', ['date' => '2026-11-17', 'scope' => 'team']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Daily')
                ->where('day.date', '2026-11-17')
                ->where('scope', 'team')
                ->where('data.jira.connected', false)
                ->where('data.issueChanges', []));
    }

    public function test_jira_status_changes_and_my_pull_requests_of_the_day(): void
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization, 'Ada Lovelace', jira: true);
        $grace = $this->member($organization, 'Grace Hopper');
        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00', DailyPresenter::TIMEZONE));

        foreach ([[$ada, 101], [$grace, 102]] as [$author, $number]) {
            PullRequest::create([
                'organization_id' => $organization->id,
                'repository' => 'acme/app',
                'pull_request_id' => $number,
                'author_id' => $author->id,
                'title' => "MN-1: PR {$number}",
                'state' => 'MERGED',
                'description' => "<!-- KUNDENSICHT-START -->\n- [x] Ab Deploy sichtbar\n\n**Was ändert sich?**\nExporte laufen im Hintergrund.\n<!-- KUNDENSICHT-END -->",
                'opened_at' => '2026-11-17 09:00:00',
                'merged_at' => '2026-11-17 13:00:00',
            ]);
        }

        $issue = fn (string $key, string $status, string $category, array $histories = [], bool $flagged = false) => [
            'key' => $key,
            'fields' => [
                'summary' => "Summary {$key}",
                'status' => ['name' => $status, 'statusCategory' => ['key' => $category]],
                'assignee' => ['accountId' => 'acc-ada', 'displayName' => 'Ada Lovelace'],
                'reporter' => ['accountId' => 'acc-ada'],
                'issuetype' => ['name' => 'Story'],
                'updated' => '2026-11-17T14:00:00.000+0100',
                'customfield_10016' => 3,
                'customfield_10021' => $flagged ? [['value' => 'Impediment']] : null,
            ],
            'changelog' => ['histories' => $histories],
        ];

        Http::fake([
            '*/rest/api/3/field' => Http::response([
                ['id' => 'customfield_10016', 'name' => 'Story point estimate'],
                ['id' => 'customfield_10021', 'name' => 'Flagged'],
                ['id' => 'customfield_10020', 'name' => 'Sprint', 'schema' => ['custom' => 'com.pyxis.greenhopper.jira:gh-sprint']],
            ]),
            '*/rest/api/3/user?accountId*' => Http::response(['avatarUrls' => ['48x48' => 'https://avatar.test/ada.png']]),
            '*/rest/api/3/user/search*' => Http::response([
                ['accountId' => 'acc-ada', 'active' => true, 'accountType' => 'atlassian', 'displayName' => 'Ada Lovelace'],
            ]),
            '*/rest/api/3/search/jql' => function (Request $request) use ($issue) {
                if (str_contains($request['jql'], 'statusCategory != Done')) {
                    return Http::response(['issues' => [
                        $issue('MN-2', 'Blocked', 'indeterminate'),
                        $issue('MN-3', 'In Progress', 'indeterminate', flagged: true),
                        $issue('MN-4', 'In Review', 'indeterminate'),
                        $issue('MN-5', 'In Progress', 'indeterminate'),
                    ], 'isLast' => true]);
                }

                return Http::response(['issues' => [
                    $issue('MN-1', 'Done', 'done', [[
                        'created' => '2026-11-17T14:00:00.000+0100',
                        'author' => ['accountId' => 'acc-ada'],
                        'items' => [['field' => 'status', 'fromString' => 'In Review', 'toString' => 'Done']],
                    ]]),
                    $issue('MN-9', 'Done', 'done', [[
                        'created' => '2026-11-16T14:00:00.000+0100',
                        'author' => ['accountId' => 'acc-ada'],
                        'items' => [['field' => 'status', 'fromString' => 'In Review', 'toString' => 'Done']],
                    ]]),
                ], 'isLast' => true]);
            },
        ]);

        $data = $this->actingAs($ada)->get(route('daily'))->assertOk()->inertiaProps('data');

        $this->assertSame(['MN-1'], array_column($data['issueChanges'], 'key'));
        $this->assertTrue($data['issueChanges'][0]['isDone']);
        $this->assertEqualsCanonicalizing([101, 102], array_column($data['issueChanges'][0]['prs'], 'number'));
        $this->assertSame('https://avatar.test/ada.png', $data['issueChanges'][0]['prs'][0]['avatar']);
        $this->assertSame([], $data['blockers'][0]['prs']);
        $this->assertSame(['MN-2', 'MN-3'], array_column($data['blockers'], 'key'));
        $this->assertSame(['MN-4'], array_column($data['waiting'], 'key'));
        $this->assertSame(['MN-5'], array_column($data['today'], 'key'));
        $this->assertSame([101], array_column($data['pullRequests'], 'number'));
        $this->assertSame('Exporte laufen im Hintergrund.', $data['forPo'][0]['customerView']['change']);
        $this->assertSame('Ab Deploy sichtbar', $data['forPo'][0]['customerView']['visibility']);
    }

    public function test_customer_view_is_null_without_block(): void
    {
        $this->assertNull(DailyPresenter::customerView('Nur Text'));
        $this->assertNull(DailyPresenter::customerView(null));
    }
}
