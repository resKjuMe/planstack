@php
    $inputClass = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';

    // Icon-Farbe je Status-Token (nur Textfarbe, kein Spalten-Hintergrund).
    $headCol = [
        'gray' => 'text-gray-500 dark:text-gray-400',
        'slate' => 'text-slate-500 dark:text-slate-400',
        'indigo' => 'text-indigo-600 dark:text-indigo-400',
        'sky' => 'text-sky-600 dark:text-sky-400',
        'blue' => 'text-blue-600 dark:text-blue-400',
        'navy' => 'text-blue-800 dark:text-blue-300',
        'purple' => 'text-purple-600 dark:text-purple-400',
        'green' => 'text-green-600 dark:text-green-400',
        'emerald' => 'text-emerald-600 dark:text-emerald-400',
        'teal' => 'text-teal-600 dark:text-teal-400',
        'rose' => 'text-rose-600 dark:text-rose-400',
        'red' => 'text-red-600 dark:text-red-400',
        'orange' => 'text-orange-600 dark:text-orange-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
    ];
    $headColFor = fn ($s) => $headCol[$s->color_token] ?? $headCol['gray'];
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
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('events.group_'.$group) }}</h3>

                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    <th rowspan="2" class="py-2 pe-4 text-left align-bottom font-medium">{{ __('events.col_event') }}</th>
                                    <th rowspan="2" class="py-2 pe-4 text-left align-bottom font-medium">{{ __('events.target_status') }}</th>
                                    <th colspan="{{ $statuses->count() }}" class="pb-1 text-center font-medium">{{ __('events.overridable') }}</th>
                                </tr>
                                <tr>
                                    @foreach ($statuses as $status)
                                        @php $svg = \App\Support\StatusIcons::svg($status->icon); @endphp
                                        <th class="w-10 px-1 pb-2 pt-1 text-center {{ $headColFor($status) }}"
                                            title="{{ $status->label }}">
                                            <span class="mx-auto flex h-6 w-6 items-center justify-center">
                                                @if ($svg)
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                         stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">{!! $svg !!}</svg>
                                                @else
                                                    <span class="text-[10px] font-semibold">{{ mb_substr($status->label, 0, 2) }}</span>
                                                @endif
                                            </span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach (\App\Enums\TaskEvent::forGroup($group) as $event)
                                    @php $config = $configs->get($event->value); @endphp
                                    <tr x-data="{ target: '{{ $config?->target_status_id }}' }" class="align-top">
                                        <td class="py-3 pe-4">
                                            <div class="flex items-start gap-2.5">
                                                @php $eventSvg = \App\Support\StatusIcons::svg($event->icon()); @endphp
                                                @if ($eventSvg)
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true">{!! $eventSvg !!}</svg>
                                                @endif
                                                <div>
                                                    <div class="flex flex-wrap items-baseline gap-x-2">
                                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $event->label() }}</span>
                                                        <span class="font-mono text-xs text-gray-400 dark:text-gray-500">{{ $event->value }}</span>
                                                    </div>
                                                    <p class="mt-0.5 max-w-md text-xs text-gray-500 dark:text-gray-400">{{ $event->description() }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 pe-4">
                                            <select name="events[{{ $event->value }}][target_status_id]" x-model="target" class="{{ $inputClass }} w-full min-w-[10rem]">
                                                <option value="">{{ __('events.no_status_change') }}</option>
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status->id }}" @selected($config?->target_status_id === $status->id)>{{ $status->label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        @foreach ($statuses as $status)
                                            <td class="px-1 py-3 text-center"
                                                x-bind:class="!target && 'opacity-40'">
                                                <input type="checkbox" name="events[{{ $event->value }}][overridable_status_ids][]" value="{{ $status->id }}"
                                                       @checked(in_array($status->id, $config?->overridable_status_ids ?? []))
                                                       x-bind:disabled="!target"
                                                       title="{{ $status->label }}"
                                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ __('events.overridable_hint') }}</p>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('events.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
