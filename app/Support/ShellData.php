<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Nutzlast für das React-Grundgerüst (resources/js/shell): Navigations-Links,
 * User-Menü, Labels usw. Wird als Inertia-Shared-Data bei jedem Aufruf frisch
 * berechnet, damit z. B. die aktiven Links pro Navigation stimmen.
 */
final class ShellData
{
    public static function build(): array
    {
        $ciVersion = config('planstack_ci.version');
        $changelogVersion = config('changelog.releases.0.version');
        $user = Auth::user();

        return [
            'hasOrg' => $user?->organization_id !== null,
            'notificationDisplay' => $user?->notification_display ?? 'dropdown',
            'user' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
            ],
            'logoHref' => route('dashboard'),
            'logoutHref' => route('logout'),
            'csrf' => csrf_token(),
            'ciVersion' => $ciVersion,
            'changelogVersion' => $changelogVersion,
            'onChangelog' => request()->routeIs('changelog'),
            'links' => [
                [
                    'label' => __('common.projects'),
                    'href' => route('projects.index'),
                    'active' => request()->routeIs('projects.*'),
                ],
                [
                    'label' => __('common.teams'),
                    'href' => route('teams.index'),
                    'active' => request()->routeIs('teams.*'),
                ],
                [
                    'label' => __('nav.planstack_skill'),
                    'href' => route('skill.setup'),
                    'active' => request()->routeIs('skill.*'),
                    'icon' => 'skill',
                ],
                [
                    'label' => 'v' . $changelogVersion,
                    'href' => route('changelog'),
                    'active' => request()->routeIs('changelog'),
                    'icon' => 'changelog',
                    'mono' => true,
                ],
            ],
            'menu' => [
                [
                    'label' => __('common.organization'),
                    'href' => route('organization.index'),
                    'icon' => 'org',
                ],
                [
                    'label' => __('common.profile'),
                    'href' => route('profile.edit'),
                    'icon' => 'profile',
                ],
                [
                    'label' => __('nav.tampermonkey_script'),
                    'href' => url('/planstack-ci/setup'),
                    'icon' => 'ci',
                    'orgOnly' => true,
                    'badge' => 'v' . $ciVersion,
                ],
            ],
            'labels' => [
                'signOut' => __('nav.sign_out'),
                'newChanges' => __('nav.new_changes'),
                'ciUpdate' => __('common.update_available_for_the_ci_status'),
                'theme' => [
                    'light' => __('nav.theme_light'),
                    'dark' => __('nav.theme_dark'),
                    'system' => __('nav.theme_system'),
                ],
            ],
        ];
    }
}
