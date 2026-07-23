{{-- Benachrichtigungs-Glocke für den Header (rechts neben dem Dark-Mode-Toggle).
     Verbindet sich – nur auf der Domain planstack.eskju.net – über den globalen
     Alpine-Store $store.notifications (siehe resources/js/app.js) mit dem
     WebSocket-Server. Eingehende Nachrichten erhöhen den Zähler (Pill im
     Logo-Rot #FF4B3E). Ein Klick öffnet ein Flyout mit den letzten Nachrichten
     als JSON und markiert sie als gelesen. --}}
@php
    $labelBase = __('nav.notifications');
    $labelNew = __('nav.new_messages');
    $labelOffline = __('nav.not_connected');

    // Übersetzungs-Tabellen für die lesbare Darstellung (in der aktuellen
    // Sprache des Betrachters gerendert): Event-Wert → Bezeichnung und die
    // Status-Icon-Palette (Key → Inneres SVG-Markup) als Single Source of Truth.
    $eventLabels = [];
    foreach (\App\Enums\TaskEvent::cases() as $taskEvent) {
        $eventLabels[$taskEvent->value] = $taskEvent->label();
    }
    $statusIcons = \App\Support\StatusIcons::all();
@endphp
<div x-data="{
        eventLabels: @js($eventLabels),
        statusIcons: @js($statusIcons),
        structured(d) { return d && typeof d === 'object' && typeof d.event !== 'undefined'; },
        eventLabel(d) { return this.eventLabels[d.event] ?? d.event; },
        statusIcon(d) { return d.status_changed && d.status_icon ? (this.statusIcons[d.status_icon] ?? null) : null; },
        nowTick: 0,
        when(iso) { try { return new Date(iso).toLocaleString(); } catch (e) { return iso; } },
        relative(iso) {
            // nowTick lesen, damit Alpine diese Ausgabe an den Sekunden-Tick
            // koppelt und die relative Zeit im offenen Flyout live mitzählt.
            void this.nowTick;
            try {
                const t = new Date(iso).getTime();
                if (isNaN(t)) return iso;
                const s = Math.max(0, Math.round((Date.now() - t) / 1000));
                const de = (document.documentElement.lang || 'de').toLowerCase().startsWith('de');
                if (s < 5) return de ? 'gerade eben' : 'just now';
                if (s < 60) return de ? ('vor ' + s + 's') : (s + 's ago');
                const m = Math.floor(s / 60);
                if (m < 60) return de ? ('vor ' + m + 'min') : (m + 'min ago');
                const h = Math.floor(m / 60);
                if (h < 24) return de ? ('vor ' + h + 'h') : (h + 'h ago');
                const d = Math.floor(h / 24);
                return de ? ('vor ' + d + 'd') : (d + 'd ago');
            } catch (e) { return iso; }
        },
        raw(d) { return typeof d === 'string' ? d : JSON.stringify(d, null, 2); },
     }"
     x-init="setInterval(() => { if ($store.notifications.open) nowTick++ }, 1000)"
     @keydown.escape.window="$store.notifications.open = false"
     @click.outside="$store.notifications.open = false"
     {{ $attributes->merge(['class' => 'relative']) }}>
    <button type="button"
            @click="$store.notifications.toggle()"
            :title="($store.notifications.enabled && $store.notifications.failed)
                ? '{{ $labelOffline }}'
                : ($store.notifications.count > 0
                    ? $store.notifications.count + ' {{ $labelNew }}'
                    : '{{ $labelBase }}')"
            :aria-label="($store.notifications.enabled && $store.notifications.failed)
                ? '{{ $labelOffline }}'
                : ($store.notifications.count > 0
                    ? $store.notifications.count + ' {{ $labelNew }}'
                    : '{{ $labelBase }}')"
            class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition ease-in-out duration-150 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700">
        {{-- Glocke --}}
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.268 21a2 2 0 0 0 3.464 0"/>
            <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>
        </svg>
        {{-- Pill im Logo-Rot: bei fehlgeschlagener Verbindung ein „✕" (NICHT
             während des initialen Verbindens), sonst die Anzahl neuer
             Nachrichten (nur wenn > 0). Ohne aktivierte Glocke bleibt sie leer. --}}
        <span x-cloak
              x-show="($store.notifications.enabled && $store.notifications.failed) || $store.notifications.count > 0"
              x-text="($store.notifications.enabled && $store.notifications.failed) ? '✕' : ($store.notifications.count > 99 ? '99+' : $store.notifications.count)"
              class="absolute -top-0.5 -end-0.5 inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-semibold leading-none text-white ring-2 ring-white dark:ring-gray-800"
              style="background-color:#FF4B3E">
        </span>
    </button>

    {{-- Flyout: letzte Socket-Nachrichten als JSON --}}
    <div x-cloak
         x-show="$store.notifications.open"
         x-transition
         class="absolute end-0 mt-2 w-96 max-w-[calc(100vw-2rem)] origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 z-50 dark:bg-gray-800 dark:ring-white/10">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $labelBase }}</span>
            <button type="button"
                    @click="$store.notifications.clear()"
                    x-show="$store.notifications.messages.length > 0"
                    class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                {{ __('nav.clear') }}
            </button>
        </div>

        <div class="max-h-96 overflow-auto p-2">
            <template x-if="$store.notifications.messages.length === 0">
                <p class="px-2 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('nav.no_messages') }}</p>
            </template>
            <ul class="space-y-1">
                <template x-for="(msg, i) in $store.notifications.messages" :key="i">
                    <li class="rounded px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        {{-- Lesbare Darstellung: [Status-Icon bei Statuswechsel] Projekt › Task: Event --}}
                        <template x-if="structured(msg.data)">
                            <div class="flex items-start gap-2">
                                <div class="flex min-w-0 flex-1 items-start gap-1.5">
                                    {{-- Icon-Spalte immer reservieren, damit der Text ohne
                                         Statuswechsel identisch eingerückt bleibt. --}}
                                    <svg x-show="statusIcon(msg.data)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                         class="mt-0.5 h-4 w-4 flex-none text-gray-500 dark:text-gray-400" aria-hidden="true" x-html="statusIcon(msg.data)"></svg>
                                    <span x-show="!statusIcon(msg.data)" class="mt-0.5 h-4 w-4 flex-none" aria-hidden="true"></span>
                                    <div class="min-w-0 text-sm leading-snug text-gray-800 dark:text-gray-200">
                                        <template x-if="msg.data.project_name">
                                            <span class="text-gray-500 dark:text-gray-400"><span x-text="msg.data.project_name"></span> <span class="text-gray-300 dark:text-gray-600">›</span> </span>
                                        </template>
                                        <span class="font-medium" x-text="msg.data.task_name ?? ('#' + msg.data.task_id)"></span><span class="text-gray-500 dark:text-gray-400">:</span>
                                        <span x-text="' ' + eventLabel(msg.data)"></span>
                                    </div>
                                </div>
                                <div class="mt-0.5 flex-none whitespace-nowrap text-[10px] text-gray-400 dark:text-gray-500" x-text="relative(msg.at)" :title="when(msg.at)"></div>
                            </div>
                        </template>
                        {{-- Fallback: unbekannte/rohe Nutzlast weiterhin als JSON --}}
                        <template x-if="!structured(msg.data)">
                            <div>
                                <div class="mb-0.5 flex justify-end text-[10px] text-gray-400 dark:text-gray-500" x-text="relative(msg.at)" :title="when(msg.at)"></div>
                                <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-xs leading-snug text-gray-800 dark:bg-gray-900 dark:text-gray-200" x-text="raw(msg.data)"></pre>
                            </div>
                        </template>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
