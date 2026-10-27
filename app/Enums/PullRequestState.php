<?php

namespace App\Enums;

enum PullRequestState: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
    case MERGED = 'MERGED';
}
