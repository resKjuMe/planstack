{{-- Sidebar: Voraussetzungen (Gates, mit Erledigt-Icon) und Blockiert.
     Nur Task-ID + Einzeiler als Link; Langtext lebt im Ziel-Task. Leere Listen
     als kleine Zeile statt voller Karte. --}}
@props(['task', 'project'])

@php
    $doneStatuses = [\App\Enums\TaskStatus::COMPLETED, \App\Enums\TaskStatus::MERGED];
@endphp

<div class="bg-white rounded-lg shadow p-5">
    <h3 class="mb-3 text-sm font-semibold text-gray-900">Voraussetzungen</h3>
    @forelse ($task->prerequisites as $pre)
        @php $preDone = in_array($pre->status, $doneStatuses, true); @endphp
        <a href="{{ route('projects.tasks.show', [$project, $pre]) }}" class="group flex items-start gap-2 py-1">
            @if ($preDone)
                <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5l10 -10"/></svg>
            @else
                <span class="mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 border-gray-300"></span>
            @endif
            <span class="min-w-0">
                <span class="font-mono text-xs font-semibold text-indigo-700 group-hover:underline">{{ $pre->name }}</span>
                <span class="block truncate text-xs text-gray-500">{{ $pre->summary }}</span>
            </span>
        </a>
    @empty
        <p class="text-xs text-gray-400">Keine.</p>
    @endforelse
</div>

<div class="bg-white rounded-lg shadow p-5">
    <h3 class="mb-3 text-sm font-semibold text-gray-900">Blockiert</h3>
    @forelse ($task->dependents as $dep)
        <a href="{{ route('projects.tasks.show', [$project, $dep]) }}" class="group flex items-start gap-2 py-1">
            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300"></span>
            <span class="min-w-0">
                <span class="font-mono text-xs font-semibold text-indigo-700 group-hover:underline">{{ $dep->name }}</span>
                <span class="block truncate text-xs text-gray-500">{{ $dep->summary }}</span>
            </span>
        </a>
    @empty
        <p class="text-xs text-gray-400">Keine.</p>
    @endforelse
</div>
