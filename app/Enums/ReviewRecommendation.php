<?php

namespace App\Enums;

enum ReviewRecommendation: string
{
    case APPROVE = 'APPROVE';
    case REQUEST_CHANGES = 'REQUEST_CHANGES';

    /**
     * German label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::APPROVE => __('enums.review_approve'),
            self::REQUEST_CHANGES => __('enums.review_request_changes'),
        };
    }
}
