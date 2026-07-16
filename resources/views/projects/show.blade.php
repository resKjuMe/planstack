@php
    $statuses = \App\Enums\TaskStatus::cases();
    $byStatus = $project->tasks->groupBy(fn ($t) => $t->status->value);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-project-header-bar :project="$project" />
    </x-slot>

    <x-slot name="subheader">
        <x-project-tabs :project="$project" active="board" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <x-flash />

            <x-page-head title="Board">
                <ul class="list-disc space-y-1 ps-4">
                    <li><span class="font-medium">Board</span>: alle Tasks des Projekts nach Status in Spalten (Kanban).</li>
                    <li>Jede Karte zeigt Task-Kürzel, Kurzbeschreibung, Bearbeiter und Story Points; ⚠ markiert ein gemeldetes Problem (Concern).</li>
                    <li><span class="font-medium">Beanspruchen/Freigeben</span>: einen Task übernehmen bzw. wieder freigeben.</li>
                    <li>Wer Zugriff hat und welche Rolle gilt, steht im Tab „Zugriff".</li>
                </ul>
            </x-page-head>

            @if ($project->description)
                <p class="text-sm text-gray-600 max-w-3xl">{{ $project->description }}</p>
            @endif

            {{-- Board --}}
            <div class="overflow-x-auto pb-4">
                <div class="flex gap-4 min-w-max">
                    @foreach ($statuses as $status)
                        @php $tasks = $byStatus->get($status->value, collect()); @endphp
                        <div class="w-72 shrink-0">
                            <div class="flex items-center justify-between mb-2">
                                <x-task-status :status="$status" />
                                <span class="text-xs text-gray-400">{{ $tasks->count() }}</span>
                            </div>
                            <div class="space-y-2 min-h-8">
                                @foreach ($tasks as $task)
                                    <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-100 p-3">
                                        <div class="flex items-center justify-between">
                                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                               class="font-mono text-sm font-semibold text-indigo-700 hover:underline">
                                                {{ $task->name }}
                                            </a>
                                            @if ($task->concern)
                                                <span title="Concern" class="text-orange-500 text-xs">⚠ Concern</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-gray-700">{{ $task->summary }}</p>
                                        <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                                            <span>{{ $task->claimer?->name ?? '—' }}</span>
                                            <span>
                                                @if ($task->effort_story_points) {{ $task->effort_story_points }} SP @endif
                                            </span>
                                        </div>
                                        @can('claim', $task)
                                            <form method="POST" action="{{ route('projects.tasks.claim', [$project, $task]) }}" class="mt-2">
                                                @csrf
                                                <button class="w-full rounded bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">
                                                    {{ $task->claimed_by_id ? 'Freigeben' : 'Beanspruchen' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
