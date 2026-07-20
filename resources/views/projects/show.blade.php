<x-app-layout>
    <x-slot name="header">
        <x-project-header-bar :project="$project" />
    </x-slot>

    <x-slot name="subheader">
        <x-project-tabs :project="$project" active="board" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-page-head :title="__('common.board')">
                <ul class="list-disc space-y-1 ps-4">
                    <li><span class="font-medium">{{ __('common.board') }}</span>: {{ __('projects.all_tasks_of_the_project_by_status_in') }}</li>
                    <li>{{ __('projects.each_card_shows_the_task_key_summary') }}</li>
                    <li><span class="font-medium">{{ __('projects.claim_release') }}</span>: {{ __('projects.claim_a_task_or_release_it_again') }}</li>
                    <li>{{ __('projects.who_has_access_and_which_role_applies') }}</li>
                </ul>
            </x-page-head>

            @if ($project->description)
                <p class="text-sm text-gray-600 dark:text-gray-400 max-w-3xl">{{ $project->description }}</p>
            @endif

            {{-- Kanban-Board (React). Der komplette Zustand kommt server-gerendert
                 aus BoardPresenter::payload(); die App hydriert daraus und spricht
                 für Statuswechsel den board-move-Endpunkt an. --}}
            <div id="board-root"></div>
            <script>
                window.__PLANSTACK_BOARD__ = @json($boardData);
            </script>
            @vite('resources/js/board/index.jsx')
        </div>
    </div>
</x-app-layout>
