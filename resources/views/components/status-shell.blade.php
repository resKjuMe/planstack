@props(['project', 'active', 'bare' => false])

@php
    $tabs = [
        'diagram' => ['label' => 'Diagramm', 'route' => 'projects.status.diagram'],
        'pr-sequence' => ['label' => 'PR-Sequenz', 'route' => 'projects.status.pr-sequence'],
        'summary' => ['label' => 'Summary', 'route' => 'projects.status.summary'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.show', $project) }}"
                   class="inline-flex items-center rounded bg-gray-800 px-2.5 py-1 text-sm font-mono font-semibold text-white hover:bg-gray-700">
                    {{ $project->alias }}
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Status</h2>
            </div>
            <div class="flex items-center gap-3">
                @can('update', $project)
                    <form method="POST" action="{{ route('projects.sync-prs', $project) }}"
                          onsubmit="return confirm('Merge-Status aller offenen PRs von GitHub abrufen und gemergte Tasks taggen?');">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.311h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                            </svg>
                            PRs abgleichen
                        </button>
                    </form>
                @endcan
                <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">← zum Board</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <nav class="flex gap-1 border-b border-gray-200">
                @foreach ($tabs as $key => $tab)
                    <a href="{{ route($tab['route'], $project) }}"
                       class="px-4 py-2 text-sm font-medium border-b-2 -mb-px
                              {{ $active === $key
                                  ? 'border-indigo-600 text-indigo-700'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>

            <x-flash />

            @if ($bare)
                {{ $slot }}
            @else
                <div class="bg-white rounded-lg shadow p-6 overflow-x-auto">
                    {{ $slot }}
                </div>
            @endif
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .md-content h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0 0 .75rem; }
        .md-content h2 { font-size: 1.15rem; font-weight: 600; color: #1f2937; margin: 1.5rem 0 .5rem; }
        .md-content h3 { font-size: 1rem; font-weight: 600; color: #374151; margin: 1.25rem 0 .5rem; }
        .md-content p { color: #4b5563; font-size: .875rem; margin: .5rem 0; line-height: 1.5; }
        .md-content a { color: #4338ca; text-decoration: underline; }
        .md-content code { background: #f3f4f6; padding: .05rem .3rem; border-radius: .25rem; font-size: .8rem; }
        .md-content table { width: 100%; border-collapse: collapse; font-size: .8rem; margin: .5rem 0 1.5rem; }
        .md-content th, .md-content td { border: 1px solid #e5e7eb; padding: .35rem .55rem; text-align: left; vertical-align: top; }
        .md-content th { background: #f9fafb; font-weight: 600; color: #374151; white-space: nowrap; }
        .md-content tbody tr:nth-child(even) { background: #fafafa; }
        .md-content strong { color: #111827; }
        .mermaid { display: flex; justify-content: center; margin: 1rem 0; }
    </style>
</x-app-layout>
