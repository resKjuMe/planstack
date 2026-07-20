@props(['project', 'active'])

@php
    $tabs = [
        'general' => ['label' => 'Allgemein', 'route' => 'projects.edit'],
        'phases' => ['label' => 'Phasen', 'route' => 'projects.phases.index'],
        'claude' => ['label' => 'Claude', 'route' => 'projects.claude.edit'],
        'access' => ['label' => 'Zugriff', 'route' => 'projects.access'],
    ];
@endphp

<nav class="flex gap-1 border-b border-gray-200">
    @foreach ($tabs as $key => $tab)
        <a href="{{ route($tab['route'], $project) }}"
           class="{{ $loop->first ? 'pr-4' : 'px-4' }} py-2 text-sm font-medium border-b-2 -mb-px
                  {{ $active === $key
                      ? 'border-gray-800 text-gray-800'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
