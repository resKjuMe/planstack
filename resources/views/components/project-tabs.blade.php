@props(['project', 'active'])

@php
    $tabs = [
        'diagram' => ['label' => __('common.diagram'), 'route' => 'projects.diagram'],
        'pr-sequence' => ['label' => __('common.pr_sequence'), 'route' => 'projects.pr-sequence'],
        'summary' => ['label' => __('common.summary'), 'route' => 'projects.summary'],
        'board' => ['label' => __('common.board'), 'route' => 'projects.show'],
        'changelog' => ['label' => __('common.changelog'), 'route' => 'projects.changelog'],
        'calibration' => ['label' => __('common.calibration'), 'route' => 'projects.calibration'],
    ];
@endphp

<nav class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
    @foreach ($tabs as $key => $tab)
        <a href="{{ ($tab['global'] ?? false) ? route($tab['route']) : route($tab['route'], $project) }}"
           class="{{ $loop->first ? 'pr-4' : 'px-4' }} {{ ($tab['right'] ?? false) ? 'ms-auto' : '' }} py-2 text-sm font-medium border-b-2 -mb-px
                  {{ $active === $key
                      ? 'border-gray-800 dark:border-gray-100 text-gray-800 dark:text-gray-100'
                      : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
