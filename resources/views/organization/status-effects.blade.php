@php
    $inputClass = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';
    // Literal swatch classes (scanned by Tailwind) for the status color dot.
    $swatch = [
        'gray' => 'bg-gray-500', 'slate' => 'bg-slate-500', 'indigo' => 'bg-indigo-500',
        'sky' => 'bg-sky-500', 'blue' => 'bg-blue-500', 'navy' => 'bg-blue-700',
        'purple' => 'bg-purple-500', 'green' => 'bg-green-500', 'emerald' => 'bg-emerald-500',
        'teal' => 'bg-teal-500', 'rose' => 'bg-rose-500', 'red' => 'bg-red-500',
        'orange' => 'bg-orange-500', 'amber' => 'bg-amber-500',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('board_admin.automations_title') }}</h2>
    </x-slot>

    <x-slot name="subheader">
        <x-organization-tabs active="statuses" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="flex items-center justify-between gap-4">
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('board_admin.automations_intro') }}</p>
                <a href="{{ route('organization.statuses.index') }}"
                   class="shrink-0 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('board_admin.back_to_statuses') }}</a>
            </div>

            <form method="POST" action="{{ route('organization.statuses.effects.update-all') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                <th class="py-2 pe-4 text-left font-medium">{{ __('board_admin.col_status') }}</th>
                                <th class="py-2 text-left font-medium">{{ __('board_admin.automations') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($statuses as $status)
                                @php
                                    $p = 'statuses['.$status->id.']';
                                    $svg = \App\Support\StatusIcons::svg($status->icon);
                                @endphp
                                <tr x-data="{ rows: @js($status->on_enter_effects ?? []) }" class="align-top">
                                    {{-- Status --}}
                                    <td class="py-3 pe-4">
                                        <div class="flex items-center gap-2">
                                            <span class="block h-2 w-2 shrink-0 rounded-full {{ $swatch[$status->color_token] ?? $swatch['gray'] }}"></span>
                                            @if ($svg)
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                     stroke-linecap="round" stroke-linejoin="round"
                                                     class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true">{!! $svg !!}</svg>
                                            @endif
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $status->label }}</span>
                                            <span class="font-mono text-xs text-gray-400 dark:text-gray-500">{{ $status->key }}</span>
                                        </div>
                                    </td>

                                    {{-- Automationen (editierbar) --}}
                                    <td class="py-3">
                                        <div class="space-y-2">
                                            <template x-for="(row, idx) in rows" :key="idx">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <select x-bind:name="'{{ $p }}[effects][' + idx + '][field]'" x-model="row.field" class="{{ $inputClass }}">
                                                        <option value="">{{ __('board_admin.effect_field') }}</option>
                                                        @foreach ($effectFields as $f)
                                                            <option value="{{ $f }}">{{ $f }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" x-bind:name="'{{ $p }}[effects][' + idx + '][value]'" x-model="row.value"
                                                           placeholder="{{ __('board_admin.effect_value_placeholder') }}" class="{{ $inputClass }} w-52">
                                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                        <input type="checkbox" value="1" x-bind:name="'{{ $p }}[effects][' + idx + '][only_if_empty]'" x-model="row.only_if_empty"
                                                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                        {{ __('board_admin.effect_only_if_empty') }}
                                                    </label>
                                                    <button type="button" x-on:click="rows.splice(idx, 1)"
                                                            class="text-rose-600 dark:text-rose-400 hover:underline">×</button>
                                                </div>
                                            </template>
                                            <div class="pt-1">
                                                <button type="button" x-on:click="rows.push({ field: '', value: '', only_if_empty: false })"
                                                        class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('board_admin.add_effect') }}</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('board_admin.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
