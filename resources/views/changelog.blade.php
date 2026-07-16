@php $releases = config('changelog.releases', []); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Was ist neu?</h2>
    </x-slot>

    <style>[x-cloak]{display:none !important;}</style>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
            <p class="text-sm text-gray-500">Alle sichtbaren Änderungen an Planstack — neueste zuerst. Zeile anklicken für Details.</p>

            @forelse ($releases as $release)
                <div x-data="{ open: false }" class="bg-white rounded-lg shadow">
                    {{-- Kopfzeile: Version + TL;DR (fett, einzeilig, truncated) + Datum + Chevron --}}
                    <button type="button" @click="open = ! open"
                            class="flex w-full items-center gap-3 px-6 py-4 text-left">
                        <span class="shrink-0 inline-flex items-center rounded-md bg-indigo-600 px-2 py-0.5 text-sm font-mono font-semibold text-white">v{{ $release['version'] }}</span>

                        <span class="min-w-0 flex-1 truncate text-sm">
                            @if (!empty($release['tldr']))
                                @foreach ($release['tldr'] as $kw)<span class="font-bold text-gray-900">{{ $kw }}</span>@unless ($loop->last)<span class="text-gray-500">&nbsp;·&nbsp;&nbsp;</span>@endunless@endforeach
                            @endif
                        </span>

                        @if (!empty($release['date']))
                            <span class="shrink-0 text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($release['date'])->translatedFormat('d. F Y') }}</span>
                        @endif
                        <svg class="shrink-0 h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    {{-- Details: erst nach dem Aufklappen --}}
                    <div x-cloak x-show="open" class="border-t border-gray-100 px-6 py-4">
                        <ul class="space-y-2">
                            @foreach ($release['changes'] as $change)
                                <li class="flex gap-2 text-sm text-gray-700">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    <span>{{ $change }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow p-6 text-sm text-gray-500">Noch keine Einträge.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
