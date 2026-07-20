{{-- IST/SOLL-Gegenüberstellung. Bei erkennbaren „IST:"/„SOLL:"-Abschnitten zwei
     Karten nebeneinander (IST neutral, SOLL grün), sonst Fließtext-Fallback. --}}
@php $ta = \App\Support\TaskContentParser::targetActual((string) $task->description_target_actual); @endphp

<section class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-gray-900 mb-3">{{ __('tasks.actual_target_comparison') }}</h3>

    @if ($ta)
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-500">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    {{ __('tasks.actual') }} <span class="font-normal text-gray-400">{{ __('tasks.before') }}</span>
                </div>
                <div class="text-sm text-gray-800">
                    @if ($ta['ist']) <x-markdown :content="$ta['ist']" /> @else <span class="text-gray-400">—</span> @endif
                </div>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-green-700">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12l5 5l10 -10"/></svg>
                    {{ __('tasks.target') }} <span class="font-normal text-green-600/70">{{ __('tasks.after') }}</span>
                </div>
                <div class="text-sm text-gray-800">
                    @if ($ta['soll']) <x-markdown :content="$ta['soll']" /> @else <span class="text-gray-400">—</span> @endif
                </div>
            </div>
        </div>
    @else
        <x-markdown :content="$task->description_target_actual" />
    @endif
</section>
