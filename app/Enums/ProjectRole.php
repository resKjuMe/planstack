<?php

namespace App\Enums;

enum ProjectRole: string
{
    case WORKER = 'WORKER';
    case ARCHITECT = 'ARCHITECT';
    case ADMIN = 'ADMIN';

    /**
     * Deutscher Anzeigename der Rolle.
     */
    public function label(): string
    {
        return match ($this) {
            self::WORKER => 'Mitarbeiter',
            self::ARCHITECT => 'Architekt',
            self::ADMIN => 'Administrator',
        };
    }
}
