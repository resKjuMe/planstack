@props(['project'])

<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <a href="{{ route('projects.show', $project) }}"
           class="inline-flex items-center rounded bg-gray-800 px-2.5 py-1 text-sm font-mono font-semibold text-white hover:bg-gray-700">
            {{ $project->alias }}
        </a>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2>
    </div>
    <x-project-actions :project="$project" />
</div>
