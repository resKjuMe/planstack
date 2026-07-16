@php $roles = \App\Enums\ProjectRole::cases(); @endphp

<x-app-layout>
    <x-slot name="header">
        <x-project-header-bar :project="$project" />
    </x-slot>

    <x-slot name="subheader">
        <x-project-tabs :project="$project" active="access" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-page-head title="Zugriff">
                <div class="space-y-3">
                    <div>
                        <div class="mb-1 font-semibold text-gray-700">Zugewiesene Teams</div>
                        <ul class="list-disc space-y-1 ps-4">
                            <li>Der Zugriff aufs Projekt läuft über Teams: Alle Mitglieder eines zugewiesenen Teams können das Projekt sehen und darin arbeiten.</li>
                            <li><span class="font-medium">Zuweisen</span> fügt eines deiner Teams hinzu — dessen Mitglieder erhalten Zugriff.</li>
                            <li><span class="font-medium">Entfernen</span> nimmt die Zuweisung zurück — die Mitglieder verlieren den Zugriff (außer über ein anderes Team oder als Projektgründer).</li>
                            <li>Ohne zugewiesenes Team hat nur der Projektgründer Zugriff. Neue Teams legst du unter „Teams" an.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="mb-1 font-semibold text-gray-700">Rollen</div>
                        <ul class="list-disc space-y-1 ps-4">
                            <li>Die Rolle legt fest, was jemand im Projekt darf; der Zugriff selbst kommt über die Teams.</li>
                            <li><span class="font-medium">Mitarbeiter</span>: Standardrolle — Tasks ansehen, beanspruchen und bearbeiten.</li>
                            <li><span class="font-medium">Architekt</span>: für die technische Planung — Tasks schneiden, Phasen und Abhängigkeiten festlegen und den Aufwand (Story Points) schätzen. Technisch dieselben Rechte wie ein Mitarbeiter, keine Zugriffs-/Rollenverwaltung.</li>
                            <li><span class="font-medium">Administrator</span>: darf zusätzlich Projekt, Team-Zuweisungen und Rollen verwalten. Der Projektgründer ist immer Administrator.</li>
                            <li>Ohne expliziten Eintrag gilt „Mitarbeiter". <span class="font-medium">Speichern</span> ändert die Rolle, <span class="font-medium">Reset</span> setzt auf Mitarbeiter zurück.</li>
                        </ul>
                    </div>
                </div>
            </x-page-head>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Assigned teams (grant access) --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="mb-4 font-semibold text-gray-900">Zugewiesene Teams</h3>

                    <div class="divide-y divide-gray-100">
                        @forelse ($project->teams as $team)
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <span class="font-medium text-gray-800">{{ $team->name }}</span>
                                    <span class="ms-2 text-xs text-gray-400">{{ $team->members->count() }} Mitglieder</span>
                                </div>
                                @can('manageMembers', $project)
                                    <form method="POST" action="{{ route('projects.teams.destroy', [$project, $team]) }}"
                                          onsubmit="return confirm('Team-Zuweisung entfernen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-red-500 hover:underline">Entfernen</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Noch kein Team zugewiesen – ohne Team hat niemand außer dem Projektgründer Zugriff.</p>
                        @endforelse
                    </div>

                    @can('manageMembers', $project)
                        @if ($assignableTeams->isNotEmpty())
                            <form method="POST" action="{{ route('projects.teams.store', $project) }}" class="mt-5 flex items-end gap-3 border-t pt-5">
                                @csrf
                                <div class="flex-1">
                                    <x-input-label for="team_id" value="Team zuweisen" />
                                    <select id="team_id" name="team_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                        @foreach ($assignableTeams as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('team_id')" class="mt-2" />
                                </div>
                                <x-primary-button>Zuweisen</x-primary-button>
                            </form>
                        @else
                            <p class="mt-4 text-xs text-gray-400 border-t pt-4">Keine weiteren eigenen Teams zum Zuweisen. Neue Teams unter „Teams“ anlegen.</p>
                        @endif
                    @endcan
                </div>

                {{-- Role distribution (per user) --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="mb-4 font-semibold text-gray-900">Rollen</h3>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-400 border-b">
                                <th class="py-2">User</th>
                                <th class="py-2">Rolle</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accessUsers as $user)
                                @php
                                    $isOwner = $project->isOwner($user);
                                    $role = $isOwner
                                        ? \App\Enums\ProjectRole::ADMIN
                                        : ($roleByUser->get($user->id)?->role ?? \App\Enums\ProjectRole::WORKER);
                                @endphp
                                <tr class="border-b last:border-0">
                                    <td class="py-2 font-medium text-gray-800">
                                        {{ $user->name }}
                                        @if ($isOwner)
                                            <span class="ms-1 text-xs text-amber-600">(Projektgründer)</span>
                                        @endif
                                        <div class="text-xs text-gray-400">{{ $user->email }}</div>
                                    </td>
                                    <td class="py-2">
                                        @can('manageMembers', $project)
                                            @unless ($isOwner)
                                                <form method="POST" action="{{ route('projects.members.update', [$project, $user]) }}" class="flex items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="role" class="rounded-md border-gray-300 text-xs">
                                                        @foreach ($roles as $r)
                                                            <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button class="text-xs text-indigo-600 hover:underline">Speichern</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-500">{{ $role->label() }}</span>
                                            @endunless
                                        @else
                                            <span class="text-xs text-gray-500">{{ $role->label() }}</span>
                                        @endcan
                                    </td>
                                    <td class="py-2 text-right">
                                        @can('manageMembers', $project)
                                            @if (! $isOwner && $roleByUser->has($user->id))
                                                <form method="POST" action="{{ route('projects.members.destroy', [$project, $user]) }}"
                                                      title="Auf WORKER zurücksetzen">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-gray-400 hover:underline">Reset</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-gray-400">Zugriff kommt über die zugewiesenen Teams. Ohne expliziten Eintrag gilt die Rolle Mitarbeiter.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
