@php
    $tileText = fn ($c) => match ($c) {
        'green' => 'text-green-600 dark:text-green-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        'red' => 'text-red-600 dark:text-red-400',
        default => 'text-gray-300 dark:text-gray-600',
    };
    $medArrow = $kpis['median'] === null ? '' : ($kpis['median'] < 0 ? '↘' : ($kpis['median'] > 0 ? '↗' : '→'));

    // Scatter-Koordinaten (viewBox 0 0 340 240)
    $ax = $scatter['axis'];
    $L = 30; $B = 214; $T = 8; $Rr = 332;
    $plotW = $Rr - $L; $plotH = $B - $T;
    $sx = fn ($v) => $L + ($ax > 0 ? $v / $ax : 0) * $plotW;
    $sy = fn ($v) => $B - ($ax > 0 ? $v / $ax : 0) * $plotH;
@endphp

<x-status-shell :project="$project" :active="$active" :bare="true">
    <div x-data="{
            tab: 'all',
            sort: 'dev',
            rows: @js($rowData),
            get list() {
                var t = this.tab;
                var r = this.rows.filter(function (x) {
                    return t === 'outliers' ? x.isOutlier : t === 'noEstimate' ? !x.hasEstimate : true;
                });
                var key = { dev: 'sortDev', sp: 'sortSp', date: 'sortDate', time: 'sortTime' }[this.sort];
                return r.slice().sort(function (a, b) { return b[key] - a[key]; });
            }
         }"
         class="space-y-6">

        <x-page-head :title="__('common.calibration')">
            <x-slot:meta>
                <span class="text-sm text-gray-400 dark:text-gray-500">
                    {{ __('status.total_merged_tasks', ['total' => $kpis['total']]) }}{{ $kpis['lastSync'] ? ' · '.__('status.last_synced_time', ['time' => $kpis['lastSync']]) : '' }}
                </span>
            </x-slot:meta>
            <div class="space-y-4">
                <div>
                    <div class="mb-1 font-semibold text-gray-700 dark:text-gray-300">{{ __('status.metrics') }}</div>
                    <ul class="list-disc space-y-1 ps-4">
                        <li><span class="font-medium">{{ __('status.median_deviation') }}</span>: {{ __('status.typical_deviation_of_the_actually') }}</li>
                        <li><span class="font-medium">{{ __('status.velocity') }}</span>: {{ __('status.completed_story_points_per_day_measured') }}</li>
                        <li><span class="font-medium">{{ __('status.accuracy_25') }}</span>: {{ __('status.share_of_tasks_whose_file_deviation_is') }}</li>
                        <li><span class="font-medium">{{ __('status.data_basis') }}</span>: {{ __('status.how_many_of_the_merged_tasks_have_a') }}</li>
                    </ul>
                </div>
                <div>
                    <div class="mb-1 font-semibold text-gray-700 dark:text-gray-300">{{ __('status.charts_table') }}</div>
                    <ul class="list-disc space-y-1 ps-4">
                        <li><span class="font-medium">{{ __('status.estimated_vs_actual') }}</span>: {{ __('status.one_task_per_point_x_estimated_y') }}</li>
                        <li><span class="font-medium">{{ __('status.accuracy_by_sp') }}</span>: {{ __('status.hit_rate_grouped_by_task_size_shows') }}</li>
                        <li><span class="font-medium">{{ __('status.deviation') }}</span>: {{ __('status.changed_estimated_estimated_green_25') }}</li>
                        <li><span class="font-medium">{{ __('status.outliers') }}</span>: {{ __('status.deviation_over_50') }}</li>
                        <li><span class="font-medium">{{ __('status.time_sp') }}</span>: {{ __('status.calendar_time_from_claim_merge_divided') }}</li>
                        <li><span class="font-medium">{{ __('status.no_estimate_2') }}</span>: {{ __('status.task_with_no_file_count_on_record') }}</li>
                    </ul>
                </div>
            </div>
        </x-page-head>

        {{-- KPI-Kacheln --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.median_deviation') }}</div>
                @if ($kpis['medianLabel'])
                    <div class="mt-1 text-3xl font-bold {{ $tileText($kpis['medianClass']) }}">{{ $kpis['medianLabel'] }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $medArrow }} {{ $kpis['medianHint'] }}</div>
                @else
                    <div class="mt-1 text-3xl font-bold text-gray-300 dark:text-gray-600">—</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $kpis['medianHint'] }}</div>
                @endif
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.velocity') }}</div>
                @if ($kpis['spPerDay'] !== null)
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ number_format($kpis['spPerDay'], 1, ',', '') }} <span class="text-lg font-semibold text-gray-500 dark:text-gray-400">{{ __('status.sp_day') }}</span></div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">@if ($kpis['daysPerSpLabel'])Ø {{ $kpis['daysPerSpLabel'] }} {{ __('status.per_sp') }} · @endif {{ __('status.claim_merge') }}</div>
                @else
                    <div class="mt-1 text-3xl font-bold text-gray-300 dark:text-gray-600">—</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('status.claim_merge') }}</div>
                @endif
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.accuracy_25') }}</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">{{ $kpis['hits'] }} <span class="text-lg font-semibold text-gray-400 dark:text-gray-500">/ {{ $kpis['hitsTotal'] }}</span></div>
                <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full bg-amber-400" style="width: {{ $kpis['hitsTotal'] ? round($kpis['hits'] / $kpis['hitsTotal'] * 100) : 0 }}%"></div>
                </div>
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.data_basis') }}</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">{{ $kpis['withEstimate'] }} <span class="text-lg font-semibold text-gray-400 dark:text-gray-500">/ {{ $kpis['total'] }}</span></div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('status.tasks_with_a_file_estimate') }}</div>
            </div>
        </div>

        {{-- Warnbanner: Tasks ohne Dateischätzung --}}
        @if ($kpis['noEstimate'] > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 px-4 py-3">
                <div class="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-300">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    <span>{{ trans_choice('status.no_estimate_note', $kpis['noEstimate'], ['count' => $kpis['noEstimate']]) }}</span>
                </div>
                <button type="button"
                        @click="tab = 'noEstimate'; $nextTick(() => document.getElementById('calib-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="shrink-0 rounded-md bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-semibold text-amber-800 dark:text-amber-300 ring-1 ring-amber-300 dark:ring-amber-700 hover:bg-amber-100 dark:hover:bg-amber-900/40">
                    {{ __('status.show') }}
                </button>
            </div>
        @endif

        {{-- Zwei Panels: Scatter + Treffsicherheit nach SP --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('status.estimated_vs_actual') }}</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('status.files_per_task_diagonal_perfect_estimate') }}</p>
                @if (count($scatter['points']) > 0)
                    <svg viewBox="0 0 340 240" class="mt-3 w-full" font-family="ui-sans-serif, system-ui">
                        {{-- Gitter + Achsenbeschriftung --}}
                        @for ($t = 0; $t <= $ax; $t += 10)
                            <line x1="{{ $sx($t) }}" y1="{{ $sy(0) }}" x2="{{ $sx($t) }}" y2="{{ $sy($ax) }}" stroke="#f1f5f9" stroke-width="1"/>
                            <line x1="{{ $sx(0) }}" y1="{{ $sy($t) }}" x2="{{ $sx($ax) }}" y2="{{ $sy($t) }}" stroke="#f1f5f9" stroke-width="1"/>
                            <text x="{{ $sx($t) }}" y="234" fill="#9ca3af" font-size="9" text-anchor="middle">{{ $t }}</text>
                            <text x="24" y="{{ $sy($t) + 3 }}" fill="#9ca3af" font-size="9" text-anchor="end">{{ $t }}</text>
                        @endfor
                        {{-- Diagonale = perfekte Schätzung --}}
                        <line x1="{{ $sx(0) }}" y1="{{ $sy(0) }}" x2="{{ $sx($ax) }}" y2="{{ $sy($ax) }}" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="4 4"/>
                        {{-- Achsen-Titel --}}
                        <text x="{{ $sx($ax / 2) }}" y="240" fill="#9ca3af" font-size="9" text-anchor="middle">{{ __('status.estimated') }}</text>
                        <text x="10" y="{{ $sy($ax / 2) }}" fill="#9ca3af" font-size="9" text-anchor="middle" transform="rotate(-90 10 {{ $sy($ax / 2) }})">{{ __('status.changed') }}</text>
                        {{-- Punkte --}}
                        @foreach ($scatter['points'] as $p)
                            @if ($p['hit'])
                                <circle cx="{{ $sx($p['x']) }}" cy="{{ $sy($p['y']) }}" r="4" fill="#16a34a" fill-opacity="0.85"><title>{{ $p['name'] }}: {{ $p['x'] }} → {{ $p['y'] }}</title></circle>
                            @else
                                <rect x="{{ $sx($p['x']) - 4 }}" y="{{ $sy($p['y']) - 4 }}" width="8" height="8" fill="#ef4444" fill-opacity="0.8"><title>{{ $p['name'] }}: {{ $p['x'] }} → {{ $p['y'] }}</title></rect>
                            @endif
                        @endforeach
                    </svg>
                @else
                    <p class="mt-6 text-sm text-gray-400 dark:text-gray-500">{{ __('status.no_tasks_with_a_file_estimate') }}</p>
                @endif
            </div>

            <div class="rounded-lg bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('status.accuracy_by_sp') }}</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('status.share_within_25') }}</p>
                @if (count($spAccuracy) > 0)
                    <div class="mt-4 space-y-3">
                        @foreach ($spAccuracy as $g)
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $g['label'] }}</span>
                                    <span class="text-gray-400 dark:text-gray-500">{{ $g['hits'] }}/{{ $g['total'] }}</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    @if ($g['pct'] > 0)
                                        <div class="h-full bg-green-500" style="width: {{ $g['pct'] }}%"></div>
                                    @else
                                        <div class="h-full bg-red-400" style="width: 5%"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($tip)
                        <p class="mt-4 flex items-start gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2h6c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/></svg>
                            <span>{{ $tip }}</span>
                        </p>
                    @endif
                @else
                    <p class="mt-6 text-sm text-gray-400 dark:text-gray-500">{{ __('status.no_tasks_with_a_file_estimate') }}</p>
                @endif
            </div>
        </div>

        {{-- Tabs + Sortierung --}}
        <div id="calib-list" class="flex flex-wrap items-center justify-between gap-3 scroll-mt-6">
            <div class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 p-1">
                @foreach (['all' => __('common.all'), 'outliers' => __('status.outliers_only'), 'noEstimate' => __('status.no_estimate'), 'grouped' => __('status.grouped_by_sp')] as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'"
                            class="rounded-full px-3 py-1 text-sm font-medium"
                            :class="tab === '{{ $key }}' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400" x-show="tab !== 'grouped'">
                {{ __('status.sort') }}
                <select x-model="sort" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 py-1 text-sm">
                    <option value="dev">{{ __('status.deviation') }}</option>
                    <option value="sp">{{ __('common.story_points') }}</option>
                    <option value="date">{{ __('status.date') }}</option>
                    <option value="time">{{ __('status.time_sp') }}</option>
                </select>
            </label>
        </div>

        {{-- Tabelle (Alle / Ausreißer / Ohne Schätzung) --}}
        <div x-show="tab !== 'grouped'" class="overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700 text-left text-xs text-gray-400 dark:text-gray-500">
                        <th class="px-4 py-2 font-medium">{{ __('status.task') }}</th>
                        <th class="px-4 py-2 font-medium">SP</th>
                        <th class="px-4 py-2 font-medium">{{ __('status.files_estimated_changed') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('status.deviation') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ __('status.time_sp') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in list" :key="row.name">
                        <tr class="border-b border-gray-50 dark:border-gray-700 last:border-0">
                            <td class="px-4 py-3">
                                <a :href="row.url" x-text="row.name" class="font-mono font-semibold text-indigo-700 dark:text-indigo-400 hover:underline"></a>
                                <div class="text-xs text-gray-400 dark:text-gray-500"><span x-text="row.dateShort"></span> · <span x-text="row.meta"></span></div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" x-text="row.sp ?? '—'"></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-gray-600 dark:text-gray-400" x-text="(row.filesEstimated ?? '—') + ' → ' + row.filesActual"></span>
                                    <template x-if="row.hasEstimate">
                                        <span class="inline-block h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                                            <span class="block h-full" :class="row.barClass" :style="'width:' + row.barWidth + '%'"></span>
                                        </span>
                                    </template>
                                    <template x-if="!row.hasEstimate">
                                        <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('status.no_estimate_2') }}</span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <template x-if="row.hasEstimate">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="row.pillClass" x-text="row.deviationLabel"></span>
                                </template>
                                <template x-if="!row.hasEstimate"><span class="text-gray-300 dark:text-gray-600">—</span></template>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400" x-text="row.timePerSp"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p x-show="list.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('status.no_entries') }}</p>
        </div>

        {{-- Nach SP gruppiert --}}
        <div x-show="tab === 'grouped'" x-cloak class="overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700 text-left text-xs text-gray-400 dark:text-gray-500">
                        <th class="px-4 py-2 font-medium">SP</th>
                        <th class="px-4 py-2 font-medium">{{ __('status.avg_to_merge') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('common.tasks') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr class="border-b border-gray-50 dark:border-gray-700 align-top last:border-0">
                            <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">{{ $group['storyPoints'] }} SP</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">Ø {{ number_format($group['avgDuration'], 1, ',', '') }} {{ __('status.days') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($group['rows'] as $r)
                                        <a href="{{ $r['url'] }}" class="rounded bg-gray-50 dark:bg-gray-800/50 px-2 py-0.5 font-mono text-xs text-indigo-700 dark:text-indigo-400 ring-1 ring-gray-100 dark:ring-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">{{ $r['name'] }}</a>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($groups->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('status.no_entries') }}</p>
            @endif
        </div>
    </div>
</x-status-shell>
