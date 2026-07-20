@props(['project', 'active'])

@php
    $tabs = [
        'general' => ['label' => __('common.general'), 'route' => 'projects.edit'],
        'phases' => ['label' => __('common.phases'), 'route' => 'projects.phases.index'],
        'claude' => ['label' => 'Claude', 'route' => 'projects.claude.edit'],
        'access' => ['label' => __('common.access'), 'route' => 'projects.access'],
    ];
@endphp

<nav class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
    @foreach ($tabs as $key => $tab)
        <a href="{{ route($tab['route'], $project) }}"
           class="{{ $loop->first ? 'pr-4' : 'px-4' }} py-2 text-sm font-medium border-b-2 -mb-px
                  {{ $active === $key
                      ? 'border-gray-800 dark:border-gray-100 text-gray-800 dark:text-gray-100'
                      : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
