<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Task;
use App\Models\User;
use App\Support\DailyPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $organization, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->organization_id = $organization->id;
        $user->save();

        return $user;
    }

    /**
     * @return array{0: Organization, 1: User, 2: User, 3: Project}
     */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        $ada = $this->member($organization, 'Ada Lovelace');
        $grace = $this->member($organization, 'Grace Hopper');
        $project = Project::factory()->create([
            'organization_id' => $organization->id,
            'created_by_id' => $ada->id,
        ]);

        return [$organization, $ada, $grace, $project];
    }

    public function test_previous_workday_skips_the_weekend(): void
    {
        $this->assertSame('2026-11-13', DailyPresenter::previousWorkday(CarbonImmutable::parse('2026-11-16'))->toDateString());
        $this->assertSame('2026-11-13', DailyPresenter::previousWorkday(CarbonImmutable::parse('2026-11-15'))->toDateString());
        $this->assertSame('2026-11-17', DailyPresenter::previousWorkday(CarbonImmutable::parse('2026-11-18'))->toDateString());
        $this->assertSame('2026-11-16', DailyPresenter::nextWorkday(CarbonImmutable::parse('2026-11-13'))->toDateString());
    }

    public function test_page_renders_with_the_requested_day_and_scope(): void
    {
        [, $ada] = $this->scenario();

        $this->actingAs($ada)
            ->get(route('daily', ['date' => '2026-11-17', 'scope' => 'team']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Daily')
                ->where('day.date', '2026-11-17')
                ->where('scope', 'team')
                ->where('data.hasProjects', true));
    }

    public function test_me_scope_lists_only_my_status_changes_and_pull_requests(): void
    {
        [$organization, $ada, $grace, $project] = $this->scenario();
        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', DailyPresenter::TIMEZONE));

        $mine = Task::factory()->create(['project_id' => $project->id, 'name' => 'MINE', 'claimed_by_id' => $ada->id, 'status' => TaskStatus::CLAIMED]);
        $theirs = Task::factory()->create(['project_id' => $project->id, 'name' => 'THEIRS', 'created_by_id' => $grace->id, 'claimed_by_id' => $grace->id, 'status' => TaskStatus::CLAIMED]);

        $this->travelTo(CarbonImmutable::parse('2026-11-17 14:00', DailyPresenter::TIMEZONE));
        $this->actingAs($ada);
        $mine->update(['status' => TaskStatus::IN_PROGRESS]);
        $this->actingAs($grace);
        $theirs->update(['status' => TaskStatus::IN_PROGRESS]);

        foreach ([[$ada, 101], [$grace, 102]] as [$author, $number]) {
            PullRequest::create([
                'organization_id' => $organization->id,
                'repository' => 'acme/app',
                'pull_request_id' => $number,
                'author_id' => $author->id,
                'title' => "PR {$number}",
                'state' => 'MERGED',
                'description' => "<!-- KUNDENSICHT-START -->\n- [x] Ab Deploy sichtbar\n\n**Was ändert sich?**\nExporte laufen im Hintergrund.\n<!-- KUNDENSICHT-END -->",
                'opened_at' => now()->subHours(2),
                'merged_at' => now(),
            ]);
        }

        $this->travelTo(CarbonImmutable::parse('2026-11-18 08:00', DailyPresenter::TIMEZONE));

        $data = $this->actingAs($ada)->get(route('daily'))->assertOk()->inertiaProps('data');

        $this->assertSame(['MINE'], array_column($data['taskChanges'], 'name'));
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
