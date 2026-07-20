<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('teams.new_team') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" action="{{ route('teams.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="name" :value="__('teams.team_name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name')" required autofocus maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('teams.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</a>
                        <x-primary-button>{{ __('common.create') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
