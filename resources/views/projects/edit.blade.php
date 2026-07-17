<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Projekt bearbeiten – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="general" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="alias" value="Kürzel (unique)" />
                        <x-text-input id="alias" name="alias" type="text" class="mt-1 block w-full"
                                      :value="old('alias', $project->alias)" required maxlength="20" />
                        <x-input-error :messages="$errors->get('alias')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $project->name)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Beschreibung" />
                        <textarea id="description" name="description" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $project->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="github_repo" value="GitHub-Repository" />
                        <x-text-input id="github_repo" name="github_repo" type="text" class="mt-1 block w-full font-mono"
                                      :value="old('github_repo', $project->github_repo)" maxlength="255"
                                      placeholder="owner/repo" />
                        <p class="mt-1 text-xs text-gray-400">Format <span class="font-mono">owner/repo</span> – für PR-Verlinkung und den „PRs abgleichen"-Button. Leer lassen, um den Standard aus der Konfiguration zu nutzen.</p>
                        <x-input-error :messages="$errors->get('github_repo')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                        <x-primary-button>Speichern</x-primary-button>
                    </div>
                </form>
            </div>

            @can('delete', $project)
                <div class="bg-white rounded-lg shadow p-6 border border-red-100">
                    <h3 class="font-semibold text-red-700">Projekt löschen</h3>
                    <p class="text-sm text-gray-500 mt-1">Entfernt das Projekt inkl. aller Tasks. Nicht umkehrbar.</p>
                    <form method="POST" action="{{ route('projects.destroy', $project) }}" class="mt-4"
                          onsubmit="return confirm('Projekt wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Löschen</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
