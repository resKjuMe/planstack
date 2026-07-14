<?php

namespace App\Enums;

enum ProjectRole: string
{
    case WORKER = 'WORKER';
    case ARCHITECT = 'ARCHITECT';
    case ADMIN = 'ADMIN';
}
