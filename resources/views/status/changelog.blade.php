<x-status-shell :project="$project" :active="$active" :bare="true">
    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
        @forelse ($changes as $entry)
            <div x-data="{ open: false }" class="p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-mono text-gray-400">{{ $entry['when']->format('d.m.Y H:i') }}</span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $entry['entity_label'] }}</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $entry['action_badge'] }}">{{ $entry['action_label'] }}</span>
                        <span class="font-medium text-gray-800">{{ $entry['subject'] }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span>{{ $entry['causer'] }}</span>
                        @if (! empty($entry['changes']))
                            <button type="button" @click="open = !open" class="text-indigo-600 hover:underline">
                                <span x-show="!open">Details anzeigen</span>
                                <span x-show="open" x-cloak>Details ausblenden</span>
                            </button>
                        @endif
                    </div>
                </div>

                @if (! empty($entry['changes']))
                    <div x-show="open" x-cloak class="mt-3 border-t border-gray-100 pt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-400">
                                    <th class="pr-4 py-1 font-medium">Feld</th>
                                    <th class="pr-4 py-1 font-medium">Vorher</th>
                                    <th class="py-1 font-medium">Nachher</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entry['changes'] as $change)
                                    <tr class="align-top">
                                        <td class="pr-4 py-1 whitespace-nowrap text-gray-500">{{ $change['field'] }}</td>
                                        <td class="pr-4 py-1 text-gray-600">{{ $change['old'] ?? '—' }}</td>
                                        <td class="py-1 text-gray-800">{{ $change['new'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <p class="p-6 text-sm text-gray-400">Noch keine Änderungen protokolliert.</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $changes->links() }}
    </div>
</x-status-shell>
