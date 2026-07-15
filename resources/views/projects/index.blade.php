<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Projekte') }}</h2>
            <a href="{{ route('projects.create') }}"
               class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Neues Projekt
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            @if ($projects->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    Noch keine Projekte. Lege dein erstes Projekt an.
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($projects as $project)
                        @php
                            $totalSp = (int) $project->total_sp;
                            $pct = $totalSp > 0 ? (int) $project->done_sp / $totalSp * 100 : 0;
                        @endphp
                        <a href="{{ route('projects.status.diagram', $project) }}"
                           class="block bg-white rounded-lg shadow hover:shadow-md transition p-5">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center rounded bg-gray-800 px-2 py-0.5 text-xs font-mono font-semibold text-white">
                                    {{ $project->alias }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $project->tasks_count }} Tasks · {{ $totalSp }} SP · {{ number_format($pct, 1, ',', '') }}%</span>
                            </div>
                            <h3 class="mt-3 font-semibold text-gray-900">{{ $project->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ $project->description }}</p>
                            <p class="mt-3 text-xs text-gray-400">Owner: {{ $project->owner?->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
