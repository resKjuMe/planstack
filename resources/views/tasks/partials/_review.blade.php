{{-- Review-Sektion (best-effort aus vorhandenen Daten): Verdict-Badge + TLDR/
     Empfehlung als farbiges Callout oben; Konfiguration + ausführliche Analyse
     hinter Aufklapper. Strukturierte Findings/Severity + AC-Abgleich: TODO,
     folgt mit strukturierten Review-Daten (eigenes Feature). --}}
@php
    $rec = $task->last_review_recommendation;
    $isApprove = $rec === \App\Enums\ReviewRecommendation::APPROVE;
    $isChanges = $rec === \App\Enums\ReviewRecommendation::REQUEST_CHANGES;
    $calloutClass = $isApprove
        ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 text-green-900 dark:text-green-200'
        : ($isChanges ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200' : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300');
    $badgeClass = $isApprove
        ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'
        : ($isChanges ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400');

    // TLDR + Konfigurationszeile aus dem Freitext herauslösen.
    $summary = (string) $task->last_review_summary;
    $tldr = null;
    $config = null;
    if ($summary !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $summary) as $line) {
            if ($tldr === null && preg_match('/^\s*\**\s*TLDR\s*:?\s*\**\s*(.+)$/iu', $line, $m)) {
                $tldr = trim($m[1]);
            } elseif ($config === null && preg_match('/^\s*\**\s*Review-Konfiguration\s*:?\s*\**\s*(.+)$/iu', $line, $m)) {
                $config = trim($m[1]);
            }
        }
    }
@endphp

<section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-purple-100 dark:border-purple-900/50">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('tasks.review') }}</h3>
        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass }}">
            @if ($isApprove)
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5l10 -10"/></svg>
            @elseif ($isChanges)
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            @endif
            {{ $rec?->label() ?? __('tasks.pending') }}
        </span>
    </div>

    {{-- Callout: Empfehlung + TLDR --}}
    @if ($tldr || $rec)
        <div class="rounded-lg border p-4 {{ $calloutClass }}">
            @if ($tldr)
                <p class="text-sm"><span class="font-semibold">{{ __('tasks.tldr') }}</span> {{ $tldr }}</p>
            @else
                <p class="text-sm font-semibold">{{ $rec?->label() }}</p>
            @endif
        </div>
    @endif

    <dl class="mt-3 grid gap-4 sm:grid-cols-2 text-sm">
        <div><dt class="text-gray-400 dark:text-gray-500">{{ __('tasks.reviewer') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ $task->reviewer?->name ?? '—' }}</dd></div>
        <div><dt class="text-gray-400 dark:text-gray-500">{{ __('tasks.last_reviewed') }}</dt><dd class="text-gray-800 dark:text-gray-100">{{ $task->last_reviewed_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
    </dl>

    @if ($config)
        <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ $config }}</p>
    @endif

    {{-- Aufklapper: ausführliche Analyse --}}
    @if ($summary !== '')
        <div class="mt-3" x-data="disclosure({ id: 'review-analyse' })" id="review-analyse">
            <button type="button" @click="toggle()" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                <span x-text="open ? '{{ __('tasks.hide_analysis') }}' : '{{ __('tasks.show_detailed_analysis') }}'"></span>
            </button>
            <div x-show="open" x-cloak class="mt-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                <x-markdown :content="$task->last_review_summary" />
            </div>
        </div>
    @endif
</section>
