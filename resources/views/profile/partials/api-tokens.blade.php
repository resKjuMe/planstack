<section id="api-tokens">
    <header>
        <h2 class="text-lg font-medium text-gray-900">API-Token</h2>
        <p class="mt-1 text-sm text-gray-600">
            Persönliche Access-Tokens für die Planstack-API und den Planstack-Skill. Der Skill-Download
            legt automatisch einen an. Ein Token wird aus Sicherheitsgründen nur einmal — direkt nach
            dem Erstellen — angezeigt.
        </p>
    </header>

    {{-- One-time plaintext display of a freshly created token. --}}
    @if (session('api_token'))
        <div x-data="{ copied: false }" class="mt-6 rounded-md border border-green-200 bg-green-50 p-4">
            <p class="text-sm font-medium text-green-800">
                Neuer Token „{{ session('api_token_name') }}" — jetzt kopieren, er wird nicht erneut angezeigt:
            </p>
            <div class="mt-2 flex items-center gap-2">
                <code x-ref="tok" class="flex-1 overflow-x-auto whitespace-nowrap rounded bg-white px-2 py-1 text-xs text-gray-800 ring-1 ring-gray-200">{{ session('api_token') }}</code>
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.tok.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                        class="shrink-0 rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500">
                    <span x-text="copied ? 'Kopiert ✓' : 'Kopieren'"></span>
                </button>
            </div>
        </div>
    @endif

    @if (session('status') === 'api-token-revoked')
        <p class="mt-4 text-sm text-gray-500">Token widerrufen.</p>
    @endif

    {{-- Create a new token. --}}
    <form method="POST" action="{{ route('profile.tokens.store') }}" class="mt-6 flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <x-input-label for="token_name" :value="'Token-Name'" />
            <x-text-input id="token_name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', 'planstack')" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <x-primary-button>Token erstellen</x-primary-button>
    </form>

    {{-- Existing tokens. --}}
    <div class="mt-6">
        @forelse ($user->tokens()->latest()->get() as $token)
            <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-800">{{ $token->name }}</p>
                    <p class="text-xs text-gray-400">
                        erstellt {{ $token->created_at->locale('de')->diffForHumans() }}
                        ·
                        {{ $token->last_used_at
                            ? 'zuletzt genutzt '.$token->last_used_at->locale('de')->diffForHumans()
                            : 'noch nicht genutzt' }}
                    </p>
                </div>
                <form method="POST" action="{{ route('profile.tokens.destroy', $token->id) }}"
                      onsubmit="return confirm('Token „{{ $token->name }}“ widerrufen? Anwendungen, die ihn nutzen, verlieren den Zugriff.');">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs text-red-500 hover:underline">Widerrufen</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-400">Noch keine Tokens.</p>
        @endforelse
    </div>
</section>
