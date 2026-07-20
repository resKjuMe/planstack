<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('projects.edit_project') }} – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="general" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="alias" :value="__('projects.key_unique')" />
                        <x-text-input id="alias" name="alias" type="text" class="mt-1 block w-full"
                                      :value="old('alias', $project->alias)" required maxlength="20" />
                        <x-input-error :messages="$errors->get('alias')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('common.name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $project->name)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('common.description')" />
                        <textarea id="description" name="description" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $project->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="github_repo" :value="__('projects.github_repository')" />
                        <x-text-input id="github_repo" name="github_repo" type="text" class="mt-1 block w-full font-mono"
                                      :value="old('github_repo', $project->github_repo)" maxlength="255"
                                      placeholder="owner/repo" />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('projects.format') }} <span class="font-mono">owner/repo</span> {{ __('projects.for_pr_linking_and_the_sync_prs_button') }}</p>
                        <x-input-error :messages="$errors->get('github_repo')" class="mt-2" />
                    </div>

                    <div class="border-t border-gray-100 dark:border-gray-700 pt-5 space-y-4">
                        <label for="completed" class="flex items-start gap-3">
                            {{-- Hidden-Feld sorgt dafür, dass eine abgewählte Checkbox als 0 ankommt. --}}
                            <input type="hidden" name="completed" value="0">
                            <input id="completed" name="completed" type="checkbox" value="1"
                                   {{ old('completed', $project->completed_at) ? 'checked' : '' }}
                                   class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ __('projects.project_completed') }}
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ __('projects.shows_the_completed_badge_in_the') }}</span>
                            </span>
                        </label>

                        <label for="archived" class="flex items-start gap-3">
                            {{-- Hidden-Feld sorgt dafür, dass eine abgewählte Checkbox als 0 ankommt. --}}
                            <input type="hidden" name="archived" value="0">
                            <input id="archived" name="archived" type="checkbox" value="1"
                                   {{ old('archived', $project->archived_at) ? 'checked' : '' }}
                                   class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ __('projects.archive_project') }}
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ __('projects.hides_the_project_from_the_project_list') }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('common.cancel') }}</a>
                        <x-primary-button>{{ __('common.save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            @can('delete', $project)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-red-100 dark:border-red-900/50">
                    <h3 class="font-semibold text-red-700 dark:text-red-300">{{ __('projects.delete_project') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('projects.removes_the_project_including_all_tasks') }}</p>
                    <form method="POST" action="{{ route('projects.destroy', $project) }}" class="mt-4"
                          onsubmit="return confirm('{{ __('projects.really_delete_this_project') }}');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('common.delete') }}</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
