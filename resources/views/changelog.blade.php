@php $releases = config('changelog.releases', []); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('changelog.what_s_new') }}</h2>
    </x-slot>

    <style>[x-cloak]{display:none !important;}</style>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
            <p class="text-sm text-gray-500">{{ __('changelog.all_visible_changes_to_planstack_newest') }}</p>

            @forelse ($releases as $release)
                @php
                    $loc = app()->getLocale();
                    $tldr = $release['tldr'][$loc] ?? $release['tldr']['de'] ?? [];
                    $changes = $release['changes'][$loc] ?? $release['changes']['de'] ?? [];
                @endphp
                <div x-data="{ open: false }" data-release-version="{{ $release['version'] }}" class="bg-white rounded-lg shadow">
                    {{-- Kopfzeile: Version + TL;DR (fett, einzeilig, truncated) + Datum + Chevron --}}
                    <button type="button" @click="open = ! open"
                            class="flex w-full items-center gap-3 px-6 py-4 text-left">
                        <span class="shrink-0 inline-flex items-center rounded-md bg-indigo-600 px-2 py-0.5 text-sm font-mono font-semibold text-white">v{{ $release['version'] }}</span>
                        <span class="cl-new-badge shrink-0 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700" style="display:none">{{ __('changelog.new') }}</span>

                        <span class="min-w-0 flex-1 truncate text-sm">
                            @if (!empty($tldr))
                                @foreach ($tldr as $kw)@if (! $loop->first)<span class="text-gray-500">&nbsp;·&nbsp;</span>@endif<span class="font-bold text-gray-900">{{ $kw }}</span>@endforeach
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
                            @foreach ($changes as $change)
                                @php
                                    // „Neu:"/„New:"-Präfix wird als Badge dargestellt statt als Text.
                                    $isNew = \Illuminate\Support\Str::startsWith($change, ['Neu:', 'New:']);
                                    $text = $isNew ? ltrim(\Illuminate\Support\Str::after($change, ':')) : $change;
                                @endphp
                                <li class="flex gap-2 text-sm text-gray-700">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    <span>
                                        @if ($isNew)
                                            <span class="me-1.5 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 align-[1px]">{{ __('changelog.new') }}</span>
                                        @endif{{ $text }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow p-6 text-sm text-gray-500">{{ __('changelog.no_entries_yet') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Neue Einträge (Version > zuletzt gesehen) hervorheben, danach gesehene
         Version aktualisieren. Erstbesuch (nichts gespeichert) → keine Hervorhebung. --}}
    <script>
    (function () {
        var latest = @json(config('changelog.releases.0.version'));
        var key = 'changelog-seen-version';
        function cmp(a, b) {
            var pa = String(a || '0').split('.').map(Number), pb = String(b || '0').split('.').map(Number);
            for (var i = 0; i < 3; i++) { var d = (pa[i] || 0) - (pb[i] || 0); if (d) return d < 0 ? -1 : 1; }
            return 0;
        }
        var seen = null;
        try { seen = localStorage.getItem(key); } catch (e) {}
        if (seen) {
            document.querySelectorAll('[data-release-version]').forEach(function (card) {
                if (cmp(card.getAttribute('data-release-version'), seen) > 0) {
                    card.classList.add('ring-2', 'ring-indigo-400');
                    var b = card.querySelector('.cl-new-badge');
                    if (b) b.style.display = '';
                }
            });
        }
        try { localStorage.setItem(key, latest); } catch (e) {}
    })();
    </script>
</x-app-layout>
