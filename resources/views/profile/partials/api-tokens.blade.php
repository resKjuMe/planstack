<section id="api-tokens">
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('profile.api_tokens') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('profile.personal_access_tokens_for_the') }}
        </p>
    </header>

    {{-- One-time plaintext display of a freshly created token. --}}
    @if (session('api_token'))
        <div x-data="{ copied: false }" class="mt-6 rounded-md border border-green-200 bg-green-50 p-4">
            <p class="text-sm font-medium text-green-800">
                {{ __('profile.new_token_name_copy_it_now_it_won_t_be', ['name' => session('api_token_name')]) }}
            </p>
            <div class="mt-2 flex items-center gap-2">
                <code x-ref="tok" class="flex-1 overflow-x-auto whitespace-nowrap rounded bg-white px-2 py-1 text-xs text-gray-800 ring-1 ring-gray-200">{{ session('api_token') }}</code>
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.tok.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                        class="shrink-0 rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500">
                    <span x-text="copied ? '{{ __('common.copied') }}' : '{{ __('profile.copy') }}'"></span>
                </button>
            </div>
        </div>
    @endif

    @if (session('status') === 'api-token-revoked')
        <p class="mt-4 text-sm text-gray-500">{{ __('profile.token_revoked') }}</p>
    @endif

    {{-- Create a new token. --}}
    <form method="POST" action="{{ route('profile.tokens.store') }}" class="mt-6">
        @csrf
        <x-input-label for="token_name" :value="__('profile.token_name')" />
        <div class="mt-1 flex items-center gap-3">
            <x-text-input id="token_name" name="name" type="text" class="block flex-1"
                          :value="old('name', 'planstack')" required />
            <x-primary-button>{{ __('profile.create_token') }}</x-primary-button>
        </div>
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </form>

    {{-- Existing tokens. --}}
    <div class="mt-6">
        @forelse ($user->tokens()->latest()->get() as $token)
            <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-800">{{ $token->name }}</p>
                    <p class="text-xs text-gray-400">
                        {{ __('profile.created') }} {{ $token->created_at->locale('de')->diffForHumans() }}
                        ·
                        {{ $token->last_used_at
                            ? __('profile.last_used').' '.$token->last_used_at->locale('de')->diffForHumans()
                            : __('profile.never_used') }}
                    </p>
                </div>
                <form method="POST" action="{{ route('profile.tokens.destroy', $token->id) }}"
                      onsubmit="return confirm('{{ __('profile.revoke_token_name_applications_using_it', ['name' => $token->name]) }}');">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs text-red-500 hover:underline">{{ __('profile.revoke') }}</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-400">{{ __('profile.no_tokens_yet') }}</p>
        @endforelse
    </div>
</section>
