@props(['project', 'active', 'bare' => false])

<x-app-layout>
    <x-slot name="header">
        <x-project-header-bar :project="$project" />
    </x-slot>

    <x-slot name="subheader">
        <x-project-tabs :project="$project" :active="$active" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            {{-- Optionaler Inhalt über der Card (z. B. der CI-Status-Teaser der Diagramm-Seite). --}}
            {{ $beforeCard ?? '' }}

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
