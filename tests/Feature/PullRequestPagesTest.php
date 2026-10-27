<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PullRequestPagesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    private function member(string $name, ?Organization $organization = null): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->organization_id = ($organization ?? $this->organization)->id;
        $user->save();

        return $user;
    }

    private function pullRequest(int $number, array $attributes = []): PullRequest
    {
        return PullRequest::create(array_merge([
            'organization_id' => $this->organization->id,
            'repository' => 'clockodo-intern/main',
            'pull_request_id' => $number,
            'title' => "PR {$number}",
            'branch' => "feature/{$number}",
            'target_branch' => 'main',
            'last_commit' => 'aaa',
        ], $attributes));
    }

    private function review(PullRequest $pullRequest, User $reviewer, array $attributes = []): void
    {
        $review = $pullRequest->reviews()->create(array_merge([
            'reviewer_id' => $reviewer->id,
            'decision' => 'APPROVE',
            'criticality' => 50,
            'ai_reviewability' => 50,
            'commit_sha' => 'aaa',
        ], $attributes));

        $review->findingItems()->create(['level' => 'HINT', 'type' => 'SUMMARY', 'title' => 'Nit', 'message' => '**fett**']);
    }

    public function test_lists_open_pull_requests_reviewed_by_me_by_default(): void
    {
        $me = $this->member('Ich Selbst');
        $other = $this->member('Jemand Anderes');

        $this->review($mine = $this->pullRequest(1), $me);
        $this->review($this->pullRequest(2, ['state' => 'MERGED']), $me);
        $this->review($this->pullRequest(3), $other);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PullRequestsIndex', false)
                ->where('filters', ['state' => 'open', 'role' => 'reviewed'])
                ->has('pullRequests', 1)
                ->where('pullRequests.0.id', $mine->id)
                ->where('pullRequests.0.review.needScore', 2500)
                ->where('pullRequests.0.review.stale', false));
    }

    public function test_filters_by_state_and_authored_role(): void
    {
        $me = $this->member('Ich Selbst');
        $other = $this->member('Jemand Anderes');

        $this->pullRequest(1, ['author_id' => $me->id, 'state' => 'MERGED']);
        $this->review($this->pullRequest(2, ['author_id' => $other->id]), $me);

        $this->actingAs($me)
            ->get(route('pull-requests.index', ['role' => 'authored', 'state' => 'all']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pullRequests', 1)
                ->where('pullRequests.0.number', 1)
                ->where('pullRequests.0.review', null));
    }

    public function test_marks_the_review_stale_after_a_new_push(): void
    {
        $me = $this->member('Ich Selbst');
        $this->review($this->pullRequest(1, ['last_commit' => 'bbb']), $me);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('pullRequests.0.review.stale', true));
    }

    public function test_detail_page_shows_reviews_with_rendered_findings(): void
    {
        $me = $this->member('Ich Selbst');
        $pullRequest = $this->pullRequest(7);
        $this->review($pullRequest, $me);

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PullRequestShow', false)
                ->where('pullRequest.number', 7)
                ->has('reviews', 1)
                ->where('reviews.0.reviewer', 'Ich Selbst')
                ->where('reviews.0.findings.0.messageHtml', "<p><strong>fett</strong></p>\n"));
    }

    public function test_detail_page_of_another_organization_is_not_found(): void
    {
        $pullRequest = $this->pullRequest(7);
        $stranger = $this->member('Eve Fremd', Organization::factory()->create());

        $this->actingAs($stranger)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertNotFound();
    }
}
