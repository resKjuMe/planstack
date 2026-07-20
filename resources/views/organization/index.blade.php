<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Organisation') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            @if ($organization)
                {{-- ============ Bestehende Organisation ============ --}}
                @php $isOwner = $organization->isOwner($user); @endphp

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $organization->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Gegründet von {{ $organization->owner?->name }}
                                · {{ $organization->members->count() }} {{ \Illuminate\Support\Str::plural('Mitglied', $organization->members->count()) }}
                            </p>
                        </div>

                        {{-- Einladungscode zum Weitergeben (nur Gründer) --}}
                        @if ($isOwner)
                        <div x-data="{ copied: false }" class="text-right">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-400">Einladungscode</div>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="rounded-md bg-gray-100 px-3 py-1.5 font-mono text-base font-semibold tracking-widest text-gray-800">{{ $organization->formattedInviteCode() }}</span>
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($organization->invite_code)); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="Code kopieren">
                                    <svg x-show="!copied" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <svg x-show="copied" x-cloak class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Zum Beitreten weitergeben.</p>
                        </div>
                        @endif
                    </div>

                    {{-- Mitglieder --}}
                    <div class="mt-6">
                        <h4 class="mb-3 text-sm font-semibold text-gray-600">Mitglieder</h4>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-400">
                                    <th class="py-2">Name</th>
                                    <th class="py-2">E-Mail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($organization->members->sortBy('name') as $member)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 font-medium text-gray-800">
                                            {{ $member->name }}
                                            @if ($organization->isOwner($member))
                                                <span class="ms-1 text-xs text-amber-600">(Gründer)</span>
                                            @endif
                                            @if ($member->id === $user->id)
                                                <span class="ms-1 text-xs text-gray-400">(du)</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-gray-500">{{ $member->email }}</td>
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
                                  onsubmit="return confirm('Organisation „{{ $organization->name }}“ wirklich löschen? Alle Mitglieder verlieren die Zugehörigkeit.');">
                                @csrf
                                @method('DELETE')
                                <x-danger-button>Organisation löschen</x-danger-button>
                                <span class="ms-2 text-xs text-gray-400">Entfernt die Organisation für alle Mitglieder. Nicht umkehrbar.</span>
                            </form>
                        @else
                            <form method="POST" action="{{ route('organization.leave') }}"
                                  onsubmit="return confirm('Organisation wirklich verlassen?');">
                                @csrf
                                <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Organisation verlassen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Mitglieder per individueller Einladung einladen (nur Gründer) --}}
                @if ($isOwner)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="mb-1 font-semibold text-gray-900">Mitglieder einladen</h3>
                    <p class="mb-4 text-sm text-gray-500">
                        Verschicke eine persönliche Einladung per E-Mail. Der Link ist einmalig und ordnet die Person nach der Registrierung automatisch dieser Organisation – und den gewählten Teams – zu.
                    </p>

                    <form method="POST" action="{{ route('organization.invite') }}">
                        @csrf
                        <x-input-label for="email" value="E-Mail-Adresse" />
                        <div class="mt-1 flex items-center gap-3">
                            <x-text-input id="email" name="email" type="email" class="block flex-1"
                                          :value="old('email')" required placeholder="kollege@firma.de" />
                            <x-primary-button>Einladung senden</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />

                        @if ($assignableTeams->isNotEmpty())
                            @php $oldTeams = collect(old('team_ids', []))->map(fn ($v) => (int) $v); @endphp
                            <div class="mt-5 border-t pt-5">
                                <x-input-label value="Teams (optional)" />
                                <p class="mt-1 text-xs text-gray-400">Die eingeladene Person wird nach der Registrierung diesen Teams hinzugefügt.</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @foreach ($assignableTeams as $team)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="team_ids[]" value="{{ $team->id }}"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                   @checked($oldTeams->contains($team->id))>
                                            <span>{{ $team->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('team_ids')" class="mt-2" />
                            </div>
                        @else
                            <p class="mt-4 border-t pt-4 text-xs text-gray-400">
                                Du bist noch in keinem Team. Lege unter „Teams" welche an, um Einladungen direkt mit Teams zu verknüpfen.
                            </p>
                        @endif
                    </form>
                </div>
                @endif
            @else
                {{-- ============ Keine Organisation: gründen oder beitreten ============ --}}
                <p class="text-sm text-gray-500">
                    Du gehörst noch keiner Organisation an. Gründe eine neue oder tritt einer bestehenden per Einladungscode bei.
                    Jeder User kann nur einer Organisation angehören.
                </p>

                <div class="grid gap-6 lg:grid-cols-2">
                    {{-- Gründen --}}
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="mb-1 font-semibold text-gray-900">Organisation gründen</h3>
                        <p class="mb-4 text-sm text-gray-500">Lege eine neue Organisation an – du wirst automatisch Gründer.</p>
                        <form method="POST" action="{{ route('organization.store') }}">
                            @csrf
                            <x-input-label for="name" value="Name der Organisation" />
                            <div class="mt-1 flex items-center gap-3">
                                <x-text-input id="name" name="name" type="text" class="block flex-1"
                                              :value="old('name')" required maxlength="100" placeholder="z. B. Meine Firma" />
                                <x-primary-button>Gründen</x-primary-button>
                            </div>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </form>
                    </div>

                    {{-- Beitreten --}}
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="mb-1 font-semibold text-gray-900">Organisation beitreten</h3>
                        <p class="mb-4 text-sm text-gray-500">Gib den Einladungscode ein, den du vom Gründer erhalten hast.</p>
                        <form method="POST" action="{{ route('organization.join') }}">
                            @csrf
                            <x-input-label for="invite_code" value="Einladungscode" />
                            <div class="mt-1 flex items-center gap-3">
                                <x-text-input id="invite_code" name="invite_code" type="text"
                                              class="block flex-1 font-mono tracking-widest uppercase"
                                              :value="old('invite_code')" required maxlength="16" placeholder="XXXX-XXXX" />
                                <x-primary-button>Beitreten</x-primary-button>
                            </div>
                            <x-input-error :messages="$errors->get('invite_code')" class="mt-2" />
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
