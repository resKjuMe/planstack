@php
    $inputClass = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('events.effects_title') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="flex items-center justify-between gap-4">
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('events.effects_intro') }}</p>
                <div class="flex shrink-0 items-center gap-4 text-sm">
                    <a href="{{ route('organization.events.index') }}"
                       class="text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('events.back_to_events') }}</a>
                </div>
            </div>

            <form method="POST" action="{{ route('organization.events.effects.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach ($groups as $group)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('events.group_'.$group) }}</h3>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach (\App\Enums\TaskEvent::forGroup($group) as $event)
                                @php $config = $configs->get($event->value); @endphp
                                @php
                                    $target = $config?->target_status_id;
                                    $columnEffects = $target ? ($statusEffects[$target] ?? []) : [];
                                @endphp
                                <div class="py-4"
                                     x-data="{ rows: @js($config?->effects ?? []) }">
                                    <div class="mb-3 flex items-start gap-2.5">
                                        @php $eventSvg = \App\Support\StatusIcons::svg($event->icon()); @endphp
                                        @if ($eventSvg)
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true">{!! $eventSvg !!}</svg>
                                        @endif
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $event->label() }}</span>
                                            <span class="ms-2 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $event->value }}</span>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $event->description() }}</p>
                                        </div>
                                    </div>

                                    {{-- Automationen der (auf der Hauptseite gewählten) Zielspalte, readonly. --}}
                                    @if ($target)
                                        <div class="mb-3">
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                                {{ __('events.column_automations') }}
                                                <span class="font-normal text-gray-400 dark:text-gray-500">— {{ $statusLabels[$target] ?? '' }}</span>
                                            </label>
                                            @if (count($columnEffects))
                                                <ul class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    @foreach ($columnEffects as $fx)
                                                        <li>
                                                            <span class="font-mono">{{ $fx['field'] ?? '' }}</span>
                                                            = <span class="font-mono">{{ $fx['value'] ?? '' }}</span>
                                                            @if (! empty($fx['only_if_empty']))
                                                                <span class="text-gray-400 dark:text-gray-500">({{ __('events.effect_only_if_empty') }})</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('events.no_column_automations') }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('events.extra_effects') }}</label>
                                    <div class="mt-1 space-y-2">
                                        <template x-for="(row, idx) in rows" :key="idx">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <select x-bind:name="'events[{{ $event->value }}][effects][' + idx + '][field]'" x-model="row.field" class="{{ $inputClass }}">
                                                    <option value="">{{ __('events.effect_field') }}</option>
                                                    @foreach ($effectFields as $f)
                                                        <option value="{{ $f }}">{{ $f }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" x-bind:name="'events[{{ $event->value }}][effects][' + idx + '][value]'" x-model="row.value"
                                                       placeholder="{{ __('events.effect_value_placeholder') }}" class="{{ $inputClass }} w-48">
                                                <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                    <input type="checkbox" value="1" x-bind:name="'events[{{ $event->value }}][effects][' + idx + '][only_if_empty]'" x-model="row.only_if_empty"
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                    {{ __('events.effect_only_if_empty') }}
                                                </label>
                                                <button type="button" x-on:click="rows.splice(idx, 1)"
                                                        class="text-rose-600 dark:text-rose-400 hover:underline">×</button>
                                            </div>
                                        </template>
                                        <p x-show="rows.length === 0" class="text-xs text-gray-400 dark:text-gray-500">{{ __('events.no_effects') }}</p>
                                        <div class="pt-1">
                                            <button type="button" x-on:click="rows.push({ field: '', value: '', only_if_empty: false })"
                                                    class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('events.add_effect') }}</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('events.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
