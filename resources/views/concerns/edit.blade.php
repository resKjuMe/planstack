@php $c = $task->concern; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('common.concern') }} – <span class="font-mono">{{ $project->alias }}/{{ $task->name }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.tasks.concern.update', [$project, $task]) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="summary" :value="__('common.summary_2')" />
                        <x-text-input id="summary" name="summary" type="text" class="mt-1 block w-full"
                                      :value="old('summary', $c?->summary)" required maxlength="255" />
                        <x-input-error :messages="$errors->get('summary')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description_context" :value="__('concerns.context_collected_background')" />
                        <textarea id="description_context" name="description_context" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description_context', $c?->description_context) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="description_blocker" :value="__('concerns.blocker_why_it_is_blocked')" />
                        <textarea id="description_blocker" name="description_blocker" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description_blocker', $c?->description_blocker) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="description_misconception" :value="__('concerns.misconception_why_the_planning_was_wrong')" />
                        <textarea id="description_misconception" name="description_misconception" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description_misconception', $c?->description_misconception) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="description_decisions" :value="__('common.open_decisions')" />
                        <textarea id="description_decisions" name="description_decisions" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm font-mono text-sm">{{ old('description_decisions', $c?->description_decisions) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">{{ __('concerns.1_decision_per_line_options_as_csv_with') }} <code>{{ __('concerns.which_way_to_go_option_a_option_b') }}</code></p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</a>
                        <x-primary-button>{{ __('common.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
