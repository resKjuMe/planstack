@php
    $statuses = \App\Enums\TaskStatus::cases();
    $roles = \App\Enums\ProjectRole::cases();
    $byStatus = $project->tasks->groupBy(fn ($t) => $t->status->value);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded bg-gray-800 px-2.5 py-1 text-sm font-mono font-semibold text-white">
                    {{ $project->alias }}
                </span>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('projects.status.summary', $project) }}"
                   class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                    Status
                </a>
                <a href="{{ route('projects.skill', $project) }}"
                   class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50"
                   title="Planstack-Skill für Claude Code (SKILL.md + vorausgefüllte config.json) herunterladen">
                    <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                        <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                    </svg>
                    Skill
                </a>
                @can('contribute', $project)
                    <a href="{{ route('projects.tasks.create', $project) }}"
                       class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        + Task
                    </a>
                @endcan
                @can('update', $project)
                    <form method="POST" action="{{ route('projects.sync-prs', $project) }}"
                          onsubmit="return confirm('Merge-Status aller offenen PRs von GitHub abrufen und gemergte Tasks taggen?');">
                        @csrf
                        <button class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.311h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                            </svg>
                            PRs abgleichen
                        </button>
                    </form>
                    <a href="{{ route('projects.edit', $project) }}"
                       class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                        Einstellungen
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <x-flash />

            @if ($project->description)
                <p class="text-sm text-gray-600 max-w-3xl">{{ $project->description }}</p>
            @endif

            {{-- Board --}}
            <div class="overflow-x-auto pb-4">
                <div class="flex gap-4 min-w-max">
                    @foreach ($statuses as $status)
                        @php $tasks = $byStatus->get($status->value, collect()); @endphp
                        <div class="w-72 shrink-0">
                            <div class="flex items-center justify-between mb-2">
                                <x-task-status :status="$status" />
                                <span class="text-xs text-gray-400">{{ $tasks->count() }}</span>
                            </div>
                            <div class="space-y-2 min-h-8">
                                @foreach ($tasks as $task)
                                    <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-100 p-3">
                                        <div class="flex items-center justify-between">
                                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                               class="font-mono text-sm font-semibold text-indigo-700 hover:underline">
                                                {{ $task->name }}
                                            </a>
                                            @if ($task->concern)
                                                <span title="Concern" class="text-orange-500 text-xs">⚠ Concern</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-gray-700">{{ $task->summary }}</p>
                                        <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                                            <span>{{ $task->claimer?->name ?? '—' }}</span>
                                            <span>
                                                @if ($task->effort_story_points) {{ $task->effort_story_points }} SP @endif
                                            </span>
                                        </div>
                                        @can('claim', $task)
                                            <form method="POST" action="{{ route('projects.tasks.claim', [$project, $task]) }}" class="mt-2">
                                                @csrf
                                                <button class="w-full rounded bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">
                                                    {{ $task->claimed_by_id ? 'Freigeben' : 'Beanspruchen' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Assigned teams (grant access) --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Zugewiesene Teams</h3>

                    @forelse ($project->teams as $team)
                        <div class="flex items-center justify-between border-b last:border-0 py-2">
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
                        <p class="text-sm text-gray-400">Noch kein Team zugewiesen – ohne Team hat niemand außer dem Owner Zugriff.</p>
                    @endforelse

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
                    <h3 class="font-semibold text-gray-900 mb-4">Rollen</h3>

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
                                            <span class="ms-1 text-xs text-amber-600">(Owner)</span>
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
                                                            <option value="{{ $r->value }}" @selected($role === $r)>{{ $r->value }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button class="text-xs text-indigo-600 hover:underline">Speichern</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-500">{{ $role->value }}</span>
                                            @endunless
                                        @else
                                            <span class="text-xs text-gray-500">{{ $role->value }}</span>
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
                    <p class="mt-3 text-xs text-gray-400">Zugriff kommt über die zugewiesenen Teams. Ohne expliziten Eintrag gilt die Rolle WORKER.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
