<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('common.teams') }}</h2>
            <a href="{{ route('teams.create') }}"
               class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + {{ __('teams.new_team') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-flash />

            @if ($teams->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    {{ __('teams.no_teams_yet_create_a_team_and_add') }}
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($teams as $team)
                        <a href="{{ route('teams.show', $team) }}"
                           class="block bg-white rounded-lg shadow hover:shadow-md transition p-5">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">{{ $team->name }}</h3>
                                <span class="text-xs text-gray-400">{{ __('common.count_members', ['count' => $team->members_count]) }}</span>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">{{ __('teams.creator_2') }} {{ $team->owner?->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
