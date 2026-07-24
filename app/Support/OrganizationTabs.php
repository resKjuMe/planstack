<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Subnavigation der Organisations-Seiten (React-Pendant zu
 * components/organization-tabs.blade.php). Die Status-/Event-/Custom-Field-Tabs
 * sind nur für Gründer sichtbar (die Seiten selbst sind ebenfalls
 * gründer-geschützt). Liefert serverseitig gerenderte Tabs (key/label/href/active).
 *
 * @return array<int, array{key: string, label: string, href: string, active: bool}>
 */
final class OrganizationTabs
{
    public static function for(string $active): array
    {
        $user = Auth::user();
        $organization = $user?->organization;
        $isOwner = $organization && $organization->isOwner($user);

        $tabs = [
            ['key' => 'organization', 'label' => __('common.organization'), 'href' => route('organization.index')],
        ];

        if ($isOwner) {
            $tabs[] = ['key' => 'statuses', 'label' => __('board_admin.statuses'), 'href' => route('organization.statuses.index')];
            $tabs[] = ['key' => 'events', 'label' => __('events.nav'), 'href' => route('organization.events.index')];
            $tabs[] = ['key' => 'custom-fields', 'label' => __('custom_fields.nav'), 'href' => route('organization.custom-fields.index')];
        }

        return array_map(fn ($tab) => [...$tab, 'active' => $tab['key'] === $active], $tabs);
    }
}
