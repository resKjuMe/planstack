<?php

namespace App\Models;

use App\Enums\FindingFixDecision;
use App\Enums\FindingFixOutcome;
use App\Enums\ReviewFindingLevel;
use App\Enums\ReviewFindingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFinding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pull_request_review_id',
        'level',
        'type',
        'requires_po_decision',
        'file',
        'line',
        'title',
        'message',
        'fix_decision',
        'fix_note',
        'fix_outcome',
        'fix_outcome_note',
    ];

    protected function casts(): array
    {
        return [
            'level' => ReviewFindingLevel::class,
            'type' => ReviewFindingType::class,
            'requires_po_decision' => 'boolean',
            'line' => 'integer',
            'fix_decision' => FindingFixDecision::class,
            'fix_outcome' => FindingFixOutcome::class,
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PullRequestReview::class, 'pull_request_review_id');
    }
}
