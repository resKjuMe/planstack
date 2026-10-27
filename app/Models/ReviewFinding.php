<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'level' => ReviewFindingLevel::class,
            'type' => ReviewFindingType::class,
            'requires_po_decision' => 'boolean',
            'line' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PullRequestReview::class, 'pull_request_review_id');
    }
}
