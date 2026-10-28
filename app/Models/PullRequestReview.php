<?php

namespace App\Models;

use App\Concerns\BroadcastsEntityChange;
use App\Enums\ReviewRecommendation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PullRequestReview extends Model
{
    use BroadcastsEntityChange;

    /** Ein Review, das so lange ohne Abschluss ist, gilt als abgebrochen. */
    public const ABANDONED_AFTER_HOURS = 2;

    protected $fillable = [
        'pull_request_id',
        'reviewer_id',
        'review_started_at',
        'review_finished_at',
        'decision',
        'findings',
        'findings_critical',
        'findings_important',
        'findings_hint',
        'criticality',
        'criticality_reason',
        'ai_reviewability',
        'ai_reviewability_reason',
        'po_review_likelihood',
        'po_review_likelihood_reason',
        'summary',
        'commit_sha',
        'strictness',
        'thoroughness',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'review_started_at' => 'datetime',
            'review_finished_at' => 'datetime',
            'decision' => ReviewRecommendation::class,
            'findings' => 'integer',
            'findings_critical' => 'integer',
            'findings_important' => 'integer',
            'findings_hint' => 'integer',
            'criticality' => 'integer',
            'ai_reviewability' => 'integer',
            'po_review_likelihood' => 'integer',
        ];
    }

    /**
     * Gemeldet als der zugehörige `pull-request`.
     *
     * @return array{entity: string, id: int, organization_id: int|null}
     */
    public function entityChangeScope(): ?array
    {
        return [
            'entity' => 'pull-request',
            'id' => $this->pull_request_id,
            'organization_id' => $this->pullRequest?->organization_id,
        ];
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function findingItems(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }

    public function scopeFinished(Builder $query): void
    {
        $query->whereNotNull('review_finished_at');
    }

    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('review_finished_at')
            ->where('review_started_at', '>=', now()->subHours(self::ABANDONED_AFTER_HOURS));
    }

    public function isFinished(): bool
    {
        return $this->review_finished_at !== null;
    }

    public function isAbandoned(): bool
    {
        return ! $this->isFinished()
            && ($this->review_started_at === null || $this->review_started_at->lt(now()->subHours(self::ABANDONED_AFTER_HOURS)));
    }

    /**
     * Wie dringend ein menschliches Review ist: hohes Risiko bei niedriger
     * KI-Prüfbarkeit zuerst (0–10000); null, solange nicht bewertet.
     */
    public function needScore(): ?int
    {
        if ($this->criticality === null || $this->ai_reviewability === null) {
            return null;
        }

        return $this->criticality * (100 - $this->ai_reviewability);
    }
}
