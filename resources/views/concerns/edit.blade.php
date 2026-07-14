@php $c = $task->concern; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Concern – <span class="font-mono">{{ $project->alias }}/{{ $task->name }}</span>
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
                        <x-input-label for="summary" value="Zusammenfassung" />
                        <x-text-input id="summary" name="summary" type="text" class="mt-1 block w-full"
                                      :value="old('summary', $c?->summary)" required maxlength="255" />
                        <x-input-error :messages="$errors->get('summary')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description_context" value="Kontext (gesammelte Hintergründe)" />
                        <textarea id="description_context" name="description_context" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description_context', $c?->description_context) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="description_blocker" value="Blocker (weshalb es blockiert)" />
                        <textarea id="description_blocker" name="description_blocker" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description_blocker', $c?->description_blocker) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="description_misconception" value="Fehleinschätzung (weshalb die Planung falsch war)" />
                        <textarea id="description_misconception" name="description_misconception" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description_misconception', $c?->description_misconception) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="description_decisions" value="Offene Entscheidungen" />
                        <textarea id="description_decisions" name="description_decisions" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm font-mono text-sm">{{ old('description_decisions', $c?->description_decisions) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">1 Entscheidung pro Zeile, Optionen als CSV mit ";" – z.B. <code>Welchen Weg gehen?;Option A;Option B</code></p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                        <x-primary-button>Speichern</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
