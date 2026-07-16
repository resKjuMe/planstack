<x-status-shell :project="$project" :active="$active" :bare="true">
    <div class="space-y-8">

        {{-- 1. KPI-Kacheln --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Fortschritt --}}
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Fortschritt</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">{{ $kpis['progress']['pct'] }} %</div>
                <div class="mt-1 text-sm text-gray-500">{{ $kpis['progress']['done'] }} von {{ $kpis['progress']['total'] }} PRs erledigt</div>
                <div class="mt-3 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full bg-green-500" style="width: {{ $kpis['progress']['pct'] }}%"></div>
                </div>
            </div>

            {{-- Story Points --}}
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Story Points</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">{{ $kpis['storyPoints']['pct'] }} %</div>
                <div class="mt-1 text-sm text-gray-500">{{ $kpis['storyPoints']['done'] }} von {{ $kpis['storyPoints']['total'] }} SP erledigt</div>
                <div class="mt-3 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full bg-green-500" style="width: {{ $kpis['storyPoints']['pct'] }}%"></div>
                </div>
            </div>

            {{-- Dateien --}}
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Dateien</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">{{ $kpis['files']['pct'] }} %</div>
                <div class="mt-1 text-sm text-gray-500">{{ $kpis['files']['done'] }} von {{ $kpis['files']['total'] }} Dateien erledigt</div>
                <div class="mt-3 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full bg-green-500" style="width: {{ $kpis['files']['pct'] }}%"></div>
                </div>
            </div>

            {{-- Tokens --}}
            <div class="bg-white rounded-lg shadow p-5">
                <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Tokens</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">{{ $kpis['tokens']['pct'] }} %</div>
                <div class="mt-1 text-sm text-gray-500">{{ $kpis['tokens']['done'] }} von {{ $kpis['tokens']['total'] }} Tokens erledigt</div>
                <div class="mt-3 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full bg-green-500" style="width: {{ $kpis['tokens']['pct'] }}%"></div>
                </div>
            </div>

            {{-- Velocity (nur wenn aus Merge-Timestamps berechenbar) --}}
            @if ($kpis['velocity'])
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Velocity</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ rtrim(rtrim(number_format($kpis['velocity']['rate'], 1, ',', ''), '0'), ',') }} <span class="text-lg font-semibold text-gray-500">SP/Wo</span></div>
                    @if ($kpis['velocity']['eta'])
                        <div class="mt-1 text-sm text-gray-500">Prognose: fertig ca. {{ $kpis['velocity']['eta'] }}</div>
                    @endif
                </div>
            @endif

            {{-- Letzter Merge (nur wenn Merge-Timestamps vorhanden) --}}
            @if ($kpis['lastMerge'])
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">Letzter Merge</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['lastMerge']['when'] }}</div>
                    <div class="mt-1 text-sm font-mono text-gray-500">{{ $kpis['lastMerge']['pr'] }}</div>
                </div>
            @endif
        </div>

        {{-- 2. Phasen-Übersicht --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold text-gray-600 mb-4">Phasen</h2>
            <div class="space-y-3">
                @foreach ($rows as $row)
                    <div x-data="{ open: false, hover: null }" class="rounded-lg ring-1 ring-gray-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800">{{ $row['phase'] }}</span>
                                @foreach ($row['blocked_by'] as $blocker)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                        🔒 blockiert durch {{ $blocker }}
                                    </span>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold" :class="hover ? hover.text : 'text-gray-500'"
                                      x-text="hover ? (hover.label + ' · ' + hover.count + ' / ' + @js($row['total']) + ' Tasks · ' + hover.pct + ' % SP') : @js($row['done'].' / '.$row['total'].' PRs ('.$row['pct'].'%)')">{{ $row['done'] }} / {{ $row['total'] }} PRs ({{ $row['pct'] }}%)</span>
                                <button type="button" @click="open = !open" class="text-xs text-indigo-600 hover:underline">
                                    <span x-show="!open">Details anzeigen</span>
                                    <span x-show="open" x-cloak>Details ausblenden</span>
                                </button>
                            </div>
                        </div>

                        {{-- Fortschrittsbalken: ein Segment je Status (SP-anteilig) --}}
                        <div class="relative mt-3">
                            {{-- Sichtbarer Balken --}}
                            <div class="flex h-2.5 overflow-hidden rounded-full bg-gray-100">
                                @foreach ($row['statuses'] as $s)
                                    <div class="h-full {{ $s['bar'] }}" style="width: {{ $s['width'] }}%"></div>
                                @endforeach
                            </div>
                            {{-- Transparente 36px-Hover-Ebene (zentriert, kein Layout-Shift) --}}
                            <div class="absolute inset-x-0 top-1/2 flex h-9 -translate-y-1/2">
                                @foreach ($row['statuses'] as $s)
                                    <div class="h-full" style="width: {{ $s['width'] }}%"
                                         @mouseenter="hover = { pct: @js(number_format($s['width'], 1, ',', '')), count: {{ $s['count'] }}, text: @js($s['text']), label: @js($s['label']) }"
                                         @mouseleave="hover = null"
                                         title="{{ $s['count'] }} {{ $s['label'] }}"></div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Status-Badges (nach echtem Status differenziert) --}}
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @foreach ($row['statuses'] as $s)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium {{ $s['badge'] }}">{{ $s['count'] }} {{ $s['label'] }}</span>
                            @endforeach
                        </div>

                        {{-- Sekundäre Metriken (aufklappbar): verbleibend / geplant --}}
                        @php $ptFmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ','); @endphp
                        <div x-show="open" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <dt class="text-gray-400 text-xs">PT</dt>
                                    <dd class="text-gray-800">{{ $ptFmt($row['pt']['remaining']) }} <span class="text-gray-400">verbl.</span> / {{ $ptFmt($row['pt']['total']) }} <span class="text-gray-400">geplant</span></dd>
                                </div>
                                <div>
                                    <dt class="text-gray-400 text-xs">Dateien</dt>
                                    <dd class="text-gray-800">{{ $row['files']['remaining'] }} <span class="text-gray-400">verbl.</span> / {{ $row['files']['total'] }} <span class="text-gray-400">geplant</span></dd>
                                </div>
                                <div>
                                    <dt class="text-gray-400 text-xs">Tokens</dt>
                                    <dd class="text-gray-800">{{ $row['tokens']['remaining'] }} <span class="text-gray-400">verbl.</span> / {{ $row['tokens']['total'] }} <span class="text-gray-400">geplant</span></dd>
                                </div>
                            </div>

                            {{-- Offene (noch nicht gemergte) PRs dieser Phase --}}
                            @if ($row['open_prs']->isNotEmpty())
                                <div class="mt-4">
                                    <dt class="text-gray-400 text-xs mb-2">Offene PRs ({{ $row['open_prs']->count() }})</dt>
                                    <ul class="space-y-1">
                                        @foreach ($row['open_prs'] as $task)
                                            <li class="flex flex-wrap items-center gap-2 text-sm">
                                                <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                                   class="font-mono font-medium text-indigo-700 hover:underline">{{ $task->name }}</a>
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $task->x_display_status->badgeClasses() }}">{{ $task->x_display_status->label() }}</span>
                                                <span class="text-gray-500 truncate">{{ $task->summary }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 3. Pickbare PRs als Karten --}}
        <div>
            <h2 class="text-sm font-semibold text-gray-600 mb-4">Pickbare PRs ({{ $pickable->count() }})</h2>
            @if ($pickable->isEmpty())
                <p class="text-sm text-gray-400">Aktuell nichts pickbar.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($pickable as $task)
                        <div class="bg-white rounded-lg p-4 {{ $loop->first ? 'ring-2 ring-indigo-500 shadow-md' : 'ring-1 ring-gray-100 shadow-sm' }}">
                            <div class="flex items-center justify-between">
                                <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                   class="font-mono font-semibold text-indigo-700 hover:underline">{{ $task->name }}</a>
                                @if ($loop->first)
                                    <span class="inline-flex items-center rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">★ Bester Pick</span>
                                @endif
                            </div>

                            <div class="mt-2 flex items-center gap-3 text-xs text-gray-500">
                                <span>{{ $task->effort_story_points }} SP</span>
                                <span>·</span>
                                <span>{{ $task->x_tokens }} Tokens</span>
                                <span>·</span>
                                <span>{{ $task->affected_files ?? '—' }} Dateien</span>
                            </div>

                            @if ($task->x_unlocks > 0)
                                <div class="mt-2 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                    schaltet {{ $task->x_unlocks }} Folge-PR{{ $task->x_unlocks === 1 ? '' : 's' }} frei
                                </div>
                            @endif

                            <p class="mt-2 text-sm text-gray-600">{{ $task->summary }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-status-shell>
