@php use App\Enums\TaskStatus; @endphp
<x-status-shell :project="$project" :active="$active" :bare="true">
    @php
        // Inline-Icons (Tabler Outline, 24er-ViewBox); Größe bestimmt der Aufrufer.
        $ic = function (string $name, string $cls = 'h-3.5 w-3.5') {
            $paths = [
                'list' => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M5 6v.01"/><path d="M5 12v.01"/><path d="M5 18v.01"/>',
                'play' => '<path d="M7 4v16l13 -8z"/>',
                'lock' => '<path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/>',
                'alert' => '<path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/>',
                'hand' => '<path d="M8 13v-7.5a1.5 1.5 0 0 1 3 0v6.5"/><path d="M11 5.5v-2a1.5 1.5 0 1 1 3 0v8.5"/><path d="M14 5.5a1.5 1.5 0 0 1 3 0v6.5"/><path d="M17 7.5a1.5 1.5 0 0 1 3 0v8.5a6 6 0 0 1 -6 6h-2h.208a6 6 0 0 1 -5.012 -2.7a69.74 69.74 0 0 1 -.196 -.3c-.312 -.479 -1.407 -2.388 -3.286 -5.728a1.5 1.5 0 0 1 .536 -2.022a1.867 1.867 0 0 1 2.28 .28l1.47 1.47"/>',
                'flame' => '<path d="M12 12c2 -2.96 0 -7 -1 -8c0 3.038 -1.773 4.741 -3 6c-1.226 1.26 -2 3.24 -2 5a6 6 0 1 0 12 0c0 -1.532 -1.056 -3.94 -2 -5c-1.786 3 -2.791 3 -4 2z"/>',
                'check' => '<path d="M5 12l5 5l10 -10"/>',
                'clock' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7v5l3 3"/>',
                'chart' => '<path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M4 20h14"/>',
                'coin' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1"/><path d="M12 7v10"/>',
                'file' => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>',
                'chevron' => '<path d="M9 6l6 6l-6 6"/>',
                'expand' => '<path d="M16 4l4 0l0 4"/><path d="M14 10l6 -6"/><path d="M8 20l-4 0l0 -4"/><path d="M4 20l6 -6"/>',
            ];

            return '<svg class="'.$cls.' shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
        };

        $isActiveFn = fn ($t) => in_array($t->x_display_status, [TaskStatus::IN_PROGRESS, TaskStatus::ANALYZING, TaskStatus::IN_REVIEW], true);
        $isBottleneckFn = fn ($t) => ($t->x_dependents ?? 0) >= 3;

        // Reihenfolge: aktiver PR zuerst, dann Flaschenhälse (absteigend nach
        // blockierten PRs), dann Rest in Sequenz-Reihenfolge.
        $ordered = $tasks->sortBy(fn ($t) => sprintf('%d|%03d|%04d',
            $isActiveFn($t) ? 0 : ($isBottleneckFn($t) ? 1 : 2),
            $isBottleneckFn($t) && ! $isActiveFn($t) ? max(0, 999 - (int) ($t->x_dependents ?? 0)) : 0,
            min(9999, (int) $t->x_seq),
        ))->values();

        // Blockierte PRs ohne Flaschenhals-Status wandern ab >5 Stück hinter
        // einen Expander ans Listenende (Zustand pro Session gemerkt).
        $blockedPlain = $ordered->filter(fn ($t) => $t->x_display_status === TaskStatus::BLOCKED && ! $isBottleneckFn($t) && ! $isActiveFn($t))->values();
        $collapseBlocked = $blockedPlain->count() > 5;
        $blockedIds = $collapseBlocked ? $blockedPlain->pluck('id')->flip() : collect();
        $main = $collapseBlocked ? $ordered->reject(fn ($t) => $blockedIds->has($t->id))->values() : $ordered;

        $totalSp = (int) $tasks->sum('effort_story_points');
        $maxSp = (int) $tasks->max('effort_story_points');
        $criticalPath = $tasks->filter($isBottleneckFn)->sortBy('x_seq')->pluck('name')->implode(' → ');
    @endphp

    <div x-data="{
            filter: 'all',
            counts: {{ \Illuminate\Support\Js::from($counts) }},
            doneOpen: localStorage.getItem('ps-seq-done-open') === '1',
            blockedOpen: sessionStorage.getItem('ps-seq-blocked-open') === '1',
         }"
         x-init="$watch('doneOpen', v => localStorage.setItem('ps-seq-done-open', v ? '1' : '0'));
                 $watch('blockedOpen', v => sessionStorage.setItem('ps-seq-blocked-open', v ? '1' : '0'))">

        <x-page-head :title="__('common.pr_sequence')" class="mb-4">
            <ul class="list-disc space-y-1 ps-4">
                <li><span class="font-medium">{{ __('common.pr_sequence') }}</span>: {{ __('status.recommended_order_for_working_through') }}</li>
                <li>{{ __('status.metrics_open_prs_total_story_points') }}</li>
                <li>{{ __('status.on') }} <span class="font-medium">{{ __('status.bottleneck') }}</span> {{ __('status.blocks_many_downstream_prs_finishing') }}</li>
                <li>{{ __('status.the_filter_pills_narrow_to_pickable') }}</li>
            </ul>
        </x-page-head>

        {{-- Kennzahlen-Kacheln (Card-Stil wie „Kalibrierung") --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.open_prs') }}</div>
                <div class="mt-1 text-[22px] font-semibold leading-tight text-gray-900 dark:text-gray-100">{{ $counts['all'] }}</div>
            </div>
            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.total_story_points') }}</div>
                <div class="mt-1 text-[22px] font-semibold leading-tight text-gray-900 dark:text-gray-100">{{ $totalSp }}</div>
            </div>
            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('common.blocks') }}</div>
                <div class="mt-1 text-[22px] font-semibold leading-tight text-red-600 dark:text-red-400">{{ $counts['blocked'] }}</div>
            </div>
            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                <div class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('status.critical_path') }}</div>
                <div class="mt-1 break-words font-mono text-[15px] font-medium leading-snug text-gray-900 dark:text-gray-100">{{ $criticalPath !== '' ? $criticalPath : '—' }}</div>
            </div>
        </div>

        {{-- Filterleiste: Pills mit Icon + Zähler-Badge, rein clientseitig --}}
        @php
            $chips = [
                'all' => ['label' => __('common.all'), 'icon' => 'list', 'count' => $counts['all']],
                'pickable' => ['label' => __('status.pickable'), 'icon' => 'play', 'count' => $counts['pickable']],
                'blocked' => ['label' => __('common.blocks'), 'icon' => 'lock', 'count' => $counts['blocked']],
                'concerned' => ['label' => __('status.concerns'), 'icon' => 'alert', 'count' => $counts['concerned']],
                'claimed' => ['label' => __('status.claimed'), 'icon' => 'hand', 'count' => $counts['claimed']],
            ];
        @endphp
        <div class="mt-4 inline-flex flex-wrap items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 p-1">
            @foreach ($chips as $key => $chip)
                <button type="button" @click="filter = '{{ $key }}'"
                        :class="filter === '{{ $key }}' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium transition-colors">
                    {!! $ic($chip['icon'], 'h-3.5 w-3.5') !!}
                    {{ $chip['label'] }}
                    <span class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[11px] font-medium"
                          :class="filter === '{{ $key }}' ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300' : 'bg-white text-gray-400 dark:bg-gray-700 dark:text-gray-400'">{{ $chip['count'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Liste: gemeinsame Karte, Zeilen durch Trennlinien geteilt --}}
        <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
            @forelse ($main as $task)
                @include('status.partials.seq-row', ['task' => $task, 'inCollapse' => false])
            @empty
                <p class="px-4 py-6 text-sm text-[var(--seq-faint)]">{{ __('status.no_open_prs') }}</p>
            @endforelse

            {{-- Eingeklappte blockierte PRs ohne Flaschenhals-Status --}}
            @if ($collapseBlocked)
                <button type="button" x-show="filter === 'all'" @click="blockedOpen = !blockedOpen"
                        class="flex w-full items-center gap-2 px-4 py-3 text-xs font-medium text-[var(--seq-muted)] hover:text-[var(--seq-text)]">
                    <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="blockedOpen && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6"/></svg>
                    <span x-show="!blockedOpen">{{ __('status.show_count_more_blocked_prs', ['count' => $blockedPlain->count()]) }}</span>
                    <span x-show="blockedOpen" x-cloak>{{ __('status.hide_count_more_blocked_prs', ['count' => $blockedPlain->count()]) }}</span>
                </button>
                @foreach ($blockedPlain as $task)
                    @include('status.partials.seq-row', ['task' => $task, 'inCollapse' => true])
                @endforeach
            @endif

            {{-- Leerhinweis, wenn der aktive Filter nichts übrig lässt --}}
            @if ($tasks->isNotEmpty())
                <p x-show="filter !== 'all' && counts[filter] === 0" x-cloak
                   class="px-4 py-6 text-sm text-[var(--seq-faint)]">{{ __('status.no_prs_in_this_filter') }}</p>
            @endif
        </div>

        {{-- Abgeschlossene PRs: gedämpfter, einklappbarer Sammelblock --}}
        @if ($completed->isNotEmpty())
            <div x-show="filter === 'all'" class="mt-4">
                <button type="button" @click="doneOpen = !doneOpen"
                        class="flex items-center gap-2 text-sm font-medium text-[var(--seq-muted)] hover:text-[var(--seq-text)]">
                    <svg class="h-4 w-4 transition-transform" :class="doneOpen && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    {{ trans_choice('status.completed_prs', $completed->count(), ['count' => $completed->count()]) }}
                </button>
                <div x-show="doneOpen" x-cloak class="mt-2 flex flex-wrap gap-x-3 gap-y-1.5">
                    @foreach ($completed as $task)
                        <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                           class="inline-flex items-center gap-1 font-mono text-xs text-[var(--seq-muted)] hover:text-[var(--seq-text)] hover:underline">
                            {{ $task->name }}@if ($task->pr_number)<span class="text-[var(--seq-faint)]">#{{ $task->pr_number }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-status-shell>
