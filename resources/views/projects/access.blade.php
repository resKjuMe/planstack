@php $roles = \App\Enums\ProjectRole::cases(); @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('projects.edit_project') }} – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="access" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <x-page-head :title="__('common.access')">
                <div class="space-y-3">
                    <div>
                        <div class="mb-1 font-semibold text-gray-700">{{ __('projects.assigned_teams') }}</div>
                        <ul class="list-disc space-y-1 ps-4">
                            <li>{{ __('projects.access_to_the_project_is_managed_via') }}</li>
                            <li><span class="font-medium">{{ __('projects.assign') }}</span> {{ __('projects.adds_one_of_your_teams_its_members_gain') }}</li>
                            <li><span class="font-medium">{{ __('common.remove') }}</span> {{ __('projects.revokes_the_assignment_the_members_lose') }}</li>
                            <li>{{ __('projects.without_an_assigned_team_only_the') }}</li>
                        </ul>
                    </div>
                    <div>
                        <div class="mb-1 font-semibold text-gray-700">{{ __('projects.roles') }}</div>
                        <ul class="list-disc space-y-1 ps-4">
                            <li>{{ __('projects.the_role_determines_what_someone_may_do') }}</li>
                            <li><span class="font-medium">{{ __('projects.contributor') }}</span>: {{ __('projects.default_role_view_claim_and_work_on') }}</li>
                            <li><span class="font-medium">{{ __('projects.architect') }}</span>: {{ __('projects.for_technical_planning_slicing_tasks') }}</li>
                            <li><span class="font-medium">{{ __('projects.administrator') }}</span>: {{ __('projects.may_additionally_manage_the_project') }}</li>
                            <li>{{ __('projects.without_an_explicit_entry_contributor') }} <span class="font-medium">{{ __('common.save') }}</span> {{ __('projects.changes_the_role') }} <span class="font-medium">{{ __('projects.reset') }}</span> {{ __('projects.resets_to_contributor') }}</li>
                        </ul>
                    </div>
                </div>
            </x-page-head>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Assigned teams (grant access) --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="mb-4 font-semibold text-gray-900">{{ __('projects.assigned_teams') }}</h3>

                    <div class="divide-y divide-gray-100">
                        @forelse ($project->teams as $team)
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <span class="font-medium text-gray-800">{{ $team->name }}</span>
                                    <span class="ms-2 text-xs text-gray-400">{{ __('common.count_members', ['count' => $team->members->count()]) }}</span>
                                </div>
                                @can('manageMembers', $project)
                                    <form method="POST" action="{{ route('projects.teams.destroy', [$project, $team]) }}"
                                          onsubmit="return confirm('{{ __('projects.remove_team_assignment') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-red-500 hover:underline">{{ __('common.remove') }}</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">{{ __('projects.no_team_assigned_yet_without_a_team_no') }}</p>
                        @endforelse
                    </div>

                    @can('manageMembers', $project)
                        @if ($assignableTeams->isNotEmpty())
                            <form method="POST" action="{{ route('projects.teams.store', $project) }}" class="mt-5 border-t pt-5">
                                @csrf
                                <x-input-label for="team_id" :value="__('projects.assign_team')" />
                                <div class="mt-1 flex items-center gap-3">
                                    <select id="team_id" name="team_id" class="block flex-1 rounded-md border-gray-300 text-sm">
                                        @foreach ($assignableTeams as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-primary-button>{{ __('projects.assign') }}</x-primary-button>
                                </div>
                                <x-input-error :messages="$errors->get('team_id')" class="mt-2" />
                            </form>
                        @else
                            <p class="mt-4 text-xs text-gray-400 border-t pt-4">{{ __('projects.no_further_own_teams_to_assign_create') }}</p>
                        @endif
                    @endcan
                </div>

                {{-- Role distribution (per user) --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="mb-4 font-semibold text-gray-900">{{ __('projects.roles') }}</h3>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-400 border-b">
                                <th class="py-2">{{ __('projects.user') }}</th>
                                <th class="py-2">{{ __('projects.role') }}</th>
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
                                            <span class="ms-1 text-xs text-amber-600">{{ __('projects.project_owner') }}</span>
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
                                                    <button class="text-xs text-indigo-600 hover:underline">{{ __('common.save') }}</button>
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
                                                      title="{{ __('projects.reset_to_worker') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-gray-400 hover:underline">{{ __('projects.reset') }}</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-gray-400">{{ __('projects.access_comes_via_the_assigned_teams') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
