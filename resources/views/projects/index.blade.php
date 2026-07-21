<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('common.projects') }}</h2>
            <a href="{{ route('projects.create') }}"
               class="inline-flex items-center whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + {{ __('projects.new_project') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ q: '', filter: 'all' }">
            <x-flash />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $activeCount }} {{ $activeCount === 1 ? __('projects.project') : __('common.projects') }}
                    · {{ __('projects.count_open_tasks', ['count' => number_format($openTasks, 0, ',', '.')]) }}
                    · {{ __('projects.count_story_points', ['count' => number_format($totalSp, 0, ',', '.')]) }}
                </p>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                    <input type="search" x-model="q" placeholder="{{ __('projects.search_projects') }}"
                           class="w-64 rounded-md border-0 bg-white dark:bg-gray-800 py-2 pl-9 pr-3 text-sm text-gray-700 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ([
                    'all' => __('common.all'),
                    'mine' => __('projects.my_projects'),
                    'in_arbeit' => __('projects.in_progress'),
                    'fast_fertig' => __('projects.almost_done'),
                    'completed' => __('projects.completed'),
                    'archived' => __('projects.archived'),
                ] as $key => $label)
                    <button type="button" @click="filter = '{{ $key }}'"
                            class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                            :class="filter === '{{ $key }}' ? 'bg-gray-900 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($projects->isEmpty())
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">
                    {{ __('projects.no_projects_yet_create_your_first') }}
                </div>
            @else
                <div class="mt-6 grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($projects as $project)
                        @php
                            $sp = (int) $project->total_sp;
                            $pct = $sp > 0 ? (int) $project->done_sp / $sp * 100 : 0;
                            $isCompleted = $project->completed_at !== null;
                            // Abgeschlossene Projekte tragen die Kategorie „completed" (Badge
                            // + Filter-Pill) und überschreiben damit die berechnete Kategorie.
                            $category = $isCompleted
                                ? 'completed'
                                : ($pct <= 0 ? 'nicht_gestartet' : ($pct >= 80 ? 'fast_fertig' : 'in_arbeit'));
                            $categoryLabel = [
                                'nicht_gestartet' => __('projects.not_started'),
                                'in_arbeit' => __('projects.in_progress'),
                                'fast_fertig' => __('projects.almost_done'),
                                'completed' => __('projects.completed'),
                            ][$category];
                            $badgeClass = [
                                'nicht_gestartet' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                                'in_arbeit' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
                                'fast_fertig' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
                                'completed' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
                            ][$category];
                            $barClass = [
                                'nicht_gestartet' => 'bg-gray-300',
                                'in_arbeit' => 'bg-indigo-600',
                                'fast_fertig' => 'bg-green-500',
                                'completed' => 'bg-blue-500',
                            ][$category];
                            $isMine = $project->created_by_id === $userId;
                            $isArchived = $project->archived_at !== null;

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
                        {{-- Archivierte Projekte erscheinen ausschließlich unter der Pill
                             „Archiviert"; alle übrigen Filter zeigen nur aktive Projekte. --}}
                        <div class="h-full" x-show="
                                ((filter === 'archived') === {{ $isArchived ? 'true' : 'false' }})
                                && (filter === 'archived'
                                    || filter === 'all'
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
                            <div x-data="{ hover: null }"
                                 @click="if (!$event.target.closest('a')) { window.location = @js(route('projects.diagram', $project)) }"
                                 class="flex h-full cursor-pointer flex-col rounded-lg bg-white dark:bg-gray-800 p-6 shadow transition hover:shadow-md">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center rounded bg-gray-800 dark:bg-gray-700 px-2 py-0.5 font-mono text-xs font-semibold text-white">
                                        {{ $project->alias }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $badgeClass }}">
                                        {{ $categoryLabel }}
                                    </span>
                                </div>

                                <h3 class="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    <a href="{{ route('projects.diagram', $project) }}" class="hover:underline">{{ $project->name }}</a>
                                </h3>
                                <x-markdown :content="$project->description" class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2" />

                                {{-- mt-auto schiebt Fortschritt+Owner+Tasks als Block an den unteren
                                     Kachelrand — unabhängig davon, wie kurz Titel/Beschreibung sind.
                                     Setzt voraus, dass das div weiter oben flex + flex-col + h-full ist. --}}
                                <div class="mt-auto pt-5">
                                    <div>
                                        <div class="flex items-center justify-between text-sm">
                                            <span :class="hover ? hover.text : 'text-gray-500 dark:text-gray-400'"
                                                  x-text="hover ? (hover.label + ' · ' + hover.count + ' / ' + @js($project->tasks_count) + ' ' + @js(__('common.tasks'))) : @js(__('common.progress'))">{{ __('common.progress') }}</span>
                                            <span class="font-semibold" :class="hover ? hover.text : 'text-gray-900 dark:text-gray-100'"
                                                  x-text="hover ? (hover.pct + ' % SP') : @js(number_format($pct, 1, ',', '').' %')">{{ number_format($pct, 1, ',', '') }} %</span>
                                        </div>
                                        <div class="relative mt-1.5">
                                            {{-- Sichtbarer dünner Balken --}}
                                            <div class="flex h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                @foreach ($project->x_status_segments as $seg)
                                                    <div class="h-full {{ $seg['bar'] }}" style="width: {{ $seg['width'] }}%"></div>
                                                @endforeach
                                            </div>
                                            {{-- Transparente 36px-Hover-Ebene (zentriert, kein Layout-Shift) --}}
                                            <div class="absolute inset-x-0 top-1/2 flex h-9 -translate-y-1/2">
                                                @foreach ($project->x_status_segments as $seg)
                                                    <div class="h-full" style="width: {{ $seg['width'] }}%"
                                                         @mouseenter="hover = { pct: @js(number_format($seg['width'], 1, ',', '')), count: {{ $seg['count'] }}, text: @js($seg['text']), label: @js($seg['label']) }"
                                                         @mouseleave="hover = null"
                                                         title="{{ $seg['label'] }}: {{ $seg['count'] }}"></div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Status-Badges wie in der Summary (nach echtem Status differenziert) --}}
                                        @if (! empty($project->x_status_segments))
                                            <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                                                @foreach ($project->x_status_segments as $seg)
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium {{ $seg['badge'] }}">{{ $seg['count'] }} {{ $seg['label'] }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-2">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $avatarClass }} text-xs font-semibold text-white">
                                                {{ $initials }}
                                            </span>
                                            {{-- Name + Teams: zusammen max. so hoch wie der Avatar (16px + 12px = 28px) --}}
                                            <div class="min-w-0">
                                                <div class="truncate text-sm leading-4 text-gray-700 dark:text-gray-300">{{ $project->owner?->name }}</div>
                                                @if ($project->teams->isNotEmpty())
                                                    <div class="truncate text-xs leading-none text-gray-400 dark:text-gray-500" title="{{ $project->teams->pluck('name')->join(', ') }}">
                                                        {{ $project->teams->pluck('name')->join(', ') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="shrink-0 whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">{{ __('projects.count_tasks', ['count' => $project->tasks_count]) }} · {{ $sp }} SP</span>
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
