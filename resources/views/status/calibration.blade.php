@php
    $tileTextClass = fn ($cls) => match ($cls) {
        'green' => 'text-emerald-600',
        'amber' => 'text-amber-600',
        'red' => 'text-red-600',
        default => 'text-gray-900',
    };
@endphp

<x-status-shell :project="$project" :active="$active" :bare="true">
    <div x-data="{ tab: 'all' }" class="space-y-6">
        <div class="flex items-baseline justify-between">
            <h1 class="text-xl font-semibold text-gray-900">Kalibrierung</h1>
            <div class="text-sm text-gray-400">{{ $kpis['total'] }} gemergte Tasks mit PR-Daten</div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div class="text-xs font-medium text-gray-400">Ø Abweichung Dateien</div>
                @if ($kpis['avgDeviationLabel'])
                    <div class="mt-1 text-2xl font-bold {{ $tileTextClass($kpis['avgDeviationClass']) }}">{{ $kpis['avgDeviationLabel'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ $kpis['avgDeviationHint'] }}</div>
                @else
                    <div class="mt-1 text-2xl font-bold text-gray-300">—</div>
                    <div class="mt-1 text-sm text-gray-500">{{ $kpis['avgDeviationHint'] }}</div>
                @endif
            </div>

            <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div class="text-xs font-medium text-gray-400">Ø Dauer je Story Point</div>
                @if ($kpis['avgDurationPerSp'] !== null)
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['avgDurationPerSpLabel'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">
                        PR-Erstellung bis Merge
                        @if ($kpis['storyPointsPerEightHours'] !== null)
                            &middot; &asymp; {{ number_format($kpis['storyPointsPerEightHours'], 1, ',', '') }} SP pro 8-Std-Tag
                        @endif
                    </div>
                @else
                    <div class="mt-1 text-2xl font-bold text-gray-300">—</div>
                    <div class="mt-1 text-sm text-gray-500">PR-Erstellung bis Merge</div>
                @endif
            </div>

            <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div class="text-xs font-medium text-gray-400">Treffsicherheit</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['hits'] }} / {{ $kpis['hitsTotal'] }}</div>
                <div class="mt-1 text-sm text-gray-500">innerhalb ±25 %</div>
            </div>
        </div>

        <div class="inline-flex items-center gap-1 rounded-full bg-gray-100 p-1">
            <button type="button" @click="tab = 'all'"
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="tab === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                Alle
            </button>
            <button type="button" @click="tab = 'outliers'"
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="tab === 'outliers' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                Nur Ausreißer
            </button>
            <button type="button" @click="tab = 'grouped'"
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="tab === 'grouped' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                Nach SP gruppiert
            </button>
        </div>

        <div x-show="tab === 'all'">
            @forelse ($rows as $row)
                @if (! $loop->first)
                    <div class="border-t border-gray-100"></div>
                @endif
                @include('status.partials.calibration-row', ['row' => $row])
            @empty
                <p class="py-6 text-sm text-gray-400">Noch keine gemergten Tasks mit PR-Daten.</p>
            @endforelse
        </div>

        <div x-show="tab === 'outliers'" x-cloak>
            @php $outliers = $rows->filter(fn ($r) => $r['deviationClass'] !== 'green')->values(); @endphp
            @forelse ($outliers as $row)
                @if (! $loop->first)
                    <div class="border-t border-gray-100"></div>
                @endif
                @include('status.partials.calibration-row', ['row' => $row])
            @empty
                <p class="py-6 text-sm text-gray-400">Keine Ausreißer außerhalb ±25 % Abweichung.</p>
            @endforelse
        </div>

        <div x-show="tab === 'grouped'" x-cloak class="space-y-6">
            @forelse ($groups as $group)
                <div>
                    <div class="mb-1 text-xs font-medium text-gray-400">
                        {{ $group['storyPoints'] }} SP · Ø {{ number_format($group['avgDuration'], 1, ',', '') }} Tage bis Merge · {{ $group['rows']->count() }} Tasks
                    </div>
                    @foreach ($group['rows'] as $row)
                        @if (! $loop->first)
                            <div class="border-t border-gray-100"></div>
                        @endif
                        @include('status.partials.calibration-row', ['row' => $row])
                    @endforeach
                </div>
            @empty
                <p class="py-6 text-sm text-gray-400">Keine Tasks mit Story Points und Dauer verfügbar.</p>
            @endforelse
        </div>
    </div>
</x-status-shell>
