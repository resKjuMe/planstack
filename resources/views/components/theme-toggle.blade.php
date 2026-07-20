{{-- Dark-Mode-Umschalter für den Header. Zyklus: hell → dunkel → System.
     Liegt innerhalb des <nav x-data>-Scopes, greift daher auf $store.theme zu.
     Das passende Icon wird per x-show anhand des aktuellen Modus gezeigt;
     x-cloak verhindert, dass vor der Alpine-Initialisierung alle drei blitzen. --}}
@php
    $labels = [
        'light' => __('nav.theme_light'),
        'dark' => __('nav.theme_dark'),
        'system' => __('nav.theme_system'),
    ];
    $labelMap = "{ light: '{$labels['light']}', dark: '{$labels['dark']}', system: '{$labels['system']}' }";
@endphp
<button type="button"
        @click="$store.theme.cycle()"
        :title="{{ $labelMap }}[$store.theme.mode]"
        :aria-label="{{ $labelMap }}[$store.theme.mode]"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition ease-in-out duration-150 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700']) }}>
    {{-- Sonne = hell --}}
    <svg x-show="$store.theme.mode === 'light'" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
    </svg>
    {{-- Mond = dunkel --}}
    <svg x-show="$store.theme.mode === 'dark'" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
    </svg>
    {{-- Monitor = System --}}
    <svg x-show="$store.theme.mode === 'system'" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>
    </svg>
</button>
