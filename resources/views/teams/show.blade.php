<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('teams.team') }} – {{ $team->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @can('update', $team)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('teams.rename_team') }}</h3>
                    <form method="POST" action="{{ route('teams.update', $team) }}">
                        @csrf
                        @method('PATCH')
                        <x-input-label for="name" :value="__('teams.team_name_2')" />
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <div class="flex-1 min-w-64">
                                <x-text-input id="name" name="name" type="text" class="block w-full"
                                              :value="old('name', $team->name)" required maxlength="100" />
                            </div>
                            <x-primary-button>{{ __('common.save') }}</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </form>
                </div>
            @endcan

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('common.members') }}</h3>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 dark:text-gray-400 border-b">
                            <th class="py-2">{{ __('common.name') }}</th>
                            <th class="py-2">{{ __('common.email') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($team->members as $member)
                            <tr class="border-b last:border-0">
                                <td class="py-2 font-medium text-gray-800 dark:text-gray-100">
                                    {{ $member->name }}
                                    @if ($team->isOwner($member))
                                        <span class="ms-1 text-xs text-amber-600 dark:text-amber-400">{{ __('teams.creator') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $member->email }}</td>
                                <td class="py-2 text-right">
                                    @can('manageMembers', $team)
                                        @if (! $team->isOwner($member))
                                            <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}"
                                                  onsubmit="return confirm('{{ __('teams.remove_member') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs text-red-500 dark:text-red-400 hover:underline">{{ __('common.remove') }}</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @can('manageMembers', $team)
                    <form method="POST" action="{{ route('teams.members.store', $team) }}" class="mt-5 border-t pt-5">
                        @csrf
                        <x-input-label for="user_id" :value="__('teams.add_member')" />
                        @if ($assignableUsers->isNotEmpty())
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <div class="flex-1 min-w-64">
                                    <select id="user_id" name="user_id" required
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($assignableUsers as $orgUser)
                                            <option value="{{ $orgUser->id }}" @selected((int) old('user_id') === $orgUser->id)>
                                                {{ $orgUser->name }} ({{ $orgUser->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-primary-button>{{ __('teams.add') }}</x-primary-button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('teams.choose_from_the_members_of_your') }}</p>
                        @else
                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">{{ __('teams.all_members_of_your_organization_are') }}</p>
                        @endif
                        <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                    </form>
                @endcan
            </div>

            @can('delete', $team)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-red-100 dark:border-red-900/50">
                    <h3 class="font-semibold text-red-700 dark:text-red-300">{{ __('teams.delete_team') }}</h3>
                    <form method="POST" action="{{ route('teams.destroy', $team) }}" class="mt-4"
                          onsubmit="return confirm('{{ __('teams.really_delete_this_team') }}');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('common.delete') }}</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
