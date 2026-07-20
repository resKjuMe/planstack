<x-status-shell :project="$project" :active="$active" :bare="true">
    <x-page-head :title="__('common.changelog')" class="mb-4">
        <ul class="list-disc space-y-1 ps-4">
            <li><span class="font-medium">{{ __('common.changelog') }}</span>: {{ __('status.change_log_of_all_tasks_in_this_project') }}</li>
            <li>{{ __('status.each_row_shows_the_time_the_changed') }}</li>
            <li>{{ __('status.n_more_fields_reveals_additional') }}</li>
        </ul>
    </x-page-head>

    @php $lastDate = null; @endphp
    <div class="space-y-2">
        @forelse ($changes as $entry)
            @php $date = $entry['when']->format('d.m.Y'); @endphp
            @if ($date !== $lastDate)
                <div class="{{ $loop->first ? '' : 'pt-4' }} text-xs font-medium text-gray-400">{{ $date }}</div>
                @php $lastDate = $date; @endphp
            @endif

            <div x-data="{ open: false }" class="rounded-xl bg-white p-3 ring-1 ring-gray-200">
                <button type="button" @click="open = !open" class="flex w-full items-center gap-3 text-left">
                    <span class="w-10 shrink-0 text-xs text-gray-400">{{ $entry['when']->format('H:i') }}</span>
                    <span class="flex-1 text-sm text-gray-800">
                        @foreach ($entry['headline'] as $seg)
                            @switch($seg['t'])
                                @case('text')
                                    {{ $seg['v'] }}
                                    @break
                                @case('tag')
                                    <span class="font-mono font-medium text-indigo-600">{{ $seg['v'] }}</span>
                                    @break
                                @case('status')
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $seg['cls'] }}">{{ $seg['v'] }}</span>
                                    @break
                                @case('quote')
                                    <span class="text-gray-500">&bdquo;{{ $seg['v'] }}&ldquo;</span>
                                    @break
                            @endswitch
                        @endforeach
                    </span>
                    <span class="shrink-0 text-xs text-gray-400">{{ $entry['causer_short'] }}</span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-90'"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6l6 6l-6 6" />
                    </svg>
                </button>

                <div x-show="open" x-cloak class="mt-3 space-y-3 border-t border-gray-100 pt-3">
                    @foreach ($entry['sections'] as $section)
                        <div x-data="{ moreOpen: false }">
                            @if ($section['label'])
                                <div class="mb-1 text-xs font-medium text-gray-400">{{ $section['label'] }}</div>
                            @endif
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-400">
                                        <th class="pr-4 py-1 font-medium">{{ __('common.field') }}</th>
                                        <th class="pr-4 py-1 font-medium">{{ __('status.before') }}</th>
                                        <th class="py-1 font-medium">{{ __('status.after') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section['visible'] as $row)
                                        <tr class="align-top">
                                            <td class="pr-4 py-1 whitespace-nowrap text-gray-500">{{ $row['field'] }}</td>
                                            <td class="pr-4 py-1 text-gray-600">{{ $row['old'] ?? '—' }}</td>
                                            <td class="py-1 font-medium text-gray-800">{{ $row['new'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach ($section['hidden'] as $row)
                                        <tr x-show="moreOpen" x-cloak class="align-top">
                                            <td class="pr-4 py-1 whitespace-nowrap text-gray-500">{{ $row['field'] }}</td>
                                            <td class="pr-4 py-1 text-gray-600">{{ $row['old'] ?? '—' }}</td>
                                            <td class="py-1 font-medium text-gray-800">{{ $row['new'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (! empty($section['hidden']))
                                <button type="button" @click="moreOpen = !moreOpen" class="mt-1 text-xs text-indigo-600 hover:underline">
                                    <span x-show="!moreOpen">{{ __('status.count_more_fields', ['count' => count($section['hidden'])]) }}</span>
                                    <span x-show="moreOpen" x-cloak>{{ __('status.show_less') }}</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="p-6 text-sm text-gray-400">{{ __('status.no_changes_logged_yet') }}</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $changes->links() }}
    </div>
</x-status-shell>
