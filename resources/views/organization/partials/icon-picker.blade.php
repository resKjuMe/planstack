{{-- Icon-Picker: zeigt das gewählte Icon, Klick öffnet ein Flyout mit allen
     Icons (analog zum Farb-Picker). Erwartet Alpine-State aus dem umgebenden
     x-data: `icon` (key), `iconOpen`, `icons` (key=>inner-svg), `placeholder`.
     Nutzt die Blade-Variablen $iconKeys/$iconMarkup. --}}
<div class="relative shrink-0">
    <input type="hidden" name="icon" x-bind:value="icon">
    <button type="button" x-on:click="iconOpen = !iconOpen" title="{{ __('board_admin.col_icon') }}"
            class="flex items-center rounded p-1 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="h-4 w-4" aria-hidden="true" x-html="icons[icon] || placeholder"></svg>
    </button>
    <div x-show="iconOpen" x-cloak x-on:click.outside="iconOpen = false"
         class="absolute z-10 mt-1 grid w-max grid-cols-6 gap-1 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg">
        <button type="button" x-on:click="icon = ''; iconOpen = false" title="{{ __('board_admin.no_icon') }}"
                class="flex h-7 w-7 items-center justify-center rounded text-xs text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                x-bind:class="icon === '' ? 'ring-2 ring-gray-800 dark:ring-gray-200' : ''">—</button>
        @foreach ($iconKeys as $ik)
            <button type="button" x-on:click="icon = '{{ $ik }}'; iconOpen = false" title="{{ $ik }}"
                    class="flex h-7 w-7 items-center justify-center rounded text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    x-bind:class="icon === '{{ $ik }}' ? 'ring-2 ring-gray-800 dark:ring-gray-200' : ''">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="h-4 w-4" aria-hidden="true">{!! $iconMarkup[$ik] !!}</svg>
            </button>
        @endforeach
    </div>
</div>
