<?php

namespace Tests\Feature\Api;

use App\Jobs\BroadcastEntityChange;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PullRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = 'Christian Mietze', ?Organization $organization = null): User
    {
        $user = User::factory()->create(['name' => $name]);
        $organization ??= Organization::factory()->create(['created_by_id' => $user->id]);

        // organization_id ist nicht fillable — direkt zuweisen.
        $user->organization_id = $organization->id;
        $user->save();

        return $user;
    }

    /** @return array<string, mixed> */
    private function pullRequestPayload(array $overrides = []): array
    {
        return array_merge([
            'repository' => 'clockodo-intern/main',
            'pull_request_id' => 9050,
            'author_login' => 'max-muster_clockodo',
            'title' => 'MN-6556: Merge-Log companies_id',
            'branch' => 'feature/mn-6556',
            'target_branch' => 'main',
            'last_commit' => 'abc1234def5678abc1234def5678abc1234def56',
            'description' => 'Beschreibung',
            'state' => 'OPEN',
            'changed_files' => 3,
            'additions' => 40,
            'deletions' => 12,
            'commits' => 2,
            'comments' => 1,
            'review_comments' => 4,
            'opened_at' => '2026-10-20T08:00:00Z',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function reviewPayload(array $overrides = []): array
    {
        return array_merge([
            'decision' => 'REQUEST_CHANGES',
            'criticality' => 70,
            'criticality_reason' => 'Rechte-Merge betrifft alle Mandanten.',
            'ai_reviewability' => 40,
            'ai_reviewability_reason' => 'Fachregel braucht Domänenwissen.',
            'po_review_likelihood' => 85,
            'po_review_likelihood_reason' => 'Neuer Dialog im Kunden-UI.',
            'summary' => "## TLDR\n\nFix korrekt, Test fehlt.",
            'commit_sha' => 'abc1234def5678abc1234def5678abc1234def56',
            'strictness' => 'strict',
            'thoroughness' => 'meticulous',
            'model' => 'claude-opus-5-5',
            'findings' => [
                ['level' => 'CRITICAL', 'type' => 'INLINE', 'file' => 'src/Foo.php', 'line' => 12, 'title' => 'Null-Prüfung fehlt', 'message' => 'Fehlszenario …'],
                ['level' => 'IMPORTANT', 'type' => 'SUMMARY', 'requires_po_decision' => true, 'title' => 'Verhalten für Bestandsdaten', 'message' => 'Fachlich offen.'],
                ['level' => 'HINT', 'type' => 'INLINE', 'file' => 'src/Bar.php', 'line' => 3, 'title' => 'Kommentar redundant', 'message' => 'Streichen.'],
                ['level' => 'HINT', 'type' => 'SUMMARY', 'title' => 'Name', 'message' => 'Besser benennen.'],
            ],
        ], $overrides);
    }

    public function test_upsert_creates_the_pull_request_and_maps_the_author_by_slug(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs($this->member(organization: $organization));
        $author = $this->member('Max Muster', $organization);

        $this->putJson('/api/pull-requests', $this->pullRequestPayload())
            ->assertCreated()
            ->assertJsonPath('data.author_id', $author->id)
            ->assertJsonPath('data.state', 'OPEN');

        $pullRequest = PullRequest::sole();
        $this->assertSame($organization->id, $pullRequest->organization_id);
        $this->assertSame('max-muster_clockodo', $pullRequest->author_login);
        $this->assertSame(4, $pullRequest->review_comments);
    }

    public function test_upsert_updates_the_existing_pull_request_of_the_same_repository_and_number(): void
    {
        Sanctum::actingAs($this->member());

        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->assertCreated()->json('data.id');

        $this->putJson('/api/pull-requests', $this->pullRequestPayload(['title' => 'Neuer Titel', 'last_commit' => 'fff']))
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertSame(1, PullRequest::count());
        $this->assertSame('Neuer Titel', PullRequest::sole()->title);
    }

    public function test_upsert_keeps_unknown_authors_as_login_only(): void
    {
        Sanctum::actingAs($this->member());

        $this->putJson('/api/pull-requests', $this->pullRequestPayload(['author_login' => 'dependabot[bot]']))
            ->assertCreated()
            ->assertJsonPath('data.author_id', null)
            ->assertJsonPath('data.author_login', 'dependabot[bot]');
    }

    public function test_the_same_number_in_another_repository_is_a_separate_pull_request(): void
    {
        Sanctum::actingAs($this->member());

        $this->putJson('/api/pull-requests', $this->pullRequestPayload())->assertCreated();
        $this->putJson('/api/pull-requests', $this->pullRequestPayload(['repository' => 'resKjuMe/empire-of-time']))->assertCreated();

        $this->assertSame(2, PullRequest::count());
    }

    public function test_upsert_requires_an_organization(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/pull-requests', $this->pullRequestPayload())->assertForbidden();
    }

    /** @return array{0: int, 1: int} PR-ID und Review-ID */
    private function startedReview(): array
    {
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');
        $reviewId = $this->postJson("/api/pull-requests/{$id}/reviews", ['model' => 'claude-opus-5-5'])->json('data.id');

        return [$id, $reviewId];
    }

    public function test_start_review_records_a_running_review_without_rating(): void
    {
        $user = $this->member();
        Sanctum::actingAs($user);
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');

        $this->postJson("/api/pull-requests/{$id}/reviews", ['commit_sha' => 'abc1234', 'strictness' => 'strict'])
            ->assertCreated()
            ->assertJsonPath('data.decision', null)
            ->assertJsonPath('data.review_finished_at', null);

        $review = PullRequest::findOrFail($id)->reviews()->sole();
        $this->assertSame($user->id, $review->reviewer_id);
        $this->assertNotNull($review->review_started_at);
        $this->assertNull($review->criticality);
        $this->assertSame('abc1234', $review->commit_sha);
    }

    public function test_finish_review_derives_the_counts_from_the_findings(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload())
            ->assertOk()
            ->assertJsonPath('data.id', $reviewId)
            ->assertJsonPath('data.findings', 4)
            ->assertJsonPath('data.findings_critical', 1)
            ->assertJsonPath('data.findings_important', 1)
            ->assertJsonPath('data.findings_hint', 2)
            ->assertJsonPath('data.po_review_likelihood', 85)
            ->assertJsonPath('data.url', route('pull-requests.show', $id));

        $review = PullRequest::findOrFail($id)->reviews()->sole();
        $this->assertNotNull($review->review_finished_at);
        $this->assertSame('REQUEST_CHANGES', $review->decision->value);
        $this->assertSame(1, $review->findingItems()->where('requires_po_decision', true)->count());
        $this->assertSame('Neuer Dialog im Kunden-UI.', $review->po_review_likelihood_reason);
    }

    public function test_finish_review_accepts_a_review_without_po_review_likelihood(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();
        $payload = $this->reviewPayload();
        unset($payload['po_review_likelihood'], $payload['po_review_likelihood_reason']);

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.po_review_likelihood', null);
    }

    public function test_starting_and_finishing_a_review_broadcast_the_pull_request(): void
    {
        // Coalescing-Gedächtnis des Traits überlebt sonst den vorigen Test.
        \Closure::bind(fn () => PullRequestReview::$broadcastCoalesce = [], null, PullRequestReview::class)();
        Sanctum::actingAs($this->member());
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');
        Bus::fake([BroadcastEntityChange::class]);

        $reviewId = $this->postJson("/api/pull-requests/{$id}/reviews")->assertCreated()->json('data.id');
        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload())->assertOk();

        foreach (['insert', 'update'] as $action) {
            Bus::assertDispatchedAfterResponse(
                BroadcastEntityChange::class,
                fn (BroadcastEntityChange $job) => $job->payload['entity'] === 'pull-request'
                    && $job->payload['id'] === $id
                    && $job->payload['action'] === $action,
            );
        }
    }

    public function test_finish_review_accepts_a_review_without_findings(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload(['decision' => 'APPROVE', 'findings' => []]))
            ->assertOk()
            ->assertJsonPath('data.findings', 0);
    }

    public function test_a_finished_review_cannot_be_finished_again(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload())->assertOk();
        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload())->assertConflict();

        $this->assertSame(4, PullRequest::findOrFail($id)->reviews()->sole()->findingItems()->count());
    }

    public function test_only_the_reviewer_can_finish_the_review(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs($this->member(organization: $organization));
        [$id, $reviewId] = $this->startedReview();

        Sanctum::actingAs($this->member('Kollege Zwei', $organization));

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload())->assertForbidden();
    }

    public function test_inline_findings_need_file_and_line(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload([
            'findings' => [['level' => 'HINT', 'type' => 'INLINE', 'title' => 'x', 'message' => 'y']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['findings.0.file', 'findings.0.line']);
    }

    public function test_scores_must_be_between_0_and_100(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();

        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload(['criticality' => 101, 'ai_reviewability' => -1, 'po_review_likelihood' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['criticality', 'ai_reviewability', 'po_review_likelihood']);
    }

    public function test_reviews_on_pull_requests_of_another_organization_are_not_found(): void
    {
        Sanctum::actingAs($this->member());
        [$id, $reviewId] = $this->startedReview();

        Sanctum::actingAs($this->member('Eve Fremd'));

        $this->postJson("/api/pull-requests/{$id}/reviews")->assertNotFound();
        $this->putJson("/api/pull-requests/{$id}/reviews/{$reviewId}", $this->reviewPayload())->assertNotFound();
    }

    public function test_a_review_of_another_pull_request_is_not_found(): void
    {
        Sanctum::actingAs($this->member());
        [, $reviewId] = $this->startedReview();
        $otherId = $this->putJson('/api/pull-requests', $this->pullRequestPayload(['pull_request_id' => 1]))->json('data.id');

        $this->putJson("/api/pull-requests/{$otherId}/reviews/{$reviewId}", $this->reviewPayload())->assertNotFound();
    }

    public function test_review_states_report_my_review_per_pull_request(): void
    {
        $me = $this->member();
        $colleague = $this->member('Kollege Zwei', Organization::findOrFail($me->organization_id));
        Sanctum::actingAs($me);

        $make = fn (int $n) => PullRequest::create([
            'organization_id' => $me->organization_id, 'repository' => 'clockodo-intern/main', 'pull_request_id' => $n,
            'title' => "PR {$n}", 'branch' => "b{$n}", 'target_branch' => 'main', 'last_commit' => 'head',
        ]);
        $finish = fn (PullRequest $pr, string $sha, int $reviewer) => $pr->reviews()->create([
            'reviewer_id' => $reviewer, 'review_started_at' => now()->subHour(), 'review_finished_at' => now()->subMinutes(30),
            'decision' => 'APPROVE', 'criticality' => 10, 'ai_reviewability' => 90, 'commit_sha' => $sha,
            'findings' => 3, 'findings_critical' => 1, 'findings_important' => 1, 'findings_hint' => 1,
        ]);

        $finish($make(1), 'head', $me->id);
        $finish($make(2), 'old', $me->id);
        $running = $make(3);
        $finish($running, 'head', $me->id);
        $running->reviews()->create(['reviewer_id' => $me->id, 'review_started_at' => now()->subMinutes(5)]);
        $finish($make(4), 'head', $colleague->id);

        $this->getJson('/api/pull-requests/review-states?repository=clockodo-intern/main&prs=1,2,3,4,5')
            ->assertOk()
            ->assertJsonPath('data.1.state', 'current')
            ->assertJsonPath('data.1.findings', 3)
            ->assertJsonPath('data.1.critical', 1)
            ->assertJsonPath('data.1.recommendation', 'APPROVE')
            ->assertJsonPath('data.2.state', 'outdated')
            ->assertJsonPath('data.3.state', 'running')
            ->assertJsonPath('data.3.recommendation', 'APPROVE')
            ->assertJsonPath('data.4.state', 'none')
            ->assertJsonPath('data.5.state', 'none');
    }

    public function test_review_states_validate_the_pr_list(): void
    {
        Sanctum::actingAs($this->member());

        $this->getJson('/api/pull-requests/review-states?repository=a/b&prs=1;drop')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prs']);
    }
}
