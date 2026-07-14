<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Neuer Task – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.tasks.store', $project) }}">
                    @csrf
                    @include('tasks.partials.form')

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                        <x-primary-button>Task anlegen</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
