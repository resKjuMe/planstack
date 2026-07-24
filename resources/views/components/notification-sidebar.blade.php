{{-- Dauerhaft ausgeklappte Benachrichtigungs-Seitenleiste. Alternative zur
     klassischen Glocke im Header — der Nutzer wählt die Darstellung im Profil
     (users.notification_display). Wird vom React-Grundgerüst per <Relocate>
     nur im Sidebar-Modus (und nur ab lg) in eine eigene Spalte gehängt (siehe
     shell/AppShell.jsx). Nutzt dieselben Darstellungs-Helfer wie das Flyout
     (Alpine-Component `notificationsView`) und dieselbe Datenquelle
     $store.notifications. --}}
<div x-data="notificationsView('sidebar')"
     class="flex h-full flex-col">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.268 21a2 2 0 0 0 3.464 0"/>
                <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>
            </svg>
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('nav.notifications') }}</span>
            {{-- Offline-Hinweis: „✕" nur bei tatsächlich fehlgeschlagener Verbindung. --}}
            <span x-cloak
                  x-show="$store.notifications.enabled && $store.notifications.failed"
                  :title="'{{ __('nav.not_connected') }}'"
                  class="inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-semibold leading-none text-white"
                  style="background-color:#FF4B3E">✕</span>
        </div>
        <button type="button"
                @click="$store.notifications.clear()"
                x-show="$store.notifications.messages.length > 0"
                class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            {{ __('nav.clear') }}
        </button>
    </div>

    <div class="flex-1 overflow-auto p-2">
        <x-notification-list />
    </div>
</div>
