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
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('board_admin.title') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="flex items-center justify-between">
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('board_admin.intro') }}</p>
                <a href="{{ route('organization.index') }}"
                   class="shrink-0 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('board_admin.back_to_organization') }}</a>
            </div>

            {{-- ============ Status bearbeiten ============ --}}
            {{-- Jede Zeile ist ein eigenes <form> (div/flex statt Tabelle, damit
                 das Formular gültiges HTML bleibt und pro Zeile speicherbar ist). --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('board_admin.statuses') }}</h3>

                <div class="min-w-[72rem] space-y-2">
                    {{-- Kopfzeile --}}
                    <div class="flex items-center gap-3 border-b pb-2 text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <div class="w-28">{{ __('board_admin.col_key') }}</div>
                        <div class="w-28">{{ __('board_admin.col_role') }}</div>
                        <div class="w-36">{{ __('board_admin.col_label') }}</div>
                        <div class="w-36">{{ __('board_admin.col_label_en') }}</div>
                        <div class="w-40">{{ __('board_admin.col_color') }}</div>
                        <div class="w-16">{{ __('board_admin.col_position') }}</div>
                        <div class="w-14 text-center">{{ __('board_admin.col_is_column') }}</div>
                        <div class="w-16 text-center">{{ __('board_admin.col_expanded') }}</div>
                        <div class="w-16">{{ __('board_admin.col_wip') }}</div>
                        <div class="w-32">{{ __('board_admin.col_group') }}</div>
                        <div class="flex-1"></div>
                    </div>

                    <x-input-error :messages="$errors->get('status')" class="mb-2" />

                    @foreach ($statuses as $status)
                        {{-- Zeile = Update-Form; Löschen-Form (nur Custom) und der
                             Automationen-Editor sind gleichrangige Geschwister
                             (gültiges HTML). Alpine hält die Effekt-Zeilen. --}}
                        <div x-data="{ openFx: false, rows: @js($status->on_enter_effects ?? []) }"
                             class="border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div class="flex items-center gap-2 py-2">
                            <form method="POST" action="{{ route('organization.statuses.update', $status) }}"
                                  class="flex flex-1 items-center gap-3">
                                @csrf
                                @method('PATCH')
                                <div class="w-28 font-mono text-xs text-gray-500 dark:text-gray-400 truncate">{{ $status->key }}</div>
                                <div class="w-28 text-xs text-gray-500 dark:text-gray-400">
                                    <div>
                                        {{ $status->role ?? '—' }}
                                        @if ($status->role === null)
                                            <span class="rounded bg-indigo-100 dark:bg-indigo-900/40 px-1 text-indigo-700 dark:text-indigo-300">{{ __('board_admin.custom_badge') }}</span>
                                        @endif
                                    </div>
                                    <span class="rounded bg-gray-100 dark:bg-gray-700 px-1 py-0.5">{{ __('board_admin.kind_'.$status->kind) }}</span>
                                </div>
                                <input type="text" name="label" value="{{ $status->label }}" required maxlength="255" class="{{ $inputClass }} w-36">
                                <input type="text" name="label_en" value="{{ $status->label_en }}" maxlength="255" class="{{ $inputClass }} w-36">
                                <div class="flex w-40 items-center gap-2">
                                    <span class="h-3 w-3 shrink-0 rounded-full {{ $swatch[$status->color_token] ?? 'bg-gray-400' }}"></span>
                                    <select name="color_token" class="{{ $inputClass }} flex-1">
                                        @foreach ($colors as $token)
                                            <option value="{{ $token }}" @selected($status->color_token === $token)>{{ $token }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="number" name="position" value="{{ $status->position }}" min="0" class="{{ $inputClass }} w-16">
                                <div class="w-14 text-center">
                                    <input type="checkbox" name="is_column" value="1" @checked($status->is_column)
                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                </div>
                                <div class="w-16 text-center">
                                    <input type="checkbox" name="default_expanded" value="1" @checked($status->default_expanded)
                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                </div>
                                <input type="number" name="wip_limit" value="{{ $status->wip_limit }}" min="1" placeholder="—" class="{{ $inputClass }} w-16">
                                <select name="group_id" class="{{ $inputClass }} w-32">
                                    <option value="">{{ __('board_admin.no_group') }}</option>
                                    @foreach ($groups as $g)
                                        <option value="{{ $g->id }}" @selected($status->group_id === $g->id)>{{ $g->label }}</option>
                                    @endforeach
                                </select>
                                <div class="flex-1 text-right">
                                    <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                        {{ __('board_admin.save') }}
                                    </button>
                                </div>
                            </form>
                            <div class="flex w-28 items-center justify-end gap-3">
                                <button type="button" x-on:click="openFx = !openFx"
                                        class="text-xs text-gray-500 dark:text-gray-400 hover:underline">
                                    ⚙ {{ __('board_admin.automations') }}
                                </button>
                                @if ($status->role === null)
                                    <form method="POST" action="{{ route('organization.statuses.destroy', $status) }}"
                                          onsubmit="return confirm('{{ __('board_admin.delete_status_confirm') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">{{ __('board_admin.delete') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Automationen (On-Enter-Effekte) --}}
                        <div x-show="openFx" x-cloak class="pb-3 ps-2">
                            <p class="mb-2 text-xs text-gray-400 dark:text-gray-500">{{ __('board_admin.automations_hint') }}</p>
                            <form method="POST" action="{{ route('organization.statuses.effects', $status) }}">
                                @csrf
                                @method('PUT')
                                <template x-for="(row, idx) in rows" :key="idx">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <select x-bind:name="'effects[' + idx + '][field]'" x-model="row.field" class="{{ $inputClass }}">
                                            <option value="">{{ __('board_admin.effect_field') }}</option>
                                            @foreach ($effectFields as $f)
                                                <option value="{{ $f }}">{{ $f }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" x-bind:name="'effects[' + idx + '][value]'" x-model="row.value"
                                               placeholder="{{ __('board_admin.effect_value_placeholder') }}" class="{{ $inputClass }} w-52">
                                        <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                            <input type="checkbox" value="1" x-bind:name="'effects[' + idx + '][only_if_empty]'" x-model="row.only_if_empty"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                            {{ __('board_admin.effect_only_if_empty') }}
                                        </label>
                                        <button type="button" x-on:click="rows.splice(idx, 1)"
                                                class="text-rose-600 dark:text-rose-400 hover:underline">×</button>
                                    </div>
                                </template>
                                <p x-show="rows.length === 0" class="mb-2 text-xs text-gray-400 dark:text-gray-500">{{ __('board_admin.no_effects') }}</p>
                                <div class="flex items-center gap-3">
                                    <button type="button" x-on:click="rows.push({ field: '', value: '', only_if_empty: false })"
                                            class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('board_admin.add_effect') }}</button>
                                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">{{ __('board_admin.save_effects') }}</button>
                                </div>
                            </form>
                        </div>
                        </div>
                    @endforeach
                </div>

                {{-- Eigenen Status anlegen --}}
                <div class="mt-6 border-t pt-5">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('board_admin.new_status_title') }}</h4>
                    <p class="mt-1 mb-3 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('board_admin.new_status_intro') }}</p>
                    <form method="POST" action="{{ route('organization.statuses.store') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <x-input-label :value="__('board_admin.col_label')" />
                            <input type="text" name="label" required maxlength="255" class="{{ $inputClass }} mt-1 w-40">
                        </div>
                        <div>
                            <x-input-label :value="__('board_admin.col_label_en')" />
                            <input type="text" name="label_en" maxlength="255" class="{{ $inputClass }} mt-1 w-40">
                        </div>
                        <div>
                            <x-input-label :value="__('board_admin.kind')" />
                            <select name="kind" class="{{ $inputClass }} mt-1">
                                @foreach (['active', 'review', 'done', 'exception'] as $k)
                                    <option value="{{ $k }}">{{ __('board_admin.kind_'.$k) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :value="__('board_admin.col_color')" />
                            <select name="color_token" class="{{ $inputClass }} mt-1">
                                @foreach ($colors as $token)
                                    <option value="{{ $token }}">{{ $token }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label :value="__('board_admin.col_wip')" />
                            <input type="number" name="wip_limit" min="1" placeholder="—" class="{{ $inputClass }} mt-1 w-20">
                        </div>
                        <div>
                            <x-input-label :value="__('board_admin.col_group')" />
                            <select name="group_id" class="{{ $inputClass }} mt-1">
                                <option value="">{{ __('board_admin.no_group') }}</option>
                                @foreach ($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="default_expanded" value="1"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            {{ __('board_admin.col_expanded') }}
                        </label>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('board_admin.create_status') }}
                        </button>
                    </form>
                    <x-input-error :messages="$errors->get('label')" class="mt-2" />
                </div>
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
