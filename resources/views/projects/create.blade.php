<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('projects.new_project') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="alias" :value="__('projects.key_unique')" />
                        <x-text-input id="alias" name="alias" type="text" class="mt-1 block w-full"
                                      :value="old('alias')" required autofocus maxlength="20" />
                        <p class="mt-1 text-xs text-gray-400">{{ __('projects.e_g_demo_letters_numbers_and') }}</p>
                        <x-input-error :messages="$errors->get('alias')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('common.name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name')" required maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('common.description')" />
                        <textarea id="description" name="description" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="github_repo" :value="__('projects.github_repository')" />
                        <x-text-input id="github_repo" name="github_repo" type="text" class="mt-1 block w-full font-mono"
                                      :value="old('github_repo')" maxlength="255" placeholder="owner/repo" />
                        <p class="mt-1 text-xs text-gray-400">{{ __('projects.optional_format') }} <span class="font-mono">owner/repo</span> {{ __('projects.for_pr_linking_and_pr_status_sync') }}</p>
                        <x-input-error :messages="$errors->get('github_repo')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</a>
                        <x-primary-button>{{ __('common.create') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
