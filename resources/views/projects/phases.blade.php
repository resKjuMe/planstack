<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('projects.edit_project') }} – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="phases" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-page-head :title="__('common.phases')">
                <ul class="list-disc space-y-1 ps-4">
                    <li>{{ __('projects.phases_group_tasks_into_sections_e_g') }}</li>
                    <li><span class="font-medium">{{ __('common.create') }}</span> {{ __('projects.adds_a_new_phase_at_the_end_of_the_list') }}</li>
                    <li><span class="font-medium">{{ __('projects.arrows') }}</span> {{ __('projects.change_the_order') }} <span class="font-medium">{{ __('common.edit') }}</span> {{ __('projects.rename_a_phase') }}</li>
                    <li><span class="font-medium">{{ __('common.delete') }}</span> {{ __('projects.removes_only_the_phase_the_contained') }}</li>
                </ul>
            </x-page-head>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="mb-4 font-semibold text-gray-900 dark:text-gray-100">{{ __('projects.phases_count', ['count' => $phases->count()]) }}</h3>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($phases as $phase)
                        <div class="flex items-center gap-3 py-3" x-data="{ editing: false }">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                {{ $loop->iteration }}
                            </span>

                            {{-- Anzeige-Modus --}}
                            <div class="min-w-0 flex-1" x-show="! editing">
                                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $phase->name }}</span>
                                <span class="ms-2 text-xs text-gray-400 dark:text-gray-500">
                                    {{ $phase->tasks_count }} {{ Str::plural('Task', $phase->tasks_count) }}
                                </span>
                            </div>

                            {{-- Bearbeiten-Modus (Umbenennen) --}}
                            @can('contribute', $project)
                                <form method="POST" action="{{ route('projects.phases.update', [$project, $phase]) }}"
                                      class="flex flex-1 items-center gap-2" x-show="editing" style="display:none" x-cloak>
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="name" value="{{ $phase->name }}" required maxlength="100"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           x-ref="nameInput">
                                    <x-primary-button class="!py-1.5">{{ __('common.save') }}</x-primary-button>
                                    <button type="button" @click="editing = false"
                                            class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{{ __('common.cancel') }}</button>
                                </form>
                            @endcan

                            @can('contribute', $project)
                                <div class="flex shrink-0 items-center gap-1" x-show="! editing">
                                    {{-- Nach oben --}}
                                    <form method="POST" action="{{ route('projects.phases.move', [$project, $phase]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" title="{{ __('projects.move_up') }}"
                                                class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 disabled:hover:bg-transparent"
                                                @disabled($loop->first)>
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 5a.75.75 0 01.53.22l5 5a.75.75 0 11-1.06 1.06L10 6.81l-4.47 4.47a.75.75 0 01-1.06-1.06l5-5A.75.75 0 0110 5z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </form>
                                    {{-- Nach unten --}}
                                    <form method="POST" action="{{ route('projects.phases.move', [$project, $phase]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" title="{{ __('projects.move_down') }}"
                                                class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 disabled:hover:bg-transparent"
                                                @disabled($loop->last)>
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 15a.75.75 0 01-.53-.22l-5-5a.75.75 0 111.06-1.06L10 13.19l4.47-4.47a.75.75 0 111.06 1.06l-5 5A.75.75 0 0110 15z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </form>

                                    <button type="button" @click="editing = true; $nextTick(() => $refs.nameInput.focus())"
                                            class="ms-1 inline-flex items-center py-1 text-xs leading-none text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('common.edit') }}</button>

                                    <form method="POST" action="{{ route('projects.phases.destroy', [$project, $phase]) }}"
                                          class="flex items-center"
                                          onsubmit="return confirm('{{ __('projects.delete_phase_name_the_count_contained', ['name' => $phase->name, 'count' => $phase->tasks_count]) }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ms-1 inline-flex items-center py-1 text-xs leading-none text-red-500 dark:text-red-400 hover:underline">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('projects.no_phases_created_yet') }}</p>
                    @endforelse
                </div>

                @can('contribute', $project)
                    <form method="POST" action="{{ route('projects.phases.store', $project) }}"
                          class="mt-5 border-t pt-5">
                        @csrf
                        <x-input-label for="name" :value="__('projects.new_phase')" />
                        <div class="mt-1 flex items-center gap-3">
                            <x-text-input id="name" name="name" type="text" class="block flex-1"
                                          :value="old('name')" required maxlength="100" :placeholder="__('projects.e_g_foundation')" />
                            <x-primary-button>{{ __('common.create') }}</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
