{{-- Verlauf-Timeline (best-effort): Ereignisse aus Timestamps + die aus der
     Beschreibung herausgelösten Absätze ($events). Chronologisch, datumslose
     Einträge ans Ende. --}}
@props(['task', 'events' => []])

@php
    $timeline = collect();
    $timeline->push(['when' => $task->created_at, 'title' => 'Angelegt', 'body' => $task->creator?->name]);
    if ($task->claimed_at) {
        $timeline->push(['when' => $task->claimed_at, 'title' => 'Beansprucht', 'body' => $task->claimer?->name]);
    }
    if ($task->concern) {
        $timeline->push(['when' => $task->concern->created_at, 'title' => 'Concern gemeldet', 'body' => $task->concern->creator?->name]);
    }
    if ($task->last_reviewed_at) {
        $revDetail = trim(($task->last_review_recommendation?->label() ?? 'reviewt').($task->reviewer ? ' · '.$task->reviewer->name : ''));
        $timeline->push(['when' => $task->last_reviewed_at, 'title' => 'Reviewt', 'body' => $revDetail]);
    }
    if ($task->merged_at) {
        $timeline->push(['when' => $task->merged_at, 'title' => 'Gemerged', 'body' => null]);
    }
    foreach ($events as $e) {
        $body = preg_replace('/^\**\s*'.preg_quote($e['label'], '/').'\b\s*:?\s*\**\s*/iu', '', $e['text']);
        $timeline->push(['when' => $e['date'] ?? null, 'title' => $e['label'], 'body' => trim((string) $body) ?: null]);
    }

    $timeline = $timeline
        ->sortBy(fn ($e) => optional($e['when'])->timestamp ?? PHP_INT_MAX)
        ->values();
@endphp

@if ($timeline->isNotEmpty())
<section class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-gray-900 mb-4">Verlauf</h3>
    <ol class="relative space-y-4 border-l border-gray-200 pl-5">
        @foreach ($timeline as $e)
            <li class="relative">
                <span class="absolute -left-[1.4rem] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-gray-300"></span>
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span class="text-sm font-medium text-gray-800">{{ $e['title'] }}</span>
                    <span class="text-xs text-gray-400">{{ optional($e['when'])->format('d.m.Y H:i') ?? '' }}</span>
                </div>
                @if ($e['body'])
                    <p class="text-sm text-gray-600">{{ $e['body'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
</section>
@endif
