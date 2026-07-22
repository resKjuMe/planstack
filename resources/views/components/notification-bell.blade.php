{{-- Benachrichtigungs-Glocke für den Header (rechts neben dem Dark-Mode-Toggle).
     Verbindet sich – nur auf der Domain planstack.eskju.net – über den globalen
     Alpine-Store $store.notifications (siehe resources/js/app.js) mit dem
     WebSocket-Server. Eingehende Nachrichten erhöhen den Zähler; er wird als
     Pill im Logo-Rot (#FF4B3E) angezeigt. Ein Klick setzt den Zähler zurück.
     Liegt innerhalb des <nav x-data>-Scopes, greift daher auf den Store zu. --}}
@php
    $labelBase = __('nav.notifications');
    $labelNew = __('nav.new_messages');
@endphp
<button type="button"
        @click="$store.notifications.reset()"
        :title="$store.notifications.count > 0
            ? $store.notifications.count + ' {{ $labelNew }}'
            : '{{ $labelBase }}'"
        :aria-label="$store.notifications.count > 0
            ? $store.notifications.count + ' {{ $labelNew }}'
            : '{{ $labelBase }}'"
        {{ $attributes->merge(['class' => 'relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition ease-in-out duration-150 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700']) }}>
    {{-- Glocke --}}
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10.268 21a2 2 0 0 0 3.464 0"/>
        <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>
    </svg>
    {{-- Pill mit der Anzahl neuer Nachrichten, im Logo-Rot. --}}
    <span x-cloak
          x-show="$store.notifications.count > 0"
          x-text="$store.notifications.count > 99 ? '99+' : $store.notifications.count"
          class="absolute -top-0.5 -end-0.5 inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-semibold leading-none text-white ring-2 ring-white dark:ring-gray-800"
          style="background-color:#FF4B3E">
    </span>
</button>
