<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

/**
 * Welche Projekte darf dieser Nutzer sehen? — die eine Antwort für alle
 * projektübergreifenden Auswertungen (persönliche Statistik, Aktivitäts-Heatmap).
 *
 * Regel wie in der Projekt-Policy: der Organisations-Owner sieht alle Projekte
 * seiner Organisation, jeder andere die selbst angelegten plus die seiner Teams.
 * Bewusst an EINER Stelle, damit zwei Auswertungen derselben Person nicht
 * unterschiedlich viel zeigen.
 */
final class VisibleProjects
{
    /**
     * @return array<int, int>
     */
    public static function idsFor(User $user): array
    {
        if ($user->organization_id === null) {
            return [];
        }

        $isOrgOwner = $user->organization?->isOwner($user) === true;

        return Project::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $isOrgOwner, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('created_by_id', $user->id)
                ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $user->id))))
            ->pluck('id')
            ->all();
    }
}
