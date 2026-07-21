@php
    // Literal swatch classes (scanned by Tailwind) for the color-token select.
    $swatch = [
        'gray' => 'bg-gray-500', 'slate' => 'bg-slate-500', 'indigo' => 'bg-indigo-500',
        'sky' => 'bg-sky-500', 'blue' => 'bg-blue-500', 'navy' => 'bg-blue-700',
        'purple' => 'bg-purple-500', 'green' => 'bg-green-500', 'emerald' => 'bg-emerald-500',
        'teal' => 'bg-teal-500', 'rose' => 'bg-rose-500', 'red' => 'bg-red-500',
        'orange' => 'bg-orange-500', 'amber' => 'bg-amber-500',
    ];
    $inputClass = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';
    $effectFields = \App\Support\StatusEffects::ALLOWED_FIELDS;
    // Placeholder glyph (dashed circle) shown on the icon-picker trigger when no
    // icon is chosen. Rendered via Alpine x-html into a static <svg> wrapper.
    $iconPlaceholder = '<circle cx="12" cy="12" r="9" stroke-dasharray="3 3"/>';
    // Gemeinsames Spaltenraster für Kopfzeile, Status-Zeilen und das
    // "Neuer Status"-Formular, damit alle Spalten exakt untereinander fluchten.
    // Letzte Spalte (Aktionen) FEST, nicht auto: sonst variiert ihre Breite je
    // nach Inhalt (Kopf leer / Zeile mit Icons / Anlegen-Button) und die
    // 1fr-Spalten fluchten nicht mehr.
    $grid = 'grid items-center gap-x-3 grid-cols-[1.5rem_11rem_minmax(7rem,1fr)_minmax(7rem,1fr)_4.5rem_5.5rem_4.5rem_9rem_9rem]';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('board_admin.title') }}</h2>
    </x-slot>

    <x-slot name="subheader">
        <x-organization-tabs active="statuses" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="flex items-center justify-between gap-4">
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('board_admin.intro') }}</p>
                <a href="{{ route('organization.statuses.effects.index') }}"
                   class="shrink-0 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('board_admin.automations_link') }}</a>
            </div>

            {{-- ============ Status bearbeiten ============ --}}
            {{-- Alle Zeilen liegen in EINER Sammel-Form mit genau einem Speichern-
                 Button; sie speichert Darstellung, Automationen und (per Drag
                 gesetzte) Reihenfolge gemeinsam. Loeschen laeuft ueber separate,
                 versteckte Forms (via form=… referenziert), da HTML-Forms nicht
                 verschachtelbar sind. --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('board_admin.statuses') }}</h3>

                {{-- Kopfzeile (gleiches Grid wie Zeilen + Neuanlage) --}}
                <div class="{{ $grid }} border-b pb-2 text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    <div></div>
                    <div>{{ __('board_admin.col_key') }}</div>
                    <div>{{ __('board_admin.col_label') }}</div>
                    <div>{{ __('board_admin.col_label_en') }}</div>
                    <div class="text-center">{{ __('board_admin.col_is_column') }}</div>
                    <div class="text-center">{{ __('board_admin.col_expanded') }}</div>
                    <div>{{ __('board_admin.col_wip') }}</div>
                    <div>{{ __('board_admin.col_group') }}</div>
                    <div></div>
                </div>

                <x-input-error :messages="$errors->get('status')" class="mt-2" />

                <form id="statuses-bulk" method="POST" action="{{ route('organization.statuses.update-all') }}">
                    @csrf
                    @method('PUT')

                    <div id="status-sortable">
                        @foreach ($statuses as $status)
                            @php $p = 'statuses['.$status->id.']'; @endphp
                            <div x-data="{ pickerOpen: false, color: '{{ $status->color_token }}', swatch: @js($swatch), iconOpen: false, icon: '{{ $status->icon }}', icons: @js($iconMarkup), placeholder: @js($iconPlaceholder) }"
                                 data-status-row data-status-id="{{ $status->id }}"
                                 class="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <div class="{{ $grid }} py-2">
                                    <span data-drag-handle title="{{ __('board_admin.drag_to_sort') }}"
                                          class="cursor-grab select-none text-center text-gray-300 dark:text-gray-600 hover:text-gray-500">⠿</span>

                                    {{-- Reihenfolge: wird beim Absenden aus der DOM-Reihenfolge gesetzt. --}}
                                    <input type="hidden" name="{{ $p }}[position]" value="{{ $status->position }}" data-position-input>

                                    {{-- Farbpunkt + Icon (Klick öffnet je ein Flyout) + Schlüssel --}}
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <div class="relative shrink-0">
                                            <input type="hidden" name="{{ $p }}[color_token]" x-bind:value="color">
                                            <button type="button" x-on:click="pickerOpen = !pickerOpen" title="{{ __('board_admin.col_color') }}" class="p-1">
                                                <span class="block h-2 w-2 rounded-full" x-bind:class="swatch[color]"></span>
                                            </button>
                                            <div x-show="pickerOpen" x-cloak x-on:click.outside="pickerOpen = false"
                                                 class="absolute z-10 mt-1 grid grid-cols-7 gap-1.5 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg">
                                                @foreach ($colors as $token)
                                                    <button type="button" title="{{ $token }}"
                                                            x-on:click="color = '{{ $token }}'; pickerOpen = false"
                                                            class="h-5 w-5 rounded-full {{ $swatch[$token] }}"
                                                            x-bind:class="color === '{{ $token }}' ? 'ring-2 ring-offset-1 ring-gray-800 dark:ring-gray-200 dark:ring-offset-gray-800' : ''"></button>
                                                @endforeach
                                            </div>
                                        </div>
                                        @include('organization.partials.icon-picker', ['iconName' => $p.'[icon]'])
                                        <span class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $status->key }}</span>
                                    </div>
                                    <input type="text" name="{{ $p }}[label]" value="{{ $status->label }}" required maxlength="255" class="{{ $inputClass }} w-full min-w-0">
                                    <input type="text" name="{{ $p }}[label_en]" value="{{ $status->label_en }}" maxlength="255" class="{{ $inputClass }} w-full min-w-0">
                                    <div class="text-center">
                                        <input type="checkbox" name="{{ $p }}[is_column]" value="1" @checked($status->is_column)
                                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                    </div>
                                    <div class="text-center">
                                        <input type="checkbox" name="{{ $p }}[default_expanded]" value="1" @checked($status->default_expanded)
                                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                    </div>
                                    <input type="number" name="{{ $p }}[wip_limit]" value="{{ $status->wip_limit }}" min="1" placeholder="—" class="{{ $inputClass }} w-full min-w-0">
                                    <select name="{{ $p }}[group_id]" class="{{ $inputClass }} w-full min-w-0">
                                        <option value="">{{ __('board_admin.no_group') }}</option>
                                        @foreach ($groups as $g)
                                            <option value="{{ $g->id }}" @selected($status->group_id === $g->id)>{{ $g->label }}</option>
                                        @endforeach
                                    </select>

                                    <div class="flex items-center justify-start gap-2">
                                        @if ($status->role === null)
                                            {{-- Loeschen: submittet die separate, versteckte Loesch-Form. --}}
                                            <button type="submit" form="delete-status-{{ $status->id }}" title="{{ __('board_admin.delete') }}"
                                                    class="block text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                            </button>
                                        @else
                                            {{-- Platzhalter, damit die Aktionsspalte bei nicht loeschbaren Status gleich breit bleibt --}}
                                            <span class="block h-4 w-4" aria-hidden></span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('board_admin.save') }}
                        </button>
                    </div>
                </form>

                {{-- Versteckte Loesch-Forms (ausserhalb der Sammel-Form). --}}
                @foreach ($statuses as $status)
                    @if ($status->role === null)
                        <form id="delete-status-{{ $status->id }}" method="POST" action="{{ route('organization.statuses.destroy', $status) }}"
                              onsubmit="return confirm('{{ __('board_admin.delete_status_confirm') }}');" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @endforeach

                {{-- Neuer Status: gleiches Spaltenraster wie Kopf/Zeilen --}}
                <form method="POST" action="{{ route('organization.statuses.store') }}"
                      x-data="{ pickerOpen: false, color: 'indigo', swatch: @js($swatch), iconOpen: false, icon: '', icons: @js($iconMarkup), placeholder: @js($iconPlaceholder) }"
                      class="{{ $grid }} border-t-2 border-dashed border-gray-200 dark:border-gray-700 py-3">
                    @csrf
                    <span class="text-center text-lg leading-none text-indigo-500" aria-hidden>＋</span>
                    {{-- Farbpunkt + Icon + Art (kein Schlüssel: wird automatisch vergeben) --}}
                    <div class="flex min-w-0 items-center gap-1.5">
                        <div class="relative shrink-0">
                            <input type="hidden" name="color_token" x-bind:value="color">
                            <button type="button" x-on:click="pickerOpen = !pickerOpen" title="{{ __('board_admin.col_color') }}" class="p-1">
                                <span class="block h-2 w-2 rounded-full" x-bind:class="swatch[color]"></span>
                            </button>
                            <div x-show="pickerOpen" x-cloak x-on:click.outside="pickerOpen = false"
                                 class="absolute z-10 mt-1 grid grid-cols-7 gap-1.5 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg">
                                @foreach ($colors as $token)
                                    <button type="button" title="{{ $token }}"
                                            x-on:click="color = '{{ $token }}'; pickerOpen = false"
                                            class="h-5 w-5 rounded-full {{ $swatch[$token] }}"
                                            x-bind:class="color === '{{ $token }}' ? 'ring-2 ring-offset-1 ring-gray-800 dark:ring-gray-200 dark:ring-offset-gray-800' : ''"></button>
                                @endforeach
                            </div>
                        </div>
                        @include('organization.partials.icon-picker')
                        <select name="kind" title="{{ __('board_admin.kind') }}" class="{{ $inputClass }} w-full min-w-0">
                            @foreach (['active', 'review', 'done', 'exception'] as $k)
                                <option value="{{ $k }}">{{ __('board_admin.kind_'.$k) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="label" required maxlength="255" placeholder="{{ __('board_admin.col_label') }}" class="{{ $inputClass }} w-full min-w-0">
                    <input type="text" name="label_en" maxlength="255" placeholder="{{ __('board_admin.col_label_en') }}" class="{{ $inputClass }} w-full min-w-0">
                    <div class="text-center">
                        <input type="checkbox" name="is_column" value="1" checked title="{{ __('board_admin.col_is_column') }}"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <div class="text-center">
                        <input type="checkbox" name="default_expanded" value="1" title="{{ __('board_admin.col_expanded') }}"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <input type="number" name="wip_limit" min="1" placeholder="—" title="{{ __('board_admin.col_wip') }}" class="{{ $inputClass }} w-full min-w-0">
                    <select name="group_id" title="{{ __('board_admin.col_group') }}" class="{{ $inputClass }} w-full min-w-0">
                        <option value="">{{ __('board_admin.no_group') }}</option>
                        @foreach ($groups as $g)
                            <option value="{{ $g->id }}">{{ $g->label }}</option>
                        @endforeach
                    </select>
                    <div class="flex justify-start">
                        <button class="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                            {{ __('board_admin.create_status') }}
                        </button>
                    </div>
                </form>
                <x-input-error :messages="$errors->get('label')" class="mt-2" />

                <script>
                    (function () {
                        const list = document.getElementById('status-sortable');
                        if (!list) return;
                        const form = document.getElementById('statuses-bulk');
                        let dragEl = null;

                        function rows() {
                            return [...list.querySelectorAll('[data-status-row]')];
                        }
                        function afterElement(y) {
                            return rows()
                                .filter((r) => r !== dragEl)
                                .reduce((closest, child) => {
                                    const box = child.getBoundingClientRect();
                                    const offset = y - box.top - box.height / 2;
                                    return offset < 0 && offset > closest.offset
                                        ? { offset, element: child }
                                        : closest;
                                }, { offset: Number.NEGATIVE_INFINITY }).element;
                        }

                        rows().forEach((row) => {
                            const handle = row.querySelector('[data-drag-handle]');
                            if (handle) {
                                handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
                                handle.addEventListener('mouseup', () => row.removeAttribute('draggable'));
                            }
                            row.addEventListener('dragstart', () => { dragEl = row; row.classList.add('opacity-40'); });
                            row.addEventListener('dragend', () => {
                                row.classList.remove('opacity-40');
                                row.removeAttribute('draggable');
                            });
                        });

                        list.addEventListener('dragover', (e) => {
                            if (!dragEl) return;
                            e.preventDefault();
                            const after = afterElement(e.clientY);
                            if (after == null) list.appendChild(dragEl);
                            else list.insertBefore(dragEl, after);
                        });

                        // Reihenfolge aus dem DOM in die Positions-Felder schreiben (Sammel-Speichern).
                        if (form) {
                            form.addEventListener('submit', () => {
                                rows().forEach((row, i) => {
                                    const input = row.querySelector('[data-position-input]');
                                    if (input) input.value = i;
                                });
                            });
                        }
                    })();
                </script>
            </div>

            {{-- ============ Collapse-Gruppen ============ --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="mb-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('board_admin.groups_title') }}</h3>
                <p class="mb-4 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('board_admin.groups_intro') }}</p>

                @if ($groups->isNotEmpty())
                    <ul class="mb-4 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($groups as $g)
                            <li class="flex items-center justify-between py-2">
                                <span class="text-sm text-gray-800 dark:text-gray-200">
                                    {{ $g->label }}
                                    <span class="ms-2 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $g->key }}</span>
                                </span>
                                <form method="POST" action="{{ route('organization.statuses.groups.destroy', $g) }}"
                                      onsubmit="return confirm('{{ __('board_admin.delete_group') }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">{{ __('board_admin.delete_group') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mb-4 text-sm text-gray-400 dark:text-gray-500">{{ __('board_admin.no_groups') }}</p>
                @endif

                <form method="POST" action="{{ route('organization.statuses.groups.store') }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="group-label" :value="__('board_admin.group_label')" />
                        <input id="group-label" type="text" name="label" required maxlength="255" class="{{ $inputClass }} mt-1 w-64">
                        <x-input-error :messages="$errors->get('label')" class="mt-1" />
                    </div>
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        {{ __('board_admin.add_group') }}
                    </button>
                </form>
            </div>

            {{-- ============ Übergänge ============ --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                <h3 class="mb-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('board_admin.transitions_title') }}</h3>
                <p class="mb-4 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('board_admin.transitions_intro') }}</p>

                <form method="POST" action="{{ route('organization.statuses.transitions') }}">
                    @csrf
                    @method('PUT')
                    <table class="text-sm">
                        <thead>
                            <tr>
                                <th class="p-2 text-left text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('board_admin.transitions_from') }}</th>
                                @foreach ($statuses as $to)
                                    <th class="p-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                        <span class="[writing-mode:vertical-rl]">{{ $to->label }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statuses as $from)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="p-2 font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $from->label }}</td>
                                    @foreach ($statuses as $to)
                                        <td class="p-2 text-center">
                                            @if ($from->id === $to->id)
                                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                            @else
                                                <input type="checkbox" name="transitions[{{ $from->id }}][]" value="{{ $to->id }}"
                                                       @checked(in_array($to->id, $transitions[$from->id] ?? [], true))
                                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-5">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('board_admin.save_transitions') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
