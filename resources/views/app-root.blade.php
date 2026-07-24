<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Pusher-Konfiguration für die Header-Glocke (nur eingeloggte Nutzer
             mit Organisation; der Key ist öffentlich). --}}
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

        <!-- Scripts: Alpine (Glocke/Theme-Init) + Inertia/React-Grundgerüst -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.jsx'])

        {{-- Alpine: x-cloak bis zur Initialisierung verbergen (die Glocke bleibt
             Alpine-gesteuert). --}}
        <style>[x-cloak]{display:none !important;}</style>

        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        {{-- Persistenter, Alpine-gesteuerter Teil (Benachrichtigungs-Glocke,
             Desktop + Mobile). Liegt außerhalb des Inertia-Mounts, damit er über
             SPA-Navigationen hinweg erhalten bleibt; das React-Grundgerüst hängt
             die Knoten einmalig in die Navi (siehe Relocate). --}}
        @php
            // Lokalisierte Tabellen für die Nachrichtenliste (Event-Wert →
            // Bezeichnung, Status-Icon-Palette). Single Source of Truth für Flyout
            // und Sidebar; von den Alpine-Helfern (notificationsView) gelesen.
            $eventLabels = [];
            foreach (\App\Enums\TaskEvent::cases() as $taskEvent) {
                $eventLabels[$taskEvent->value] = $taskEvent->label();
            }
        @endphp
        <script id="notifications-meta" type="application/json">@json(['eventLabels' => $eventLabels, 'statusIcons' => \App\Support\StatusIcons::all()])</script>

        <div id="shell-nodes" hidden>
            <div id="shell-bell" class="relative"><x-notification-bell /></div>
            <div id="shell-bell-mobile" class="relative"><x-notification-bell /></div>
            @auth
                @if (Auth::user()->organization_id && Auth::user()->notification_display === 'sidebar')
                    <div id="shell-sidebar" class="h-full"><x-notification-sidebar /></div>
                @endif
            @endauth
        </div>

        @inertia
    </body>
</html>
