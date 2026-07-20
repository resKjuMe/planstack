{{-- Meta-Daten als Chips. Leere Werte werden komplett weggelassen (kein „– PT"). --}}
@php $repo = $project->githubRepo(); @endphp
<div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1">
        <span class="text-gray-400">{{ __('tasks.creator') }}</span>
        <span class="font-medium text-gray-700">{{ $task->creator?->name ?? '—' }}</span>
    </span>

    @if ($task->claimer)
        @if ($task->claimed_by_id === $task->created_by_id)
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">{{ __('tasks.self_claimed') }}</span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1">
                <span class="text-gray-400">{{ __('tasks.claimed') }}</span>
                <span class="font-medium text-gray-700">{{ $task->claimer->name }}</span>
            </span>
        @endif
    @endif

    @if ($task->phase)
        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1">
            <span class="text-gray-400">{{ __('tasks.phase') }}</span>
            <span class="font-medium text-gray-700">{{ $task->phase->name }}</span>
        </span>
    @endif

    @if ($task->effort_story_points !== null)
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">{{ __('tasks.points_sp', ['points' => $task->effort_story_points]) }}</span>
    @endif
    @if ($task->effort_man_days !== null)
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">{{ __('tasks.days_pd', ['days' => (float) $task->effort_man_days]) }}</span>
    @endif
    @if ($task->effort_tokens !== null)
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">{{ __('tasks.count_tok', ['count' => number_format($task->effort_tokens, 0, ',', '.')]) }}</span>
    @endif
    @if ($task->affected_files !== null)
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">{{ __('tasks.count_files', ['count' => $task->affected_files]) }}</span>
    @endif

    @if ($task->pr_number)
        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1">
            <span class="text-gray-400">{{ __('tasks.pr') }}</span>
            @if ($repo)
                <a href="https://github.com/{{ $repo }}/pull/{{ $task->pr_number }}" target="_blank" rel="noopener"
                   class="font-mono font-medium text-indigo-700 hover:underline">#{{ $task->pr_number }}</a>
            @else
                <span class="font-mono font-medium text-gray-700">#{{ $task->pr_number }}</span>
            @endif
        </span>
    @endif
</div>
