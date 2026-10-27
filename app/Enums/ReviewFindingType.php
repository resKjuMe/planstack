<?php

namespace App\Enums;

/**
 * INLINE hängt an einer Diff-Zeile (file + line), SUMMARY gehört in den
 * Review-Body (allgemein oder ausserhalb des Diffs).
 */
enum ReviewFindingType: string
{
    case SUMMARY = 'SUMMARY';
    case INLINE = 'INLINE';
}
