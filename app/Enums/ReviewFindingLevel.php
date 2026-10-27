<?php

namespace App\Enums;

enum ReviewFindingLevel: string
{
    case CRITICAL = 'CRITICAL';
    case IMPORTANT = 'IMPORTANT';
    case HINT = 'HINT';
}
