<?php

namespace App\Models;

use App\Enums\ReviewRecommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PullRequestReview extends Model
{
    protected $fillable = [
        'pull_request_id',
        'reviewer_id',
        'decision',
        'findings',
        'findings_critical',
        'findings_important',
        'findings_hint',
        'criticality',
        'criticality_reason',
        'ai_reviewability',
        'ai_reviewability_reason',
        'summary',
        'commit_sha',
        'strictness',
        'thoroughness',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewRecommendation::class,
            'findings' => 'integer',
            'findings_critical' => 'integer',
            'findings_important' => 'integer',
            'findings_hint' => 'integer',
            'criticality' => 'integer',
            'ai_reviewability' => 'integer',
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

    /**
     * Wie dringend ein menschliches Review ist: hohes Risiko bei niedriger
     * KI-Prüfbarkeit zuerst (0–10000).
     */
    public function needScore(): int
    {
        return $this->criticality * (100 - $this->ai_reviewability);
    }
}
