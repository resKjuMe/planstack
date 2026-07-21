<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('common.organization') }}</h2>
    </x-slot>

    @if ($organization)
        <x-slot name="subheader">
            <x-organization-tabs active="organization" />
        </x-slot>
    @endif

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @if ($organization)
                {{-- ============ Bestehende Organisation ============ --}}
                @php $isOwner = $organization->isOwner($user); @endphp

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $organization->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('organization.founded_by') }} {{ $organization->owner?->name }}
                                · {{ $organization->members->count() }} {{ $organization->members->count() === 1 ? __('organization.member') : __('common.members') }}
                            </p>
                        </div>
                    </div>

                    {{-- Mitglieder --}}
                    <div class="mt-6">
                        <h4 class="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">{{ __('common.members') }}</h4>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-400 dark:text-gray-400">
                                    <th class="py-2">{{ __('common.name') }}</th>
                                    <th class="py-2">{{ __('common.email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($organization->members->sortBy('name') as $member)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 font-medium text-gray-800 dark:text-gray-100">
                                            {{ $member->name }}
                                            @if ($organization->isOwner($member))
                                                <span class="ms-1 text-xs text-amber-600 dark:text-amber-400">{{ __('organization.founder') }}</span>
                                            @endif
                                            @if ($member->id === $user->id)
                                                <span class="ms-1 text-xs text-gray-400 dark:text-gray-500">{{ __('organization.you') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $member->email }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Aktionen --}}
                    <div class="mt-6 border-t pt-5">
                        <x-input-error :messages="$errors->get('leave')" class="mb-3" />
                        @if ($isOwner)
                            <form method="POST" action="{{ route('organization.destroy') }}"
                                  onsubmit="return confirm('{{ __('organization.really_delete_organization_name_all', ['name' => $organization->name]) }}');">
                                @csrf
                                @method('DELETE')
                                <x-danger-button>{{ __('organization.delete_organization') }}</x-danger-button>
                                <span class="ms-2 text-xs text-gray-400 dark:text-gray-500">{{ __('organization.removes_the_organization_for_all') }}</span>
                            </form>
                        @else
                            <form method="POST" action="{{ route('organization.leave') }}"
                                  onsubmit="return confirm('{{ __('organization.really_leave_this_organization') }}');">
                                @csrf
                                <button class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    {{ __('organization.leave_organization') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Mitglieder per individueller Einladung einladen (nur Gründer) --}}
                @if ($isOwner)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="mb-1 font-semibold text-gray-900 dark:text-gray-100">{{ __('organization.invite_members') }}</h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('organization.send_a_personal_invitation_by_email_the') }}
                    </p>

                    <form method="POST" action="{{ route('organization.invite') }}">
                        @csrf
                        <x-input-label for="email" :value="__('organization.email_address')" />
                        <div class="mt-1 flex items-center gap-3">
                            <x-text-input id="email" name="email" type="email" class="block flex-1"
                                          :value="old('email')" required :placeholder="__('organization.colleague_company_com')" />
                            <x-primary-button>{{ __('organization.send_invitation') }}</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />

                        @if ($assignableTeams->isNotEmpty())
                            @php $oldTeams = collect(old('team_ids', []))->map(fn ($v) => (int) $v); @endphp
                            <div class="mt-5 border-t pt-5">
                                <x-input-label :value="__('organization.teams_optional')" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('organization.the_invited_person_will_be_added_to') }}</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @foreach ($assignableTeams as $team)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="team_ids[]" value="{{ $team->id }}"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500"
                                                   @checked($oldTeams->contains($team->id))>
                                            <span>{{ $team->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('team_ids')" class="mt-2" />
                            </div>
                        @else
                            <p class="mt-4 border-t pt-4 text-xs text-gray-400 dark:text-gray-500">
                                {{ __('organization.you_are_not_in_any_team_yet_create_some') }}
                            </p>
                        @endif
                    </form>
                </div>
                @endif
            @else
                {{-- ============ Keine Organisation: gründen oder beitreten ============ --}}
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('organization.you_don_t_belong_to_any_organization') }}
                </p>

                <div class="grid gap-6 lg:grid-cols-2">
                    {{-- Gründen --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="mb-1 font-semibold text-gray-900 dark:text-gray-100">{{ __('organization.create_organization') }}</h3>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('organization.create_a_new_organization_you') }}</p>
                        <form method="POST" action="{{ route('organization.store') }}">
                            @csrf
                            <x-input-label for="name" :value="__('organization.organization_name')" />
                            <div class="mt-1 flex items-center gap-3">
                                <x-text-input id="name" name="name" type="text" class="block flex-1"
                                              :value="old('name')" required maxlength="100" :placeholder="__('organization.e_g_my_company')" />
                                <x-primary-button>{{ __('organization.create') }}</x-primary-button>
                            </div>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </form>
                    </div>

                    {{-- Beitreten (individueller Code aus der Einladungs-E-Mail) --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="mb-1 font-semibold text-gray-900 dark:text-gray-100">{{ __('organization.join_organization') }}</h3>
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('organization.enter_the_personal_invitation_code_from') }}</p>
                        <form method="POST" action="{{ route('organization.join') }}">
                            @csrf
                            <x-input-label for="token" :value="__('organization.invitation_code')" />
                            <div class="mt-1 flex items-center gap-3">
                                <x-text-input id="token" name="token" type="text"
                                              class="block flex-1 font-mono text-xs"
                                              :value="old('token')" required :placeholder="__('organization.code_from_the_email')" />
                                <x-primary-button>{{ __('organization.join') }}</x-primary-button>
                            </div>
                            <x-input-error :messages="$errors->get('token')" class="mt-2" />
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
