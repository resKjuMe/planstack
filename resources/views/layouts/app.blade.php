<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Pusher-Konfiguration für die Header-Glocke (nur für eingeloggte
             Nutzer mit Organisation; der Key ist öffentlich). Ohne diese Tags
             verbindet sich die Glocke nicht. --}}
        @auth
            @if (Auth::user()->organization_id && config('broadcasting.connections.pusher.key'))
                <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
                <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster') }}">
                <meta name="organization-id" content="{{ Auth::user()->organization_id }}">
            @endif
        @endauth

        @include('partials.theme-init')

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon-180.png') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Alpine: Elemente mit x-cloak bis zur Initialisierung verbergen
             (die Glocke im React-Grundgerüst bleibt Alpine-gesteuert). --}}
        <style>[x-cloak]{display:none !important;}</style>
    </head>
    <body class="font-sans antialiased">
        @php
            // Nutzlast für das React-Grundgerüst (resources/js/shell). Alle
            // request-abhängigen Werte (Auth, aktive Route, Config, Übersetzungen)
            // werden hier server-seitig berechnet und als JSON an die Shell übergeben.
            $ciVersion = config('planstack_ci.version');
            $changelogVersion = config('changelog.releases.0.version');
            $shellUser = Auth::user();
            $shell = [
                'hasOrg' => $shellUser?->organization_id !== null,
                'user' => [
                    'name' => $shellUser?->name ?? '',
                    'email' => $shellUser?->email ?? '',
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
        @endphp

        {{-- Mount-Punkt des React-Grundgerüsts (Wrapper, Header, Navi, Subnavi). --}}
        <div id="app-shell"></div>
        <script>
            window.__PLANSTACK_SHELL__ = @json($shell);
        </script>

        {{-- Server-gerenderte, seitenspezifische Blade-Inhalte. Die Shell hängt
             diese Knoten nach dem Mount an ihren Platz (echter DOM-Umzug, damit
             Alpine, Inline-Skripte und verschachtelte Islands weiterlaufen).
             Bis dahin unsichtbar, um ein Aufblitzen ohne Rahmen zu vermeiden. --}}
        <div id="shell-nodes" hidden>
            {{-- Benachrichtigungs-Glocke (Alpine/Pusher) – Desktop und Mobile. --}}
            <div id="shell-bell" class="relative"><x-notification-bell /></div>
            <div id="shell-bell-mobile" class="relative"><x-notification-bell /></div>

            @isset($header)
                <div id="shell-header">{{ $header }}</div>
            @endisset

            @isset($subheader)
                <div id="shell-subheader">{{ $subheader }}</div>
            @endisset

            <div id="shell-main">{{ $slot }}</div>
        </div>

        @vite('resources/js/shell/index.jsx')
    </body>
</html>
