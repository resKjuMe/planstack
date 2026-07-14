<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Task bearbeiten – <span class="font-mono">{{ $project->alias }}/{{ $task->name }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}">
                    @csrf
                    @method('PUT')
                    @include('tasks.partials.form')

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                        <x-primary-button>Speichern</x-primary-button>
                    </div>
                </form>
            </div>

            @can('delete', $task)
                <div class="bg-white rounded-lg shadow p-6 border border-red-100">
                    <h3 class="font-semibold text-red-700">Task löschen</h3>
                    <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}" class="mt-4"
                          onsubmit="return confirm('Task wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Löschen</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
