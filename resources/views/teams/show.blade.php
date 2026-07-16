<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Team – {{ $team->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @can('update', $team)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Team umbenennen</h3>
                    <form method="POST" action="{{ route('teams.update', $team) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="flex-1 min-w-64">
                            <x-input-label for="name" value="Teamname" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                          :value="old('name', $team->name)" required maxlength="100" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <x-primary-button>Speichern</x-primary-button>
                    </form>
                </div>
            @endcan

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Mitglieder</h3>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 border-b">
                            <th class="py-2">Name</th>
                            <th class="py-2">E-Mail</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($team->members as $member)
                            <tr class="border-b last:border-0">
                                <td class="py-2 font-medium text-gray-800">
                                    {{ $member->name }}
                                    @if ($team->isOwner($member))
                                        <span class="ms-1 text-xs text-amber-600">(Creator)</span>
                                    @endif
                                </td>
                                <td class="py-2 text-gray-500">{{ $member->email }}</td>
                                <td class="py-2 text-right">
                                    @can('manageMembers', $team)
                                        @if (! $team->isOwner($member))
                                            <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}"
                                                  onsubmit="return confirm('Mitglied entfernen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs text-red-500 hover:underline">Entfernen</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @can('manageMembers', $team)
                    <form method="POST" action="{{ route('teams.members.store', $team) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t pt-5">
                        @csrf
                        <div class="flex-1 min-w-64">
                            <x-input-label for="email" value="User per E-Mail hinzufügen" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                            <p class="mt-1 text-xs text-gray-400">Nur die Creator:in kann Mitglieder ausschließlich per E-Mail hinzufügen.</p>
                        </div>
                        <x-primary-button>Hinzufügen</x-primary-button>
                    </form>
                @endcan
            </div>

            @can('delete', $team)
                <div class="bg-white rounded-lg shadow p-6 border border-red-100">
                    <h3 class="font-semibold text-red-700">Team löschen</h3>
                    <form method="POST" action="{{ route('teams.destroy', $team) }}" class="mt-4"
                          onsubmit="return confirm('Team wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Löschen</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
