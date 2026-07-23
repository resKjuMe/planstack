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
             (die Detailseite nutzt x-app-layout, nicht die status-shell). --}}
        <style>[x-cloak]{display:none !important;}</style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow dark:bg-gray-800 dark:shadow-black/30">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Optional sub-navigation directly under the page heading (e.g. the
                 project tabs), spanning the full width in its own light band. -->
            @isset($subheader)
                <div class="bg-gray-50 border-b border-gray-200 dark:bg-gray-800/50 dark:border-gray-700">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {{ $subheader }}
                    </div>
                </div>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
