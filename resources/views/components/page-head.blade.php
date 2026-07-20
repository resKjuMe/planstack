@props(['title'])

{{-- Einheitlicher Seitenkopf der Projekt-Unterseiten: H1 (= Tab-Name) links,
     optional Meta + Hilfe-„?" rechts; die Infobox (Slot) klappt darunter auf.
     Nutzt inline display:none statt [x-cloak], damit es überall ohne Flackern
     funktioniert. Der „?"-Button erscheint nur, wenn Hilfe-Inhalt vorhanden ist. --}}
<div x-data="{ help: false }" {{ $attributes->merge(['class' => '']) }}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
        <div class="flex items-center gap-3">
            {{ $meta ?? '' }}
            @if (trim($slot) !== '')
                <button type="button" @click="help = ! help" :aria-expanded="help"
                        class="text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400" title="{{ __('common.show_hide_explanation') }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                </button>
            @endif
        </div>
    </div>
    @if (trim($slot) !== '')
        <div x-show="help" style="display:none"
             class="mt-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
            {{ $slot }}
        </div>
    @endif
</div>
