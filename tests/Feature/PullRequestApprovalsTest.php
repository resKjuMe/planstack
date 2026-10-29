<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\User;
use App\Support\PullRequestApprovals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PullRequestApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private const SUMMARY = "## Critical Files Review\n\n| Group | Team | Approvals | Files | Status |\n| --- | --- | --- | --- | --- |\n| Backend | backend-dev | 1 / 2 | 6 | 1 missing |\n| Frontend | frontend-dev | 2 / 2 | 1 | ok |\n\nRequired approvals are missing.";

    private const REQUIREMENT = "## Review Requirement\n\n**Stufe:** `high` — KI-Einschätzung `high`, Confidence 60 %\n\n**Approvals:** 0 von 2 (gezählt auf dem aktuellen Commit, ohne Autor)\n\n| Stufe | Wahrscheinlichkeit |\n| --- | --- |\n| high | 60 % ◀ |\n\n### Critical Files\n\n- Gruppe **Backend**: `backend/laravel/app/Exceptions/ApiExceptionHandler.php`\n\n### Warum nicht trivial\n\n- Critical Files, Gruppe Backend: `backend/laravel/app/Exceptions/ApiExceptionHandler.php`";

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->organization_id = $this->organization->id;
        $user->save();

        return $user;
    }

    private function pullRequest(array $attributes = []): PullRequest
    {
        return PullRequest::create(array_merge([
            'organization_id' => $this->organization->id,
            'repository' => 'clockodo-intern/main',
            'pull_request_id' => 1,
            'author_login' => 'max-muster_clockodo',
            'title' => 'PR',
            'branch' => 'feature',
            'target_branch' => 'main',
            'last_commit' => 'bbb',
        ], $attributes));
    }

    /** @return array<string, array{0: ?string, 1: ?array}> */
    public static function summaries(): array
    {
        return [
            'keine Summary' => [null, null],
            'keine kritischen Dateien' => ["## Critical Files Review\n\nPR #10857 touches no critical files.", []],
            'unbekanntes Format' => ['Irgendwas anderes', null],
            'zwei Gruppen' => [self::SUMMARY, [
                ['label' => 'Backend', 'team' => 'backend-dev', 'approved' => 1, 'required' => 2],
                ['label' => 'Frontend', 'team' => 'frontend-dev', 'approved' => 2, 'required' => 2],
            ]],
        ];
    }

    #[DataProvider('summaries')]
    public function test_parses_the_groups_from_the_check_summary(?string $summary, ?array $expected): void
    {
        $this->assertSame($expected, PullRequestApprovals::parseGroups($summary));
    }

    /** @return array<string, array{0: ?string, 1: ?array}> */
    public static function requirements(): array
    {
        return [
            'keine Summary' => [null, null],
            'früherer Check' => [self::SUMMARY, null],
            'ohne kritische Dateien' => ["## Review Requirement\n\n**Stufe:** `normal` — KI-Einschätzung `normal`\n\n**Approvals:** 1 von 1 (gezählt auf dem aktuellen Commit, ohne Autor)", [
                'level' => 'normal', 'required' => 1, 'criticalGroups' => [],
            ]],
            'trivial ohne Approvals' => ["## Review Requirement\n\n**Stufe:** `trivial` — KI-Einschätzung `trivial`, Confidence 70 %\n\n**Approvals:** keine nötig", [
                'level' => 'trivial', 'required' => 0, 'criticalGroups' => [],
            ]],
            'mit kritischen Dateien' => [self::REQUIREMENT, [
                'level' => 'high', 'required' => 2, 'criticalGroups' => ['Backend'],
            ]],
        ];
    }

    #[DataProvider('requirements')]
    public function test_parses_the_requirement_from_the_check_summary(?string $summary, ?array $expected): void
    {
        $this->assertSame($expected, PullRequestApprovals::parseRequirement($summary));
        $this->assertNull(PullRequestApprovals::parseGroups(self::REQUIREMENT));
    }

    public function test_status_takes_the_required_approvals_from_the_requirement(): void
    {
        $pullRequest = $this->pullRequest(['review_requirement' => PullRequestApprovals::parseRequirement(self::REQUIREMENT)]);
        PullRequestApprovals::sync($pullRequest, [['login' => 'a_clockodo', 'state' => 'APPROVED']]);

        $status = PullRequestApprovals::status($pullRequest, $pullRequest->approvals()->get());

        $this->assertSame(2, $status['required']);
        $this->assertSame('high', $status['level']);
        $this->assertSame(['Backend'], $status['criticalGroups']);
        $this->assertFalse($status['fulfilled']);

        PullRequestApprovals::sync($pullRequest, [
            ['login' => 'a_clockodo', 'state' => 'APPROVED'],
            ['login' => 'b_clockodo', 'state' => 'APPROVED'],
        ]);
        $this->assertTrue(PullRequestApprovals::status($pullRequest, $pullRequest->approvals()->get())['fulfilled']);
    }

    public function test_sync_keeps_the_latest_decision_per_reviewer(): void
    {
        $vera = $this->member('Vera Rolf');
        $pullRequest = $this->pullRequest();

        PullRequestApprovals::sync($pullRequest, [
            ['login' => 'vera-rolf_clockodo', 'state' => 'CHANGES_REQUESTED', 'submitted_at' => '2026-10-26T10:00:00Z', 'commit_sha' => 'aaa'],
            ['login' => 'vera-rolf_clockodo', 'state' => 'APPROVED', 'submitted_at' => '2026-10-26T12:00:00Z', 'commit_sha' => 'bbb'],
            ['login' => 'jan-teriete_clockodo', 'state' => 'APPROVED', 'submitted_at' => '2026-10-26T09:00:00Z'],
            ['login' => 'jan-teriete_clockodo', 'state' => 'DISMISSED', 'submitted_at' => '2026-10-26T11:00:00Z'],
            ['login' => 'hagen-drees_clockodo', 'state' => 'COMMENTED', 'submitted_at' => '2026-10-26T11:00:00Z'],
            ['login' => 'max-muster_clockodo', 'state' => 'APPROVED', 'submitted_at' => '2026-10-26T11:00:00Z'],
        ]);

        $approvals = $pullRequest->approvals()->get();
        $this->assertSame(['vera-rolf_clockodo'], $approvals->pluck('login')->all());
        $this->assertSame('APPROVED', $approvals[0]->state);
        $this->assertSame($vera->id, $approvals[0]->user_id);
    }

    public function test_sync_removes_reviewers_that_are_gone(): void
    {
        $pullRequest = $this->pullRequest();
        PullRequestApprovals::sync($pullRequest, [['login' => 'a_clockodo', 'state' => 'APPROVED']]);

        PullRequestApprovals::sync($pullRequest, [['login' => 'b_clockodo', 'state' => 'CHANGES_REQUESTED']]);

        $this->assertSame(['b_clockodo'], $pullRequest->approvals()->pluck('login')->all());
    }

    public function test_status_needs_one_approval_every_group_and_no_open_change_request(): void
    {
        $pullRequest = $this->pullRequest(['approval_groups' => PullRequestApprovals::parseGroups(self::SUMMARY)]);
        PullRequestApprovals::sync($pullRequest, [['login' => 'a_clockodo', 'state' => 'APPROVED']]);

        $status = PullRequestApprovals::status($pullRequest, $pullRequest->approvals()->get());

        $this->assertSame(1, $status['approved']);
        $this->assertSame(1, $status['required']);
        $this->assertFalse($status['fulfilled']);

        $pullRequest->update(['approval_groups' => []]);
        $this->assertTrue(PullRequestApprovals::status($pullRequest, $pullRequest->approvals()->get())['fulfilled']);

        PullRequestApprovals::sync($pullRequest, [
            ['login' => 'a_clockodo', 'state' => 'APPROVED'],
            ['login' => 'b_clockodo', 'state' => 'CHANGES_REQUESTED'],
        ]);
        $this->assertFalse(PullRequestApprovals::status($pullRequest, $pullRequest->approvals()->get())['fulfilled']);
    }

    public function test_upsert_stores_reviews_and_groups_sent_by_the_skill(): void
    {
        Sanctum::actingAs($this->member('Christian Mietze'));

        $this->putJson('/api/pull-requests', [
            'repository' => 'clockodo-intern/main',
            'pull_request_id' => 10880,
            'author_login' => 'jonas-grobe_clockodo',
            'title' => 'PR',
            'branch' => 'feature',
            'target_branch' => 'main',
            'github_reviews' => [
                ['login' => 'martin-seysen_clockodo', 'state' => 'APPROVED', 'submitted_at' => '2026-10-26T15:20:15Z', 'commit_id' => '80ef477'],
                ['login' => 'vera-rolf_clockodo', 'state' => 'COMMENTED', 'submitted_at' => '2026-10-26T15:00:00Z'],
            ],
            'approval_check_summary' => self::SUMMARY,
        ])
            ->assertCreated()
            ->assertJsonPath('data.approvals.approved', 1)
            ->assertJsonPath('data.approvals.groups.0.label', 'Backend')
            ->assertJsonPath('data.approvals.fulfilled', false);

        $this->assertSame('80ef477', PullRequest::sole()->approvals()->sole()->commit_sha);
    }

    public function test_upsert_stores_the_requirement_sent_by_the_skill(): void
    {
        Sanctum::actingAs($this->member('Christian Mietze'));

        $this->putJson('/api/pull-requests', [
            'repository' => 'clockodo-intern/main',
            'pull_request_id' => 10907,
            'title' => 'PR',
            'branch' => 'feature',
            'target_branch' => 'main',
            'approval_check_summary' => self::REQUIREMENT,
        ])
            ->assertCreated()
            ->assertJsonPath('data.approvals.required', 2)
            ->assertJsonPath('data.approvals.level', 'high')
            ->assertJsonPath('data.approvals.criticalGroups', ['Backend'])
            ->assertJsonPath('data.approvals.groups', null);
    }

    public function test_upsert_without_approval_fields_keeps_the_stored_ones(): void
    {
        Sanctum::actingAs($this->member('Christian Mietze'));
        $pullRequest = $this->pullRequest(['approval_groups' => []]);
        PullRequestApprovals::sync($pullRequest, [['login' => 'a_clockodo', 'state' => 'APPROVED']]);

        $this->putJson('/api/pull-requests', [
            'repository' => 'clockodo-intern/main',
            'pull_request_id' => 1,
            'title' => 'Neu',
            'branch' => 'feature',
            'target_branch' => 'main',
        ])->assertOk();

        $pullRequest->refresh();
        $this->assertSame([], $pullRequest->approval_groups);
        $this->assertSame(1, $pullRequest->approvals()->count());
    }
}
