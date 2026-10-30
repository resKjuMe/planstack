<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
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

    public function test_hidden_pull_requests_leave_only_my_lists(): void
    {
        $me = $this->member('Ich Selbst');
        $colleague = $this->member('Kollegin');
        $pullRequest = $this->pullRequest(1, ['author_id' => $me->id, 'shared_at' => now()]);
        $this->pullRequest(2, ['author_id' => $me->id]);

        $this->actingAs($me)->post(route('pull-requests.hide', $pullRequest))->assertRedirect();

        $this->actingAs($me)->get(route('pull-requests.index', ['role' => 'authored']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pullRequests', 1)
                ->where('pullRequests.0.number', 2)
                ->where('hiddenCount', 1));
        $this->actingAs($me)->get(route('pull-requests.index', ['role' => 'authored', 'hidden' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.hidden', true)
                ->has('pullRequests', 1)
                ->where('pullRequests.0.number', 1));
        $this->actingAs($me)->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page->where('pullRequest.hidden', true));
        $this->actingAs($colleague)->get(route('pull-requests.index', ['role' => 'shared']))
            ->assertInertia(fn (Assert $page) => $page->has('pullRequests', 1)->where('hiddenCount', 0));

        $this->actingAs($me)->post(route('pull-requests.hide', $pullRequest))->assertRedirect();
        $this->actingAs($me)->get(route('pull-requests.index', ['role' => 'authored']))
            ->assertInertia(fn (Assert $page) => $page->has('pullRequests', 2)->where('hiddenCount', 0));
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

    /** @return array{0: PullRequest, 1: PullRequestReview} */
    private function reviewWithPoFinding(User $reviewer, array $prAttributes = []): array
    {
        $pullRequest = $this->pullRequest(31, $prAttributes);
        $this->review($pullRequest, $reviewer, ['decision' => 'REQUEST_CHANGES', 'findings' => 2]);
        $review = $pullRequest->reviews()->sole();
        $review->findingItems()->create([
            'level' => 'IMPORTANT', 'type' => 'SUMMARY', 'requires_po_decision' => true, 'title' => 'Fachregel', 'message' => 'Offen.',
        ]);

        return [$pullRequest, $review];
    }

    /** @return list<array{id: int, decision: string, note: string}> */
    private function decisions(PullRequestReview $review, string $poNote): array
    {
        return $review->findingItems()->get()->map(fn ($f) => [
            'id' => $f->id,
            'decision' => $f->requires_po_decision ? 'FIX' : 'SKIP',
            'note' => $f->requires_po_decision ? $poNote : '',
        ])->all();
    }

    public function test_requesting_a_fix_records_the_decisions_per_finding(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.0.fixUrl', route('pull-requests.fix', [$pullRequest, $review]))
                ->where('reviews.0.fix', null));

        $this->actingAs($me)
            ->post(route('pull-requests.fix', [$pullRequest, $review]), ['findings' => $this->decisions($review, 'Bestandsdaten bleiben.')])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('requested', $review->fixStatus());
        $this->assertSame($me->id, $review->fix_requested_by_id);
        $po = $review->findingItems()->where('requires_po_decision', true)->sole();
        $this->assertSame('FIX', $po->fix_decision->value);
        $this->assertSame('Bestandsdaten bleiben.', $po->fix_note);
        $this->assertSame('SKIP', $review->findingItems()->where('requires_po_decision', false)->sole()->fix_decision->value);

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.0.fix.status', 'requested')
                ->where('reviews.0.fix.requestedBy', 'Ich Selbst'));
    }

    public function test_a_po_finding_needs_a_recorded_decision(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);

        $this->actingAs($me)
            ->post(route('pull-requests.fix', [$pullRequest, $review]), ['findings' => $this->decisions($review, '  ')])
            ->assertSessionHasErrors('findings.1.note');

        $this->assertNull($review->fresh()->fix_requested_at);
    }

    public function test_a_fix_needs_a_decision_for_every_finding(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);

        $this->actingAs($me)
            ->post(route('pull-requests.fix', [$pullRequest, $review]), ['findings' => array_slice($this->decisions($review, 'ok'), 0, 1)])
            ->assertSessionHasErrors('findings');
    }

    public function test_only_the_latest_review_of_an_open_pull_request_is_fixable(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me, ['state' => 'MERGED']);

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page->where('reviews.0.fixUrl', null));

        $this->actingAs($me)
            ->post(route('pull-requests.fix', [$pullRequest, $review]), ['findings' => $this->decisions($review, 'ok')])
            ->assertStatus(409);

        $pullRequest->update(['state' => 'OPEN']);
        $this->review($pullRequest, $me, ['review_finished_at' => now()->addMinute()]);

        $this->actingAs($me)
            ->post(route('pull-requests.fix', [$pullRequest, $review]), ['findings' => $this->decisions($review, 'ok')])
            ->assertStatus(409);
    }

    public function test_requesting_a_fix_in_another_organization_is_not_found(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);
        $stranger = $this->member('Eve Fremd', Organization::factory()->create());

        $this->actingAs($stranger)
            ->post(route('pull-requests.fix', [$pullRequest, $review]), ['findings' => $this->decisions($review, 'ok')])
            ->assertNotFound();
    }

    public function test_fixing_a_single_finding_requests_a_fix_for_just_that_finding(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);
        $nit = $review->findingItems()->where('requires_po_decision', false)->sole();
        $nit->update(['fix_decision' => 'SKIP', 'fix_outcome' => 'SKIPPED']);
        $po = $review->findingItems()->where('requires_po_decision', true)->sole();

        $this->actingAs($me)
            ->get(route('pull-requests.show', $pullRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.0.findings.0.fixUrl', route('pull-requests.finding-fix', [$pullRequest, $review, $po])));

        $this->actingAs($me)
            ->post(route('pull-requests.finding-fix', [$pullRequest, $review, $po]), ['decision' => 'FIX', 'note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($me)
            ->post(route('pull-requests.finding-fix', [$pullRequest, $review, $po]), ['decision' => 'FIX', 'note' => 'Bestandsdaten bleiben.'])
            ->assertRedirect();

        $this->assertSame('requested', $review->fresh()->fixStatus());
        $this->assertSame('FIX', $po->fresh()->fix_decision->value);
        $this->assertSame('Bestandsdaten bleiben.', $po->fresh()->fix_note);
        $this->assertSame('SKIPPED', $nit->fresh()->fix_outcome->value);
    }

    public function test_skipping_a_single_finding_records_the_reason_in_the_pr_body_and_removes_it(): void
    {
        config(['planstack.github_token' => 'test-token', 'planstack.github_api' => 'https://api.github.com']);
        Http::fake([
            'api.github.com/repos/clockodo-intern/main/pulls/31' => Http::sequence()
                ->push(['body' => "Beschreibung\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"])
                ->push(['number' => 31]),
        ]);
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);
        $review->update(['findings_hint' => 1, 'findings_important' => 1]);
        $nit = $review->findingItems()->where('requires_po_decision', false)->sole();

        $this->actingAs($me)
            ->post(route('pull-requests.finding-fix', [$pullRequest, $review, $nit]), ['decision' => 'SKIP', 'note' => ' '])
            ->assertSessionHasErrors('note');
        Http::assertNothingSent();

        $this->actingAs($me)
            ->post(route('pull-requests.finding-fix', [$pullRequest, $review, $nit]), ['decision' => 'SKIP', 'note' => 'Bewusst so belassen.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request['body'], "## Review-Entscheidungen\n\n### Nicht umgesetzt\n\n- **Nit** (Hinweis): Bewusst so belassen. — Ich Selbst")
            && str_ends_with($request['body'], '🤖 Generated with [Claude Code](https://claude.com/claude-code)'));
        $this->assertNull($nit->fresh());
        $review->refresh();
        $this->assertSame(1, $review->findings);
        $this->assertSame(0, $review->findings_hint);
        $this->assertSame(1, $review->findings_important);
        $this->assertStringContainsString('Bewusst so belassen.', $pullRequest->fresh()->description);
    }

    public function test_a_finding_stays_when_github_rejects_the_pr_body(): void
    {
        config(['planstack.github_token' => 'test-token', 'planstack.github_api' => 'https://api.github.com']);
        Http::fake(['api.github.com/*' => Http::response([], 403)]);
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);
        $nit = $review->findingItems()->where('requires_po_decision', false)->sole();

        $this->actingAs($me)
            ->post(route('pull-requests.finding-fix', [$pullRequest, $review, $nit]), ['decision' => 'SKIP', 'note' => 'Bewusst so belassen.'])
            ->assertSessionHasErrors('note');

        $this->assertNotNull($nit->fresh());
        $this->assertSame(2, $review->fresh()->findings);
    }

    public function test_a_finding_of_another_review_is_not_found(): void
    {
        $me = $this->member('Ich Selbst');
        [$pullRequest, $review] = $this->reviewWithPoFinding($me);
        $this->review($other = $this->pullRequest(32), $me);
        $foreign = $other->reviews()->sole()->findingItems()->sole();

        $this->actingAs($me)
            ->post(route('pull-requests.finding-fix', [$pullRequest, $review, $foreign]), ['decision' => 'FIX'])
            ->assertNotFound();
    }
}
