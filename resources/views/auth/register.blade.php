<x-guest-layout>
    @if (! empty($inviteOrganization))
        <div class="mb-4 rounded-md border border-indigo-100 bg-indigo-50 p-3 text-sm text-indigo-800">
            Du wirst nach der Registrierung automatisch der Organisation
            <span class="font-semibold">{{ $inviteOrganization->name }}</span> zugeordnet.
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        {{-- Einladungscode aus dem Registrierungslink (?invite=CODE) mitführen. --}}
        @if (! empty($inviteCode))
            <input type="hidden" name="invite" value="{{ $inviteCode }}">
        @endif

        <!-- Name -->
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $prefillEmail ?? '')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Passwort" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Passwort bestätigen" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                Bereits registriert?
            </a>

            <x-primary-button class="ms-4">
                Registrieren
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
