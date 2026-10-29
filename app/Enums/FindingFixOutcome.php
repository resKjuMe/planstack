<?php

namespace App\Enums;

/**
 * Was der Fix-Lauf mit einem Befund getan hat.
 */
enum FindingFixOutcome: string
{
    case FIXED = 'FIXED';
    case SKIPPED = 'SKIPPED';
}
