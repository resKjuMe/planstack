@php
    $borderClass = match ($row['deviationClass']) {
        'green' => 'border-emerald-500',
        'amber' => 'border-amber-500',
        'red' => 'border-red-500',
        default => 'border-gray-200',
    };
    $textClass = match ($row['deviationClass']) {
        'green' => 'text-emerald-600',
        'amber' => 'text-amber-600',
        'red' => 'text-red-600',
        default => 'text-gray-400',
    };
@endphp

<div class="flex items-start gap-3 border-l-4 {{ $borderClass }} pl-3 py-3">
    <div class="min-w-0 flex-1">
        <div class="text-sm text-gray-800">
            <span class="font-semibold text-gray-900">{{ $row['name'] }}</span>
            @if ($row['storyPoints'])
                · {{ $row['storyPoints'] }} SP
            @endif
            · gemerged {{ $row['mergedAt']?->format('d.m.') }}
        </div>
        <div class="mt-0.5 text-sm font-medium text-gray-900">
            Dateien: {{ $row['filesEstimated'] }} geschätzt → {{ $row['filesActual'] }} geändert
            @if ($row['deviationLabel'])
                <span class="{{ $textClass }}">{{ $row['deviationLabel'] }}</span>
            @endif
        </div>
        <div class="mt-0.5 text-xs text-gray-400">
            {{ $row['durationLabel'] ?? '—' }} bis Merge
            · +{{ $row['additions'] }}/&minus;{{ $row['deletions'] }} Zeilen
            · {{ $row['commits'] }} Commits
            · {{ $row['comments'] }} Kommentare
            · {{ $row['reviewComments'] }} Review-Comments
        </div>
    </div>
    <a href="{{ route('projects.tasks.show', [$project, $row['task']]) }}"
       class="mt-1 shrink-0 text-gray-400 hover:text-gray-600">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 6l6 6l-6 6" />
        </svg>
    </a>
</div>
