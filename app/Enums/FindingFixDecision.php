<?php

namespace App\Enums;

/**
 * Was der Fix-Lauf mit einem Befund tun soll — entschieden im Fix-Dialog.
 */
enum FindingFixDecision: string
{
    case FIX = 'FIX';
    case SUGGESTED = 'SUGGESTED';
    case SKIP = 'SKIP';

    /**
     * Ohne Anmerkung kein Fix eines PO-Befunds — außer wie vorgeschlagen.
     */
    public function needsPoNote(): bool
    {
        return $this !== self::SUGGESTED;
    }
}
