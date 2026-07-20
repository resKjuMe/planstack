<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('tasks.edit_task') }} – <span class="font-mono">{{ $project->alias }}/{{ $task->name }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}">
                    @csrf
                    @method('PUT')
                    @include('tasks.partials.form')

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('common.cancel') }}</a>
                        <x-primary-button>{{ __('common.save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            @can('delete', $task)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-red-100 dark:border-red-900/50">
                    <h3 class="font-semibold text-red-700 dark:text-red-300">{{ __('tasks.delete_task') }}</h3>
                    <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}" class="mt-4"
                          onsubmit="return confirm('{{ __('tasks.really_delete_this_task') }}');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('common.delete') }}</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
