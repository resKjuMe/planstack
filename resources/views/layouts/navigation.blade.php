@php
    $ciVersion = config('planstack_ci.version');
    // Ohne Organisation nur Profil/Organisation/Logout — die eigentliche App
    // (Projekte, Teams, Skill …) ist gesperrt (siehe EnsureUserHasOrganization).
    $hasOrg = Auth::user()?->organization_id !== null;
@endphp
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-8 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                @if ($hasOrg)
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">
                        {{ __('common.projects') }}
                    </x-nav-link>
                    <x-nav-link :href="route('teams.index')" :active="request()->routeIs('teams.*')">
                        {{ __('common.teams') }}
                    </x-nav-link>
                    <x-nav-link :href="route('skill.setup')" :active="request()->routeIs('skill.*')">
                        <svg class="me-1 inline h-4 w-4 text-gray-400 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                            <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                        </svg>
                        {{ __('nav.planstack_skill') }}
                    </x-nav-link>
                    <x-nav-link :href="route('changelog')" :active="request()->routeIs('changelog')" class="font-mono">
                        <svg class="js-changelog-new me-1 inline h-4 w-4 text-indigo-500" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" title="{{ __('nav.new_changes') }}">
                            <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
                        </svg>
                        v{{ config('changelog.releases.0.version') }}
                    </x-nav-link>
                </div>
                @endif
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-theme-toggle class="me-2" />

                <x-dropdown align="right" width="w-56">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                            <span class="js-psci-update me-1 align-middle text-indigo-600" style="display:none" title="{{ __('common.update_available_for_the_ci_status') }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16V8"/><path d="m8.5 11.5 3.5-3.5 3.5 3.5"/></svg>
                            </span>
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('organization.index')" class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                            {{ __('common.organization') }}
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('profile.edit')" class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            {{ __('common.profile') }}
                        </x-dropdown-link>

                        {{-- Einrichtungs-/Downloadseite der CI-Status-Anzeige --}}
                        @if ($hasOrg)
                        <x-dropdown-link :href="url('/planstack-ci/setup')" class="flex items-center gap-2 whitespace-nowrap">
                            <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                            {{ __('nav.tampermonkey_script') }}
                            <span class="js-psci-update ms-2 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white align-middle" style="display:none">v{{ $ciVersion }}</span>
                        </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')" class="flex items-center gap-2"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                                {{ __('nav.sign_out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700 dark:focus:text-gray-300">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        @if ($hasOrg)
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">
                {{ __('common.projects') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('teams.index')" :active="request()->routeIs('teams.*')">
                {{ __('common.teams') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('skill.setup')" :active="request()->routeIs('skill.*')">
                {{ __('nav.planstack_skill') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('changelog')" :active="request()->routeIs('changelog')">
                v{{ config('changelog.releases.0.version') }}
            </x-responsive-nav-link>
        </div>
        @endif

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
            <div class="px-4 flex items-center justify-between">
                <div>
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                </div>
                <x-theme-toggle />
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('organization.index')">
                    {{ __('common.organization') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('common.profile') }}
                </x-responsive-nav-link>

                @if ($hasOrg)
                {{-- Einrichtungs-/Downloadseite der CI-Status-Anzeige --}}
                <x-responsive-nav-link :href="url('/planstack-ci/setup')">
                    {{ __('nav.tampermonkey_script') }}
                    <span class="js-psci-update ms-2 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white align-middle" style="display:none">v{{ $ciVersion }}</span>
                </x-responsive-nav-link>

                {{-- Download des allgemeinen Planstack-Skills für Claude Code --}}
                <x-responsive-nav-link :href="route('skill.download')">
                    {{ __('nav.planstack_skill') }}
                </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('nav.sign_out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

{{-- Update-Hinweis (Icon vor Username + „new"-Badge am Menüpunkt): sichtbar, wenn
     das Userscript läuft (Marker data-planstack-ci) UND eine ältere Version als die
     aktuelle (config/planstack_ci.version) installiert ist. --}}
<script>
(function () {
    var current = @json($ciVersion);
    function cmp(a, b) {
        var pa = String(a || '0').split('.').map(Number), pb = String(b || '0').split('.').map(Number);
        for (var i = 0; i < 3; i++) { var d = (pa[i] || 0) - (pb[i] || 0); if (d) return d < 0 ? -1 : 1; }
        return 0;
    }
    function refresh() {
        var installed = document.documentElement.getAttribute('data-planstack-ci');
        var show = !!installed && cmp(installed, current) < 0;
        document.querySelectorAll('.js-psci-update').forEach(function (el) {
            el.style.display = show ? '' : 'none';
        });
    }
    document.addEventListener('planstack-ci-ready', refresh);
    refresh();
    [400, 1200, 2500].forEach(function (ms) { setTimeout(refresh, ms); });

    // Changelog-„ungelesen"-Indikator: zeigt ein Update-Icon am Changelog-Menüpunkt,
    // wenn die aktuelle Version neuer ist als die zuletzt gesehene (localStorage).
    // Beim Besuch der Changelog-Seite wird die gesehene Version aktualisiert; ist noch
    // nichts gespeichert (Erstbesuch), wird NICHT hervorgehoben.
    try {
        var clLatest = @json(config('changelog.releases.0.version'));
        var clKey = 'changelog-seen-version';
        var onChangelog = @json(request()->routeIs('changelog'));
        // Nur LESEN: das Aktualisieren der gesehenen Version macht die Changelog-Seite
        // selbst (erst nachdem sie die neuen Einträge hervorgehoben hat).
        var seen = localStorage.getItem(clKey);
        var showCl = !onChangelog && seen && cmp(seen, clLatest) < 0;
        document.querySelectorAll('.js-changelog-new').forEach(function (el) {
            el.style.display = showCl ? '' : 'none';
        });
    } catch (e) { /* localStorage evtl. nicht verfügbar */ }
})();
</script>
