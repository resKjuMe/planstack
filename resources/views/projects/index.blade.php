<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{ q: '', filter: 'all' }">
            <x-flash />

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Projekte</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $projects->count() }} {{ Str::plural('Projekt', $projects->count()) }}
                        · {{ number_format($openTasks, 0, ',', '.') }} offene Tasks
                        · {{ number_format($totalSp, 0, ',', '.') }} Story Points
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                        <input type="search" x-model="q" placeholder="Projekte durchsuchen …"
                               class="w-64 rounded-md border-0 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                    </div>
                    <a href="{{ route('projects.create') }}"
                       class="inline-flex items-center whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        + Neues Projekt
                    </a>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ([
                    'all' => 'Alle',
                    'mine' => 'Meine Projekte',
                    'in_arbeit' => 'In Arbeit',
                    'fast_fertig' => 'Fast fertig',
                ] as $key => $label)
                    <button type="button" @click="filter = '{{ $key }}'"
                            class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                            :class="filter === '{{ $key }}' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($projects->isEmpty())
                <div class="mt-6 bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    Noch keine Projekte. Lege dein erstes Projekt an.
                </div>
            @else
                <div class="mt-6 grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($projects as $project)
                        @php
                            $sp = (int) $project->total_sp;
                            $pct = $sp > 0 ? (int) $project->done_sp / $sp * 100 : 0;
                            $category = $pct <= 0 ? 'nicht_gestartet' : ($pct >= 80 ? 'fast_fertig' : 'in_arbeit');
                            $categoryLabel = [
                                'nicht_gestartet' => 'Nicht gestartet',
                                'in_arbeit' => 'In Arbeit',
                                'fast_fertig' => 'Fast fertig',
                            ][$category];
                            $badgeClass = [
                                'nicht_gestartet' => 'bg-gray-100 text-gray-600',
                                'in_arbeit' => 'bg-amber-100 text-amber-700',
                                'fast_fertig' => 'bg-green-100 text-green-700',
                            ][$category];
                            $barClass = [
                                'nicht_gestartet' => 'bg-gray-300',
                                'in_arbeit' => 'bg-indigo-600',
                                'fast_fertig' => 'bg-green-500',
                            ][$category];
                            $isMine = $project->created_by_id === $userId;

                            // Zwei Initialen aus dem Owner-Namen, z. B. "Christian Mietze" → "CM".
                            $initials = collect(preg_split('/\s+/', trim($project->owner?->name ?? '?')))
                                ->filter()
                                ->take(2)
                                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                                ->implode('');
                            // Stabile, aber beliebige Avatar-Farbe je Owner (nicht je Projekt).
                            $avatarPalette = ['bg-emerald-600', 'bg-indigo-600', 'bg-rose-600', 'bg-amber-600', 'bg-sky-600', 'bg-fuchsia-600'];
                            $avatarClass = $avatarPalette[($project->created_by_id ?? 0) % count($avatarPalette)];
                        @endphp
                        <div class="h-full" x-show="
                                (filter === 'all'
                                    || (filter === 'mine' && {{ $isMine ? 'true' : 'false' }})
                                    || filter === @js($category))
                                && (q === '' || @js(Str::lower($project->alias.' '.$project->name)).includes(q.toLowerCase()))
                            ">
                            {{-- Kein umschließendes <a>: die Beschreibung kann über <x-markdown>
                                 selbst Links enthalten (z. B. "in Jira öffnen"), und <a> darf laut
                                 HTML-Spec nicht in <a> verschachtelt werden — der Browser schließt
                                 den äußeren Link dann an der Stelle des inneren automatisch, wodurch
                                 alles danach (Fortschritt/Footer) aus dem Link herausfällt. Stattdessen
                                 ein klickbares div, das Klicks auf echte Links durchlässt (siehe @click). --}}
                            <div @click="if (!$event.target.closest('a')) { window.location = @js(route('projects.status.diagram', $project)) }"
                                 class="flex h-full cursor-pointer flex-col rounded-lg bg-white p-6 shadow transition hover:shadow-md">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center rounded bg-gray-800 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                                        {{ $project->alias }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $badgeClass }}">
                                        {{ $categoryLabel }}
                                    </span>
                                </div>

                                <h3 class="mt-3 text-lg font-semibold text-gray-900">
                                    <a href="{{ route('projects.status.diagram', $project) }}" class="hover:underline">{{ $project->name }}</a>
                                </h3>
                                <x-markdown :content="$project->description" class="mt-1 text-sm text-gray-500 line-clamp-2" />

                                {{-- mt-auto schiebt Fortschritt+Owner+Tasks als Block an den unteren
                                     Kachelrand — unabhängig davon, wie kurz Titel/Beschreibung sind.
                                     Setzt voraus, dass das div weiter oben flex + flex-col + h-full ist. --}}
                                <div class="mt-auto pt-5">
                                    <div>
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-500">Fortschritt</span>
                                            <span class="font-semibold text-gray-900">{{ number_format($pct, 1, ',', '') }} %</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 rounded-full bg-gray-100">
                                            <div class="h-1.5 rounded-full {{ $barClass }}" style="width: {{ max(2, min(100, $pct)) }}%"></div>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full {{ $avatarClass }} text-xs font-semibold text-white">
                                                {{ $initials }}
                                            </span>
                                            <span class="text-sm text-gray-700">{{ $project->owner?->name }}</span>
                                        </div>
                                        <span class="text-xs text-gray-400">{{ $project->tasks_count }} Tasks · {{ $sp }} SP</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
