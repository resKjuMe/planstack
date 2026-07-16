@php
    $task = $task ?? null;
    $candidates = $candidates ?? collect();
    $selected = collect(old('prerequisites', $selected ?? []))->map(fn ($v) => (int) $v)->all();
    $statuses = \App\Enums\TaskStatus::cases();
@endphp

<div class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="name" value="Kürzel (z.B. C23)" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $task?->name)" required maxlength="50" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="status" value="Status" />
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
        <x-input-label for="summary" value="Zusammenfassung" />
        <x-text-input id="summary" name="summary" type="text" class="mt-1 block w-full"
                      :value="old('summary', $task?->summary)" required maxlength="255" />
        <x-input-error :messages="$errors->get('summary')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Beschreibung" />
        <textarea id="description" name="description" rows="5"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description_acceptance_criteria" value="Akzeptanzkriterien" />
        <textarea id="description_acceptance_criteria" name="description_acceptance_criteria" rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description_acceptance_criteria', $task?->description_acceptance_criteria) }}</textarea>
        <x-input-error :messages="$errors->get('description_acceptance_criteria')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <x-input-label for="phase_id" value="Phase" />
            <select id="phase_id" name="phase_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">—</option>
                @foreach ($project->phases as $phase)
                    <option value="{{ $phase->id }}" @selected(old('phase_id', $task?->phase_id) == $phase->id)>{{ $phase->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('phase_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="effort_man_days" value="Personentage" />
            <x-text-input id="effort_man_days" name="effort_man_days" type="number" min="0" step="0.1" class="mt-1 block w-full"
                          :value="old('effort_man_days', $task?->effort_man_days)" />
            <x-input-error :messages="$errors->get('effort_man_days')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="effort_story_points" value="Story Points" />
            <x-text-input id="effort_story_points" name="effort_story_points" type="number" min="0" class="mt-1 block w-full"
                          :value="old('effort_story_points', $task?->effort_story_points)" />
            <x-input-error :messages="$errors->get('effort_story_points')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="effort_tokens" value="Tokens (geschätzt)" />
            <x-text-input id="effort_tokens" name="effort_tokens" type="number" min="0" class="mt-1 block w-full"
                          :value="old('effort_tokens', $task?->effort_tokens)" />
            <x-input-error :messages="$errors->get('effort_tokens')" class="mt-2" />
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="affected_files" value="Betroffene Dateien (geschätzt)" />
            <x-text-input id="affected_files" name="affected_files" type="number" min="0" class="mt-1 block w-full sm:w-40"
                          :value="old('affected_files', $task?->affected_files)" />
            <x-input-error :messages="$errors->get('affected_files')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="pr_number" value="PR-Nummer" />
            <x-text-input id="pr_number" name="pr_number" type="number" min="1" class="mt-1 block w-full sm:w-40"
                          :value="old('pr_number', $task?->pr_number)" />
            <x-input-error :messages="$errors->get('pr_number')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="reviewed_by" value="Reviewed by" />
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

    @if ($candidates->isNotEmpty())
        <div>
            <x-input-label value="Voraussetzungen (Requirements)" />
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
