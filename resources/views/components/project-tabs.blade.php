@props(['project', 'active'])

@php
    $tabs = [
        'diagram' => ['label' => 'Diagramm', 'route' => 'projects.diagram'],
        'pr-sequence' => ['label' => 'PR-Sequenz', 'route' => 'projects.pr-sequence'],
        'summary' => ['label' => 'Summary', 'route' => 'projects.summary'],
        'board' => ['label' => 'Board', 'route' => 'projects.show'],
        'changelog' => ['label' => 'Changelog', 'route' => 'projects.changelog'],
        'calibration' => ['label' => 'Kalibrierung', 'route' => 'projects.calibration'],
        'access' => ['label' => 'Zugriff', 'route' => 'projects.access', 'right' => true],
    ];
@endphp

<nav class="flex gap-1 border-b border-gray-200">
    @foreach ($tabs as $key => $tab)
        <a href="{{ route($tab['route'], $project) }}"
           class="{{ $loop->first ? 'pr-4' : 'px-4' }} {{ ($tab['right'] ?? false) ? 'ms-auto' : '' }} py-2 text-sm font-medium border-b-2 -mb-px
                  {{ $active === $key
                      ? 'border-gray-800 text-gray-800'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
