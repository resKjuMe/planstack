<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'review_started_at' => now()->subMinutes(10),
            'review_finished_at' => now(),
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

    public function test_a_running_review_is_shown_next_to_the_last_finished_one(): void
    {
        $me = $this->member('Ich Selbst');
        $pullRequest = $this->pullRequest(1);
        $this->review($pullRequest, $me, ['criticality' => 80, 'po_review_likelihood' => 90]);
        $pullRequest->reviews()->create(['reviewer_id' => $me->id, 'review_started_at' => now()]);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pullRequests.0.review.criticality', 80)
                ->where('pullRequests.0.review.poReviewLikelihood', 90)
                ->where('pullRequests.0.review.status', 'finished')
                ->where('pullRequests.0.running.reviewer', 'Ich Selbst'));
    }

    public function test_a_first_review_in_progress_lists_the_pull_request_without_rating(): void
    {
        $me = $this->member('Ich Selbst');
        $this->pullRequest(1)->reviews()->create(['reviewer_id' => $me->id, 'review_started_at' => now()]);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pullRequests', 1)
                ->where('pullRequests.0.review', null)
                ->whereNot('pullRequests.0.running', null));
    }

    public function test_an_unfinished_review_older_than_two_hours_counts_as_abandoned(): void
    {
        $me = $this->member('Ich Selbst');
        $pullRequest = $this->pullRequest(1);
        $pullRequest->reviews()->create(['reviewer_id' => $me->id, 'review_started_at' => now()->subHours(3)]);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('pullRequests.0.running', null));

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.0.status', 'abandoned')
                ->where('reviews.0.findings', []));
    }

    public function test_list_marks_my_github_decision_and_own_pull_requests(): void
    {
        $me = $this->member('Ich Selbst');
        $approved = $this->pullRequest(1);
        $this->review($approved, $me);
        $approved->approvals()->create(['login' => 'ich-selbst_clockodo', 'state' => 'APPROVED']);
        $own = $this->pullRequest(2, ['author_id' => $me->id]);
        $this->review($own, $me);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pullRequests', fn ($rows) => collect($rows)->firstWhere('number', 1)['mine'] === ['githubDecision' => 'APPROVED', 'isAuthor' => false]
                    && collect($rows)->firstWhere('number', 2)['mine'] === ['githubDecision' => null, 'isAuthor' => true]));
    }

    public function test_list_and_detail_show_the_approvals(): void
    {
        $me = $this->member('Ich Selbst');
        $vera = $this->member('Vera Rolf');
        $pullRequest = $this->pullRequest(1, ['approval_groups' => [['label' => 'Backend', 'team' => 'backend-dev', 'approved' => 1, 'required' => 2]]]);
        $this->review($pullRequest, $me);
        $pullRequest->approvals()->create(['login' => 'vera-rolf_clockodo', 'user_id' => $vera->id, 'state' => 'APPROVED', 'commit_sha' => 'old']);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pullRequests.0.approvals.approved', 1)
                ->where('pullRequests.0.approvals.groups.0.required', 2)
                ->where('pullRequests.0.approvals.fulfilled', false));

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pullRequest.approvals.reviewers.0.name', 'Vera Rolf')
                ->where('pullRequest.approvals.reviewers.0.outdated', true));
    }

    private function node(int $number, array $attributes = []): array
    {
        return array_merge([
            'number' => $number, 'state' => 'OPEN', 'mergedAt' => null, 'closedAt' => null,
            'title' => "PR {$number}", 'headRefOid' => 'ccc', 'additions' => 3, 'deletions' => 1, 'changedFiles' => 1,
            'commitCount' => ['totalCount' => 1], 'comments' => ['totalCount' => 0],
        ], $attributes);
    }

    public function test_sync_of_reviewed_pull_requests_covers_only_the_open_ones_of_the_list(): void
    {
        config(['planstack.github_token' => 'test-token', 'planstack.github_api' => 'https://api.github.com']);
        $me = $this->member('Ich Selbst');
        $other = $this->member('Jemand Anderes');

        $this->review($mine = $this->pullRequest(1), $me);
        $this->review($this->pullRequest(2, ['state' => 'MERGED']), $me);
        $this->review($this->pullRequest(3), $other);

        Http::fake(['api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'pr1' => $this->node(1, ['state' => 'MERGED', 'mergedAt' => '2026-10-27T09:00:00Z']),
        ]]])]);

        $this->actingAs($me)
            ->post(route('pull-requests.sync', ['role' => 'reviewed', 'state' => 'open']))
            ->assertRedirect(route('pull-requests.index', ['state' => 'open', 'role' => 'reviewed']))
            ->assertSessionHas('status');

        $this->assertSame('MERGED', $mine->refresh()->state->value);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['query'], 'pr1:')
            && ! str_contains($request['query'], 'pr2:')
            && ! str_contains($request['query'], 'pr3:'));
    }

    public function test_sync_of_authored_pull_requests_adds_unknown_ones_and_closes_vanished_ones(): void
    {
        config(['planstack.github_token' => 'test-token', 'planstack.github_api' => 'https://api.github.com']);
        $me = $this->member('Ich Selbst');
        $known = $this->pullRequest(4, ['author_id' => $me->id]);
        $vanished = $this->pullRequest(5, ['author_id' => $me->id]);

        Http::fake(fn ($request) => str_contains($request['query'], 'search(')
            ? Http::response(['data' => ['search' => [
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                'nodes' => [
                    $this->node(4, ['title' => 'PR 4 neu', 'repository' => ['nameWithOwner' => 'clockodo-intern/main'], 'author' => ['login' => 'ich-selbst_clockodo'], 'headRefName' => 'feature/4', 'baseRefName' => 'main', 'body' => '', 'createdAt' => '2026-10-20T08:00:00Z']),
                    $this->node(9, ['repository' => ['nameWithOwner' => 'clockodo-intern/app'], 'author' => ['login' => 'ich-selbst_clockodo'], 'headRefName' => 'feature/9', 'baseRefName' => 'develop', 'body' => 'Text', 'createdAt' => '2026-10-26T08:00:00Z']),
                ],
            ]]])
            : Http::response(['data' => ['repository' => [
                'pr5' => $this->node(5, ['state' => 'CLOSED', 'closedAt' => '2026-10-27T09:00:00Z']),
            ]]]));

        $this->actingAs($me)
            ->post(route('pull-requests.sync', ['role' => 'authored', 'state' => 'open']))
            ->assertSessionHas('status');

        Http::assertSent(fn ($request) => ($request['variables']['q'] ?? null) === 'is:pr is:open author:ich-selbst_clockodo');
        $this->assertSame('PR 4 neu', $known->refresh()->title);
        $this->assertSame('CLOSED', $vanished->refresh()->state->value);

        $created = PullRequest::query()->where('repository', 'clockodo-intern/app')->where('pull_request_id', 9)->sole();
        $this->assertSame($me->id, $created->author_id);
        $this->assertSame('feature/9', $created->branch);
        $this->assertSame('develop', $created->target_branch);
        $this->assertSame($this->organization->id, $created->organization_id);
    }

    public function test_the_author_toggles_the_shared_flag(): void
    {
        $me = $this->member('Ich Selbst');
        $pullRequest = $this->pullRequest(1, ['author_id' => $me->id]);

        $this->actingAs($me)->post(route('pull-requests.share', $pullRequest))->assertRedirect();
        $pullRequest->refresh();
        $this->assertNotNull($pullRequest->shared_at);
        $this->assertSame($me->id, $pullRequest->shared_by_id);

        $this->actingAs($me)->post(route('pull-requests.share', $pullRequest))->assertRedirect();
        $this->assertNull($pullRequest->refresh()->shared_at);
    }

    public function test_grouping_is_saved_on_the_user(): void
    {
        $me = $this->member('Ich Selbst');

        $this->actingAs($me)->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('grouped', true));

        $this->actingAs($me)->post(route('pull-requests.grouping'), ['grouped' => false])->assertRedirect();
        $this->assertFalse($me->refresh()->pull_requests_grouped);

        $this->actingAs($me)->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('grouped', false));
    }

    public function test_only_the_author_can_share(): void
    {
        $author = $this->member('Autor');
        $other = $this->member('Jemand Anderes');
        $pullRequest = $this->pullRequest(1, ['author_id' => $author->id]);

        $this->actingAs($other)->post(route('pull-requests.share', $pullRequest))->assertForbidden();
        $this->assertNull($pullRequest->refresh()->shared_at);
    }

    public function test_shared_lists_all_shared_pull_requests_of_the_organization(): void
    {
        $me = $this->member('Ich Selbst');
        $colleague = $this->member('Kollegin');
        $this->pullRequest(1, ['author_id' => $colleague->id, 'shared_at' => now()]);
        $this->pullRequest(2, ['author_id' => $colleague->id]);
        $foreign = Organization::factory()->create();
        PullRequest::create([
            'organization_id' => $foreign->id, 'repository' => 'x/y', 'pull_request_id' => 3,
            'title' => 'Fremd', 'branch' => 'b', 'target_branch' => 'main', 'shared_at' => now(),
        ]);

        $this->actingAs($me)
            ->get(route('pull-requests.index', ['role' => 'shared']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.role', 'shared')
                ->has('pullRequests', 1)
                ->where('pullRequests.0.number', 1)
                ->where('pullRequests.0.shared', true));
    }

    public function test_list_and_detail_count_findings_that_need_a_po_decision(): void
    {
        $me = $this->member('Ich Selbst');
        $pullRequest = $this->pullRequest(1);
        $this->review($pullRequest, $me);
        $pullRequest->latestReview()->first()->findingItems()->create([
            'level' => 'IMPORTANT', 'type' => 'SUMMARY', 'requires_po_decision' => true, 'title' => 'Fachregel', 'message' => 'Offen.',
        ]);

        $this->actingAs($me)
            ->get(route('pull-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->where('pullRequests.0.review.poDecisions', 1));

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page->where('reviews.0.poDecisions', 1));
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
