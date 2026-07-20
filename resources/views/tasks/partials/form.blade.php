@php
    $task = $task ?? null;
    $candidates = $candidates ?? collect();
    $selected = collect(old('prerequisites', $selected ?? []))->map(fn ($v) => (int) $v)->all();
    $statuses = \App\Enums\TaskStatus::cases();
@endphp

<div class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="name" :value="__('tasks.short_code_e_g_c23')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $task?->name)" required maxlength="50" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="status" :value="__('common.status')" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}"
                        @selected(old('status', $task?->status?->value ?? 'UNKNOWN') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="summary" :value="__('common.summary_2')" />
        <x-text-input id="summary" name="summary" type="text" class="mt-1 block w-full"
                      :value="old('summary', $task?->summary)" required maxlength="255" />
        <x-input-error :messages="$errors->get('summary')" class="mt-2" />
    </div>

    <div class="sm:w-60">
        <x-input-label for="criticality" :value="__('tasks.criticality')" />
        <select id="criticality" name="criticality" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">—</option>
            @foreach (\App\Enums\Criticality::cases() as $crit)
                <option value="{{ $crit->value }}" @selected(old('criticality', $task?->criticality?->value) === $crit->value)>{{ $crit->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('criticality')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" :value="__('common.description')" />
        <textarea id="description" name="description" rows="5"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description_acceptance_criteria" :value="__('common.acceptance_criteria')" />
        <textarea id="description_acceptance_criteria" name="description_acceptance_criteria" rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description_acceptance_criteria', $task?->description_acceptance_criteria) }}</textarea>
        <x-input-error :messages="$errors->get('description_acceptance_criteria')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description_target_actual" :value="__('tasks.actual_target_comparison')" />
        <textarea id="description_target_actual" name="description_target_actual" rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="{{ __('tasks.actual_behavior_before_the_task_target') }}">{{ old('description_target_actual', $task?->description_target_actual) }}</textarea>
        <p class="mt-1 text-xs text-gray-400">{{ __('tasks.an_easy_to_understand_before_after') }}</p>
        <x-input-error :messages="$errors->get('description_target_actual')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description_test_cases" :value="__('tasks.test_cases_test_instructions')" />
        <textarea id="description_test_cases" name="description_test_cases" rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="{{ __('tasks.step_by_step_instructions_for_how_the') }}">{{ old('description_test_cases', $task?->description_test_cases) }}</textarea>
        <p class="mt-1 text-xs text-gray-400">{{ __('tasks.for_humans_how_can_the_result_of_the_pr') }}</p>
        <x-input-error :messages="$errors->get('description_test_cases')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <x-input-label for="phase_id" :value="__('tasks.phase')" />
            <select id="phase_id" name="phase_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">—</option>
                @foreach ($project->phases as $phase)
                    <option value="{{ $phase->id }}" @selected(old('phase_id', $task?->phase_id) == $phase->id)>{{ $phase->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('phase_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="effort_man_days" :value="__('tasks.person_days')" />
            <x-text-input id="effort_man_days" name="effort_man_days" type="number" min="0" step="0.1" class="mt-1 block w-full"
                          :value="old('effort_man_days', $task?->effort_man_days)" />
            <x-input-error :messages="$errors->get('effort_man_days')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="effort_story_points" :value="__('common.story_points')" />
            <x-text-input id="effort_story_points" name="effort_story_points" type="number" min="0" class="mt-1 block w-full"
                          :value="old('effort_story_points', $task?->effort_story_points)" />
            <x-input-error :messages="$errors->get('effort_story_points')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="effort_tokens" :value="__('tasks.tokens_estimated')" />
            <x-text-input id="effort_tokens" name="effort_tokens" type="number" min="0" class="mt-1 block w-full"
                          :value="old('effort_tokens', $task?->effort_tokens)" />
            <x-input-error :messages="$errors->get('effort_tokens')" class="mt-2" />
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="affected_files" :value="__('tasks.affected_files_estimated')" />
            <x-text-input id="affected_files" name="affected_files" type="number" min="0" class="mt-1 block w-full sm:w-40"
                          :value="old('affected_files', $task?->affected_files)" />
            <p class="mt-1 text-xs text-gray-400">{{ __('tasks.always_provide_this_an_estimate_is') }}</p>
            <x-input-error :messages="$errors->get('affected_files')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="pr_number" :value="__('tasks.pr_number')" />
            <x-text-input id="pr_number" name="pr_number" type="number" min="1" class="mt-1 block w-full sm:w-40"
                          :value="old('pr_number', $task?->pr_number)" />
            <x-input-error :messages="$errors->get('pr_number')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="reviewed_by" :value="__('tasks.reviewed_by')" />
        <select id="reviewed_by" name="reviewed_by" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">—</option>
            @foreach ($project->accessUsers() as $member)
                <option value="{{ $member->id }}"
                    @selected((int) old('reviewed_by', $task?->reviewed_by) === $member->id)>
                    {{ $member->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('reviewed_by')" class="mt-2" />
    </div>

    @if (($task?->status ?? null) === \App\Enums\TaskStatus::IN_REVIEW)
        <div class="rounded-md border border-purple-100 bg-purple-50/40 p-4 space-y-4">
            <p class="text-sm font-semibold text-purple-800">{{ __('tasks.review_result') }}</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="last_review_recommendation" :value="__('tasks.recommendation')" />
                    <select id="last_review_recommendation" name="last_review_recommendation"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        @foreach (\App\Enums\ReviewRecommendation::cases() as $rec)
                            <option value="{{ $rec->value }}"
                                @selected(old('last_review_recommendation', $task?->last_review_recommendation?->value) === $rec->value)>
                                {{ $rec->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('last_review_recommendation')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="last_reviewed_at" :value="__('tasks.last_reviewed_on')" />
                    <x-text-input id="last_reviewed_at" name="last_reviewed_at" type="datetime-local" class="mt-1 block w-full"
                                  :value="old('last_reviewed_at', $task?->last_reviewed_at?->format('Y-m-d\TH:i'))" />
                    <x-input-error :messages="$errors->get('last_reviewed_at')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="last_review_summary" :value="__('tasks.review_analysis_tldr_first_then_detailed')" />
                <textarea id="last_review_summary" name="last_review_summary" rows="10"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"
                          placeholder="{{ __('tasks.tldr_2') }}&#10;&#10;{{ __('tasks.detailed_analysis') }}">{{ old('last_review_summary', $task?->last_review_summary) }}</textarea>
                <x-input-error :messages="$errors->get('last_review_summary')" class="mt-2" />
            </div>
        </div>
    @endif

    @if ($candidates->isNotEmpty())
        <div>
            <x-input-label :value="__('tasks.prerequisites_requirements')" />
            <div class="mt-2 grid gap-2 sm:grid-cols-2 max-h-56 overflow-y-auto rounded-md border border-gray-200 p-3">
                @foreach ($candidates as $candidate)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="prerequisites[]" value="{{ $candidate->id }}"
                               class="rounded border-gray-300 text-indigo-600"
                               @checked(in_array($candidate->id, $selected, true))>
                        <span class="font-mono text-indigo-700">{{ $candidate->name }}</span>
                        <span class="text-gray-500 truncate">{{ $candidate->summary }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('prerequisites')" class="mt-2" />
        </div>
    @endif
</div>
