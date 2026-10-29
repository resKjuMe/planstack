<?php

namespace App\Enums;

/**
 * Was der Fix-Lauf mit einem Befund tun soll — entschieden im Fix-Dialog.
 */
enum FindingFixDecision: string
{
    case FIX = 'FIX';
    case SKIP = 'SKIP';
}
