@php
    $inputClass = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';
    // status_id => on-enter effects (for the read-only "column automations" panel).
    $statusEffectsJs = $statusEffects->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('events.title') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="flex items-center justify-between gap-4">
                <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ __('events.intro') }}</p>
                <div class="flex shrink-0 items-center gap-4 text-sm">
                    <a href="{{ route('organization.events.effects.index') }}"
                       class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('events.effects_link') }}</a>
                    <a href="{{ route('organization.statuses.index') }}"
                       class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('board_admin.manage_link') }}</a>
                    <a href="{{ route('organization.index') }}"
                       class="text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('events.back_to_organization') }}</a>
                </div>
            </div>

            <form method="POST" action="{{ route('organization.events.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach ($groups as $group)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('events.group_'.$group) }}</h3>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach (\App\Enums\TaskEvent::forGroup($group) as $event)
                                @php $config = $configs->get($event->value); @endphp
                                <div class="py-4"
                                     x-data="{
                                         target: '{{ $config?->target_status_id }}',
                                         statusEffects: @js($statusEffectsJs),
                                         get columnEffects() { return this.target && this.statusEffects[this.target] ? this.statusEffects[this.target] : []; }
                                     }">
                                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $event->label() }}</span>
                                            <span class="ms-2 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $event->value }}</span>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $event->description() }}</p>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 lg:grid-cols-2">
                                        {{-- Zielstatus + überschreibbare Status --}}
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('events.target_status') }}</label>
                                                <select name="events[{{ $event->value }}][target_status_id]" x-model="target" class="{{ $inputClass }} mt-1 w-full">
                                                    <option value="">{{ __('events.no_status_change') }}</option>
                                                    @foreach ($statuses as $status)
                                                        <option value="{{ $status->id }}" @selected($config?->target_status_id === $status->id)>{{ $status->label }}</option>
                                                    @endforeach
                                                </select>
                                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('events.target_status_hint') }}</p>
                                            </div>

                                            <div x-show="target" x-cloak>
                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('events.overridable') }}</label>
                                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                    @foreach ($statuses as $status)
                                                        <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                                                            <input type="checkbox" name="events[{{ $event->value }}][overridable_status_ids][]" value="{{ $status->id }}"
                                                                   @checked(in_array($status->id, $config?->overridable_status_ids ?? []))
                                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                            {{ $status->label }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('events.overridable_hint') }}</p>
                                            </div>
                                        </div>

                                        {{-- Automationen der gewählten Spalte (readonly) --}}
                                        <div x-show="target" x-cloak>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('events.column_automations') }}</label>
                                            <template x-if="columnEffects.length">
                                                <ul class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    <template x-for="(fx, i) in columnEffects" :key="i">
                                                        <li>
                                                            <span class="font-mono" x-text="fx.field"></span>
                                                            = <span class="font-mono" x-text="fx.value"></span>
                                                            <span x-show="fx.only_if_empty" class="text-gray-400 dark:text-gray-500">({{ __('events.effect_only_if_empty') }})</span>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </template>
                                            <p x-show="!columnEffects.length" class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('events.no_column_automations') }}</p>
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
