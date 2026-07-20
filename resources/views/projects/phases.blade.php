<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Projekt bearbeiten – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="phases" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-page-head title="Phasen">
                <ul class="list-disc space-y-1 ps-4">
                    <li>Phasen bündeln Tasks zu Abschnitten (z. B. „Fundament", „Ausbau") und bestimmen deren Reihenfolge im Diagramm und in der Summary.</li>
                    <li><span class="font-medium">Anlegen</span> ergänzt eine neue Phase am Ende der Liste.</li>
                    <li><span class="font-medium">Pfeile</span> ändern die Reihenfolge, <span class="font-medium">Bearbeiten</span> benennt eine Phase um.</li>
                    <li><span class="font-medium">Löschen</span> entfernt nur die Phase — die enthaltenen Tasks bleiben erhalten und sind danach keiner Phase mehr zugeordnet.</li>
                </ul>
            </x-page-head>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="mb-4 font-semibold text-gray-900">Phasen ({{ $phases->count() }})</h3>

                <div class="divide-y divide-gray-100">
                    @forelse ($phases as $phase)
                        <div class="flex items-center gap-3 py-3" x-data="{ editing: false }">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">
                                {{ $loop->iteration }}
                            </span>

                            {{-- Anzeige-Modus --}}
                            <div class="min-w-0 flex-1" x-show="! editing">
                                <span class="font-medium text-gray-800">{{ $phase->name }}</span>
                                <span class="ms-2 text-xs text-gray-400">
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
                                           class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           x-ref="nameInput">
                                    <x-primary-button class="!py-1.5">Speichern</x-primary-button>
                                    <button type="button" @click="editing = false"
                                            class="whitespace-nowrap text-xs text-gray-500 hover:text-gray-700">Abbrechen</button>
                                </form>
                            @endcan

                            @can('contribute', $project)
                                <div class="flex shrink-0 items-center gap-1" x-show="! editing">
                                    {{-- Nach oben --}}
                                    <form method="POST" action="{{ route('projects.phases.move', [$project, $phase]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" title="Nach oben"
                                                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30 disabled:hover:bg-transparent"
                                                @disabled($loop->first)>
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 5a.75.75 0 01.53.22l5 5a.75.75 0 11-1.06 1.06L10 6.81l-4.47 4.47a.75.75 0 01-1.06-1.06l5-5A.75.75 0 0110 5z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </form>
                                    {{-- Nach unten --}}
                                    <form method="POST" action="{{ route('projects.phases.move', [$project, $phase]) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" title="Nach unten"
                                                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30 disabled:hover:bg-transparent"
                                                @disabled($loop->last)>
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 15a.75.75 0 01-.53-.22l-5-5a.75.75 0 111.06-1.06L10 13.19l4.47-4.47a.75.75 0 111.06 1.06l-5 5A.75.75 0 0110 15z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </form>

                                    <button type="button" @click="editing = true; $nextTick(() => $refs.nameInput.focus())"
                                            class="ms-1 text-xs text-indigo-600 hover:underline">Bearbeiten</button>

                                    <form method="POST" action="{{ route('projects.phases.destroy', [$project, $phase]) }}"
                                          onsubmit="return confirm('Phase „{{ $phase->name }}“ löschen? Die {{ $phase->tasks_count }} enthaltenen Task(s) bleiben erhalten, verlieren aber die Phasenzuordnung.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ms-1 text-xs text-red-500 hover:underline">Löschen</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-3 text-sm text-gray-400">Noch keine Phasen angelegt.</p>
                    @endforelse
                </div>

                @can('contribute', $project)
                    <form method="POST" action="{{ route('projects.phases.store', $project) }}"
                          class="mt-5 flex items-end gap-3 border-t pt-5">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="name" value="Neue Phase" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                          :value="old('name')" required maxlength="100" placeholder="z. B. Fundament" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <x-primary-button>Anlegen</x-primary-button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
