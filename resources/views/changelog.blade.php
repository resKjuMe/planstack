@php $releases = config('changelog.releases', []); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Was ist neu?</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-500">Alle sichtbaren Änderungen an Planstack — neueste zuerst.</p>

            @forelse ($releases as $release)
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-baseline justify-between">
                        <h3 class="font-semibold text-gray-900">
                            <span class="inline-flex items-center rounded-md bg-indigo-600 px-2 py-0.5 text-sm font-mono font-semibold text-white">v{{ $release['version'] }}</span>
                        </h3>
                        @if (!empty($release['date']))
                            <span class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($release['date'])->translatedFormat('d. F Y') }}</span>
                        @endif
                    </div>
                    <ul class="mt-4 space-y-2">
                        @foreach ($release['changes'] as $change)
                            <li class="flex gap-2 text-sm text-gray-700">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                <span>{{ $change }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow p-6 text-sm text-gray-500">Noch keine Einträge.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
