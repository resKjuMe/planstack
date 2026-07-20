@php
    $borderClass = match ($row['deviationClass']) {
        'green' => 'border-emerald-500',
        'amber' => 'border-amber-500',
        'red' => 'border-red-500',
        default => 'border-gray-200 dark:border-gray-700',
    };
    $textClass = match ($row['deviationClass']) {
        'green' => 'text-emerald-600 dark:text-emerald-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        'red' => 'text-red-600 dark:text-red-400',
        default => 'text-gray-400 dark:text-gray-500',
    };
@endphp

<div class="flex items-start gap-3 border-l-4 {{ $borderClass }} pl-3 py-3">
    <div class="min-w-0 flex-1">
        <div class="text-sm text-gray-800 dark:text-gray-100">
            <a href="{{ route('projects.tasks.show', [$project, $row['task']]) }}"
               class="font-mono text-sm font-medium text-[var(--seq-accent)] hover:underline">{{ $row['name'] }}</a>
            @if ($row['storyPoints'])
                · {{ $row['storyPoints'] }} SP
            @endif
            · {{ __('status.merged_date', ['date' => $row['mergedAt']?->format('d.m.')]) }}
        </div>
        <div class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ __('status.files_estimated_estimated_actual_changed', ['estimated' => $row['filesEstimated'] ?? '–', 'actual' => $row['filesActual']]) }}
            @if ($row['deviationLabel'])
                <span class="{{ $textClass }}">{{ $row['deviationLabel'] }}</span>
            @endif
        </div>
        <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
            {{ $row['durationLabel'] ?? '—' }} {{ __('status.to_merge') }}
            · +{{ $row['additions'] }}/&minus;{{ $row['deletions'] }} {{ __('status.lines') }}
            · {{ $row['commits'] }} {{ __('status.commits') }}
            · {{ $row['comments'] }} {{ __('status.comments') }}
            · {{ $row['reviewComments'] }} {{ __('status.review_comments') }}
        </div>
    </div>
</div>
