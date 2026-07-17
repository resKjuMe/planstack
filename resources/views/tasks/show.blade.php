@php
    // Anthropic/Claude-Logo (viewBox 0 0 24 24) für den "mit Claude"-Button.
    $claudeLogoPath = 'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-400 hover:text-gray-600 font-mono">{{ $project->alias }}</a>
                <span class="text-gray-300">/</span>
                <h2 class="font-mono font-semibold text-xl text-gray-800">{{ $task->name }}</h2>
                <x-task-status :status="$task->status" />
            </div>
            <div class="flex items-center gap-2">
                @can('claim', $task)
                    <form method="POST" action="{{ route('projects.tasks.claim', [$project, $task]) }}">
                        @csrf
                        <button class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">
                            {{ $task->claimed_by_id ? 'Freigeben' : 'Beanspruchen' }}
                        </button>
                    </form>
                @endcan
                @can('update', $task)
                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                       class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Bearbeiten</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6 space-y-4">
                <p class="text-lg text-gray-900">{{ $task->summary }}</p>

                @php $repo = $project->githubRepo(); @endphp
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                    <div><dt class="text-gray-400">Erstellt von</dt><dd class="text-gray-800">{{ $task->creator?->name }}</dd></div>
                    <div><dt class="text-gray-400">Beansprucht von</dt><dd class="text-gray-800">{{ $task->claimer?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Phase</dt><dd class="text-gray-800">{{ $task->phase?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Aufwand</dt><dd class="text-gray-800">
                        {{ $task->effort_man_days ?? '–' }} PT ·
                        {{ $task->effort_story_points ?? '–' }} SP ·
                        {{ $task->effort_tokens ?? '–' }} Tok
                    </dd></div>
                    <div><dt class="text-gray-400">PR</dt><dd class="text-gray-800">
                        @if ($task->pr_number && $repo)
                            <a href="https://github.com/{{ $repo }}/pull/{{ $task->pr_number }}" target="_blank" rel="noopener"
                               class="font-mono text-indigo-700 hover:underline">#{{ $task->pr_number }}</a>
                        @elseif ($task->pr_number)
                            <span class="font-mono">#{{ $task->pr_number }}</span>
                        @else — @endif
                    </dd></div>
                </dl>

                @if ($task->description)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 mb-1">Beschreibung</h3>
                        <x-markdown :content="$task->description" />
                    </div>
                @endif

                @if ($task->description_acceptance_criteria)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 mb-1">Akzeptanzkriterien</h3>
                        <x-markdown :content="$task->description_acceptance_criteria" />
                    </div>
                @endif

                @if ($task->description_target_actual)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 mb-1">IST/SOLL-Vergleich</h3>
                        <x-markdown :content="$task->description_target_actual" />
                    </div>
                @endif

                @if ($task->description_test_cases)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 mb-1">Testfälle / Testanleitung</h3>
                        <x-markdown :content="$task->description_test_cases" />
                    </div>
                @endif

                @if ($task->affected_files !== null)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 mb-1">Betroffene Dateien (geschätzt)</h3>
                        <p class="text-sm text-gray-800">{{ $task->affected_files }}</p>
                    </div>
                @endif
            </div>

            {{-- Review-Ergebnis --}}
            @if ($task->last_reviewed_at || $task->reviewed_by || $task->status === \App\Enums\TaskStatus::IN_REVIEW)
                @php
                    $rec = $task->last_review_recommendation;
                    $recClass = $rec === \App\Enums\ReviewRecommendation::APPROVE
                        ? 'bg-green-100 text-green-700'
                        : ($rec === \App\Enums\ReviewRecommendation::REQUEST_CHANGES ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500');
                @endphp
                <div class="bg-white rounded-lg shadow p-6 space-y-3 border border-purple-100">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">Review</h3>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $recClass }}">
                            {{ $rec?->label() ?? 'ausstehend' }}
                        </span>
                    </div>
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div><dt class="text-gray-400">Reviewer</dt><dd class="text-gray-800">{{ $task->reviewer?->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-400">Zuletzt reviewt</dt><dd class="text-gray-800">{{ $task->last_reviewed_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
                    </dl>
                    @if ($task->last_review_summary)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-500 mb-1">Analyse</h4>
                            <x-markdown :content="$task->last_review_summary" />
                        </div>
                    @endif
                </div>
            @endif

            {{-- Requirements graph --}}
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Voraussetzungen</h3>
                    @forelse ($task->prerequisites as $pre)
                        <a href="{{ route('projects.tasks.show', [$project, $pre]) }}" class="block text-sm text-indigo-700 hover:underline font-mono">{{ $pre->name }} <span class="text-gray-500 font-sans">— {{ $pre->summary }}</span></a>
                    @empty
                        <p class="text-sm text-gray-400">Keine.</p>
                    @endforelse
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Blockiert</h3>
                    @forelse ($task->dependents as $dep)
                        <a href="{{ route('projects.tasks.show', [$project, $dep]) }}" class="block text-sm text-indigo-700 hover:underline font-mono">{{ $dep->name }} <span class="text-gray-500 font-sans">— {{ $dep->summary }}</span></a>
                    @empty
                        <p class="text-sm text-gray-400">Keine.</p>
                    @endforelse
                </div>
            </div>

            {{-- Concern --}}
            <div class="bg-white rounded-lg shadow p-6 border {{ $task->concern ? 'border-orange-200' : 'border-gray-100' }}">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">⚠ Concern</h3>
                    @can('update', $task)
                        <a href="{{ route('projects.tasks.concern.edit', [$project, $task]) }}"
                           class="text-sm text-indigo-600 hover:underline">{{ $task->concern ? 'Bearbeiten' : 'Anlegen' }}</a>
                    @endcan
                </div>

                @if ($task->concern)
                    @php $c = $task->concern; @endphp
                    <p class="text-sm font-medium text-gray-900">{{ $c->summary }}</p>
                    <p class="text-xs text-gray-400 mb-3">von {{ $c->creator?->name }}</p>

                    <div class="grid gap-4 sm:grid-cols-2 text-sm">
                        @foreach ([
                            'Kontext' => $c->description_context,
                            'Blocker' => $c->description_blocker,
                            'Fehleinschätzung' => $c->description_misconception,
                        ] as $label => $value)
                            @if ($value)
                                <div @class(['sm:col-span-2' => $label === 'Blocker'])>
                                    <dt class="text-gray-400 mb-1">{{ $label }}</dt>
                                    <dd><x-markdown :content="$value" /></dd>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @php
                        // Entscheidungen strukturiert einlesen: eine pro Zeile, Frage + Optionen als CSV (";").
                        $decisions = [];
                        foreach (preg_split('/\r\n|\r|\n/', trim((string) $c->description_decisions)) as $line) {
                            if (trim($line) === '') {
                                continue;
                            }
                            $parts = array_map('trim', str_getcsv($line, ';'));
                            $question = array_shift($parts);
                            if ($question === null || $question === '') {
                                continue;
                            }

                            // Manche Concerns betten Option (a) direkt in den Fragetext
                            // ein, statt sie wie (b)/(c) als eigenes Feld abzutrennen
                            // (z. B. "Frage: Optionen: (a) ... ;(b) ...;(c) ..."). An
                            // "(a) " splitten, wenn danach noch Optionen folgen.
                            if (count($parts) && preg_match('/^(.*?)\(a\)\s*(.+)$/su', $question, $m)) {
                                $question = trim($m[1], " \t\n\r\0\x0B:-") ?: 'Entscheidung';
                                array_unshift($parts, trim($m[2]));
                            }

                            // Führende Buchstaben-Label wie "(a) "/"(b) " aus den
                            // Optionstexten entfernen; die Reihenfolge liefert das
                            // Label fürs UI (Pill) stattdessen selbst.
                            $options = array_map(
                                fn ($o) => preg_replace('/^\([a-z]\)\s*/i', '', $o),
                                array_filter($parts, fn ($o) => $o !== ''),
                            );

                            $decisions[] = [
                                'question' => $question,
                                'options' => array_values($options),
                            ];
                        }
                    @endphp

                    @if (count($decisions))
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-gray-400 text-sm">Offene Entscheidungen</h4>
                                <button type="button" x-data
                                        @click="$dispatch('open-modal', 'claude-decisions')"
                                        class="inline-flex items-center gap-2 rounded-md bg-[#D97757] px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-[#c96544] focus:outline-none focus:ring-2 focus:ring-[#D97757] focus:ring-offset-1">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
                                    Entscheidung mit Claude finden
                                </button>
                            </div>
                            <ul class="space-y-2">
                                @foreach ($decisions as $d)
                                    <li class="text-sm">
                                        <span class="font-medium text-gray-800">{{ $d['question'] }}</span>
                                        @if (count($d['options']))
                                            <ul class="ms-4 list-disc text-gray-600">
                                                @foreach ($d['options'] as $option)
                                                    <li>{{ $option }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @php
                            $claudeConfig = [
                                'alias' => $project->alias,
                                'taskName' => $task->name,
                                'ticketUrl' => route('projects.tasks.show', [$project, $task]),
                                'summary' => $c->summary,
                                'decisions' => $decisions,
                            ];
                        @endphp

                        <x-modal name="claude-decisions" maxWidth="lg" focusable>
                            <div class="p-6"
                                 x-data="claudeDecisions(@js($claudeConfig))"
                                 x-on:open-modal.window="$event.detail === 'claude-decisions' && reset()">
                                {{-- Kopf --}}
                                <div class="flex items-start gap-3 mb-4">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D97757]/10">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="#D97757" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-gray-900">Entscheidung mit Claude finden</h3>
                                        <p class="text-xs text-gray-500 truncate" x-text="summary"></p>
                                    </div>
                                </div>

                                {{-- Fortschritt --}}
                                <template x-if="!done">
                                    <div>
                                        <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                                            <span x-text="'Entscheidung ' + (step + 1) + ' von ' + total"></span>
                                            <span x-text="answered + ' beantwortet'"></span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-gray-100 overflow-hidden mb-5">
                                            <div class="h-full rounded-full bg-[#D97757] transition-all"
                                                 :style="'width:' + ((step + 1) / total * 100) + '%'"></div>
                                        </div>

                                        <p class="font-medium text-gray-900 mb-3" x-text="current.question"></p>

                                        <div class="space-y-2">
                                            <template x-for="(opt, i) in current.options" :key="i">
                                                <button type="button"
                                                        @click="choose(opt)"
                                                        class="flex w-full items-start gap-2 rounded-md border px-3 py-2 text-left text-sm transition"
                                                        :class="answers[step] === opt
                                                            ? 'border-[#D97757] bg-[#D97757]/5 text-gray-900 ring-1 ring-[#D97757]'
                                                            : 'border-gray-200 text-gray-700 hover:border-gray-300 hover:bg-gray-50'">
                                                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500"
                                                          x-text="String.fromCharCode(65 + i)"></span>
                                                    <span x-text="opt"></span>
                                                </button>
                                            </template>
                                        </div>

                                        <div class="mt-3">
                                            <label class="block text-xs text-gray-400 mb-1"
                                                   x-text="current.options.length ? 'Oder eigene Antwort' : 'Antwort'"></label>
                                            <input type="text"
                                                   x-model="custom[step]"
                                                   @input="answers[step] = undefined"
                                                   placeholder="Eigene Entscheidung eingeben …"
                                                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-[#D97757] focus:ring-[#D97757]">
                                        </div>

                                        <div class="mt-6 flex items-center justify-between">
                                            <button type="button" @click="prev()" x-show="step > 0"
                                                    class="text-sm text-gray-500 hover:text-gray-700">Zurück</button>
                                            <span x-show="step === 0"></span>
                                            <button type="button" @click="next()" :disabled="!canProceed"
                                                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                                    x-text="step + 1 === total ? 'Zur Übersicht' : 'Weiter'"></button>
                                        </div>
                                    </div>
                                </template>

                                {{-- Übersicht --}}
                                <template x-if="done">
                                    <div>
                                        <p class="text-sm text-gray-500 mb-3">Getroffene Entscheidungen — prüfe und starte Claude, um sie umzusetzen:</p>
                                        <ol class="space-y-3 mb-5">
                                            <template x-for="(d, i) in decisions" :key="i">
                                                <li class="text-sm">
                                                    <p class="font-medium text-gray-800" x-text="(i + 1) + '. ' + d.question"></p>
                                                    <div class="flex items-center justify-between gap-3 ms-4">
                                                        <span class="text-[#a8492e]" x-text="'→ ' + (value(i) || '(keine Angabe)')"></span>
                                                        <button type="button" class="text-xs text-indigo-600 hover:underline shrink-0"
                                                                @click="step = i">Ändern</button>
                                                    </div>
                                                </li>
                                            </template>
                                        </ol>
                                        <div class="flex items-center justify-between">
                                            <button type="button" @click="step = total - 1"
                                                    class="text-sm text-gray-500 hover:text-gray-700">Zurück</button>
                                            <button type="button" @click="launch()"
                                                    class="inline-flex items-center gap-2 rounded-md bg-[#D97757] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#c96544]">
                                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
                                                Mit Claude umsetzen
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </x-modal>
                    @endif

                    @can('update', $task)
                        <form method="POST" action="{{ route('projects.tasks.concern.destroy', [$project, $task]) }}" class="mt-4"
                              onsubmit="return confirm('Concern entfernen?');">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Concern entfernen</button>
                        </form>
                    @endcan
                @else
                    <p class="text-sm text-gray-400">Kein Concern erfasst.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Wizard-Logik für "Entscheidung mit Claude finden".
         Läuft als inline (nicht-Modul) Script vor dem deferred Vite-app.js, registriert
         sich also rechtzeitig auf alpine:init. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('claudeDecisions', (config) => ({
                alias: config.alias,
                taskName: config.taskName,
                ticketUrl: config.ticketUrl,
                summary: config.summary,
                decisions: config.decisions,
                step: 0,
                answers: {},   // step -> gewählte Option
                custom: {},    // step -> freie Antwort

                get total() { return this.decisions.length; },
                get current() { return this.decisions[this.step] || { question: '', options: [] }; },
                get done() { return this.step >= this.total; },
                get answered() { return this.decisions.filter((d, i) => this.value(i) !== null).length; },
                get canProceed() { return this.value(this.step) !== null; },

                // Finale Antwort einer Entscheidung: freie Antwort schlägt gewählte Option.
                value(i) {
                    const c = (this.custom[i] || '').trim();
                    if (c) return c;
                    return this.answers[i] ?? null;
                },

                choose(opt) {
                    this.answers[this.step] = opt;
                    this.custom[this.step] = '';
                },

                next() { if (this.canProceed && this.step < this.total) this.step++; },
                prev() { if (this.step > 0) this.step--; },

                reset() {
                    this.step = 0;
                    this.answers = {};
                    this.custom = {};
                },

                launch() {
                    const lines = [
                        'Am Planstack-Ticket ' + this.alias + '/' + this.taskName + ' wurde ein Concern gemeldet. Der Owner hat die offenen Entscheidungen nun getroffen.',
                        '',
                        'Concern: ' + this.summary,
                        'Ticket: ' + this.ticketUrl,
                        '',
                        'Getroffene Entscheidungen:',
                    ];
                    this.decisions.forEach((d, i) => {
                        lines.push((i + 1) + '. ' + d.question);
                        lines.push('   → ' + (this.value(i) || '(keine Angabe)'));
                    });
                    lines.push('');
                    lines.push('Bitte setze diese Entscheidungen um: löse den Concern auf, passe Plan/Umsetzung entsprechend an und arbeite das Ticket weiter ab.');

                    const prompt = lines.join('\n');
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(prompt).catch(() => {});
                    }
                    window.location.href = 'claudetask:' + encodeURIComponent(prompt);
                    this.$dispatch('close-modal', 'claude-decisions');
                },
            }));
        });
    </script>
</x-app-layout>
