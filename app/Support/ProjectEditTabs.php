<?php

namespace App\Support;

use App\Models\Project;

/**
 * Sub-Navigation der Projekt-Einstellungen (Allgemein / Phasen / Claude / Zugriff)
 * als Prop für die React-Settings-Seiten. Pendant zur früheren Blade-Komponente
 * project-edit-tabs.
 */
class ProjectEditTabs
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(Project $project, string $active): array
    {
        return collect([
            'general' => ['label' => __('common.general'), 'route' => 'projects.edit'],
            'phases' => ['label' => __('common.phases'), 'route' => 'projects.phases.index'],
            'claude' => ['label' => 'Claude', 'route' => 'projects.claude.edit'],
            'access' => ['label' => __('common.access'), 'route' => 'projects.access'],
        ])->map(fn (array $t, string $key) => [
            'key' => $key,
            'label' => $t['label'],
            'href' => route($t['route'], $project),
            'active' => $key === $active,
        ])->values()->all();
    }
}
