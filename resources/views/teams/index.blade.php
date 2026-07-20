<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Teams') }}</h2>
            <a href="{{ route('teams.create') }}"
               class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Neues Team
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-flash />

            @if ($teams->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    Noch keine Teams. Lege ein Team an und füge Kollegen per E-Mail hinzu.
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($teams as $team)
                        <a href="{{ route('teams.show', $team) }}"
                           class="block bg-white rounded-lg shadow hover:shadow-md transition p-5">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900">{{ $team->name }}</h3>
                                <span class="text-xs text-gray-400">{{ $team->members_count }} Mitglieder</span>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">Creator: {{ $team->owner?->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
