<?php

namespace App\Models;

use App\Concerns\BroadcastsEntityChange;
use App\Enums\PullRequestState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PullRequest extends Model
{
    use BroadcastsEntityChange;

    /** GitHub-Konvention: `vorname-nachname_clockodo` ↔ Planstack-Slug `vorname-nachname`. */
    private const LOGIN_SUFFIX = '_clockodo';

    protected $attributes = [
        'state' => 'OPEN',
    ];

    protected $fillable = [
        'organization_id',
        'repository',
        'pull_request_id',
        'author_id',
        'author_login',
        'title',
        'branch',
        'target_branch',
        'last_commit',
        'description',
        'state',
        'changed_files',
        'additions',
        'deletions',
        'commits',
        'comments',
        'review_comments',
        'approval_groups',
        'review_requirement',
        'ci_state',
        'opened_at',
        'merged_at',
        'closed_at',
        'shared_at',
        'shared_by_id',
    ];

    protected function casts(): array
    {
        return [
            'pull_request_id' => 'integer',
            'state' => PullRequestState::class,
            'changed_files' => 'integer',
            'additions' => 'integer',
            'deletions' => 'integer',
            'commits' => 'integer',
            'comments' => 'integer',
            'review_comments' => 'integer',
            'approval_groups' => 'array',
            'review_requirement' => 'array',
            'opened_at' => 'datetime',
            'merged_at' => 'datetime',
            'closed_at' => 'datetime',
            'shared_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PullRequestReview::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PullRequestApproval::class);
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(PullRequestReview::class)->ofMany(
            ['review_finished_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereNotNull('review_finished_at'),
        );
    }

    public function runningReview(): HasOne
    {
        return $this->hasOne(PullRequestReview::class)->ofMany(
            ['review_started_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->running(),
        );
    }

    /**
     * Gemeldet als `pull-request`; die PR-Seiten laden darauf neu.
     *
     * @return array{entity: string, id: int, organization_id: int|null}
     */
    public function entityChangeScope(): ?array
    {
        return [
            'entity' => 'pull-request',
            'id' => $this->id,
            'organization_id' => $this->organization_id,
        ];
    }

    public function url(): string
    {
        return "https://github.com/{$this->repository}/pull/{$this->pull_request_id}";
    }

    public static function loginFor(User $user): string
    {
        return $user->slug.self::LOGIN_SUFFIX;
    }

    /**
     * Planstack-User der Organisation zum GitHub-Login, oder null.
     */
    public static function resolveAuthor(int $organizationId, ?string $login): ?User
    {
        if ($login === null || $login === '') {
            return null;
        }

        $slug = Str::lower(Str::beforeLast($login, self::LOGIN_SUFFIX));

        return User::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->first();
    }
}
