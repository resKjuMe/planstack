@props(['active'])

@php
    // Subnavigation der Organisations-Seiten — optisch identisch zu den
    // Projekt-Tabs (x-project-edit-tabs). Die Status-/Event-Tabs sind nur fuer
    // Gruender sichtbar (die Seiten selbst sind ebenfalls gruender-geschuetzt).
    $user = auth()->user();
    $organization = $user?->organization;
    $isOwner = $organization && $organization->isOwner($user);

    $tabs = [
        'organization' => ['label' => __('common.organization'), 'route' => 'organization.index'],
    ];
    if ($isOwner) {
        $tabs['statuses'] = ['label' => __('board_admin.statuses'), 'route' => 'organization.statuses.index'];
        $tabs['events'] = ['label' => __('events.nav'), 'route' => 'organization.events.index'];
        $tabs['custom-fields'] = ['label' => __('custom_fields.nav'), 'route' => 'organization.custom-fields.index'];
    }
@endphp

<nav class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
    @foreach ($tabs as $key => $tab)
        <a href="{{ route($tab['route']) }}"
           class="{{ $loop->first ? 'pr-4' : 'px-4' }} py-2 text-sm font-medium border-b-2 -mb-px
                  {{ $active === $key
                      ? 'border-gray-800 dark:border-gray-100 text-gray-800 dark:text-gray-100'
                      : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
