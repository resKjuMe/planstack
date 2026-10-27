<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_store_review_derives_the_counts_from_the_findings(): void
    {
        $user = $this->member();
        Sanctum::actingAs($user);
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');

        $this->postJson("/api/pull-requests/{$id}/reviews", $this->reviewPayload())
            ->assertCreated()
            ->assertJsonPath('data.findings', 4)
            ->assertJsonPath('data.findings_critical', 1)
            ->assertJsonPath('data.findings_important', 1)
            ->assertJsonPath('data.findings_hint', 2)
            ->assertJsonPath('data.url', route('pull-requests.show', $id));

        $review = PullRequest::findOrFail($id)->reviews()->sole();
        $this->assertSame($user->id, $review->reviewer_id);
        $this->assertSame('claude-opus-5-5', $review->model);
        $this->assertSame(1, $review->findingItems()->where('requires_po_decision', true)->count());
    }

    public function test_store_review_accepts_a_review_without_findings(): void
    {
        Sanctum::actingAs($this->member());
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');

        $this->postJson("/api/pull-requests/{$id}/reviews", $this->reviewPayload(['decision' => 'APPROVE', 'findings' => []]))
            ->assertCreated()
            ->assertJsonPath('data.findings', 0);
    }

    public function test_inline_findings_need_file_and_line(): void
    {
        Sanctum::actingAs($this->member());
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');

        $this->postJson("/api/pull-requests/{$id}/reviews", $this->reviewPayload([
            'findings' => [['level' => 'HINT', 'type' => 'INLINE', 'title' => 'x', 'message' => 'y']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['findings.0.file', 'findings.0.line']);
    }

    public function test_scores_must_be_between_0_and_100(): void
    {
        Sanctum::actingAs($this->member());
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');

        $this->postJson("/api/pull-requests/{$id}/reviews", $this->reviewPayload(['criticality' => 101, 'ai_reviewability' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['criticality', 'ai_reviewability']);
    }

    public function test_reviews_on_pull_requests_of_another_organization_are_not_found(): void
    {
        Sanctum::actingAs($this->member());
        $id = $this->putJson('/api/pull-requests', $this->pullRequestPayload())->json('data.id');

        Sanctum::actingAs($this->member('Eve Fremd'));

        $this->postJson("/api/pull-requests/{$id}/reviews", $this->reviewPayload())->assertNotFound();
    }
}
