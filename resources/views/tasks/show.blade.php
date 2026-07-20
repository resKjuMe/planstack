@php
    // Anthropic/Claude-Logo (viewBox 0 0 24 24) für den "mit Claude"-Button.
    $claudeLogoPath = 'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';

    $rec = $task->last_review_recommendation;
    $isApprove = $rec === \App\Enums\ReviewRecommendation::APPROVE;
    $isChanges = $rec === \App\Enums\ReviewRecommendation::REQUEST_CHANGES;
    $hasReview = $task->last_reviewed_at || $task->reviewed_by || $task->status === \App\Enums\TaskStatus::IN_REVIEW;
    $concernOpen = (bool) $task->concern;
    $claimed = (bool) $task->claimed_by_id;

    // Titel = Zusammenfassung ohne abschließenden Klammerzusatz; dieser wandert
    // in die Untertitel-Zeile.
    $title = $task->summary;
    $subtitle = null;
    if (preg_match('/^(.*\S)\s*\((.+)\)\s*$/u', $task->summary, $m)) {
        $title = $m[1];
        $subtitle = $m[2];
    }

    // Beschreibung: Verlaufs-Absätze herauslösen (→ Timeline), Rest bereinigt zeigen.
    $descParsed = \App\Support\TaskContentParser::descriptionEvents((string) $task->description);
    $descClean = $descParsed['clean'];
    $descLong = mb_strlen(strip_tags($descClean)) > 320;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('projects.show', $project) }}" class="font-mono text-sm text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">{{ $project->alias }}</a>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <span class="rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-0.5 font-mono text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $task->name }}</span>
                <x-task-status :status="$task->status" :label="true" />
                @if ($rec)
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $isApprove ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : ($isChanges ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400') }}">
                        @if ($isApprove)
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5l10 -10"/></svg>
                        @elseif ($isChanges)
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                        @endif
                        {{ $rec->label() }}
                    </span>
                @endif
                @if ($task->criticality)
                    <span title="{{ __('tasks.criticality') }}" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $task->criticality->badgeClasses() }}">
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                        {{ $task->criticality->label() }}
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @can('claim', $task)
                    @if ($claimed && $concernOpen)
                        {{-- Freigeben bei offenem Concern gesperrt (Tooltip, da native title auf disabled-Buttons unzuverlässig). --}}
                        <span class="relative inline-block" x-data="{ tip: false }" @mouseenter="tip = true" @mouseleave="tip = false">
                            <button type="button" disabled
                                    class="cursor-not-allowed rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-400 dark:text-gray-500 opacity-60 ring-1 ring-gray-300 dark:ring-gray-600">{{ __('common.release') }}</button>
                            <span x-show="tip" x-cloak
                                  class="absolute right-0 top-full z-10 mt-1 w-56 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg">
                                {{ __('tasks.cannot_be_released_while_a_concern_is') }}
                            </span>
                        </span>
                    @else
                        <form method="POST" action="{{ route('projects.tasks.claim', [$project, $task]) }}">
                            @csrf
                            <button class="rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                {{ $claimed ? __('common.release') : __('common.claim') }}
                            </button>
                        </form>
                    @endif
                @endcan
                @can('update', $task)
                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                       class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('common.edit') }}</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            {{-- Titel + Untertitel + Meta-Chips --}}
            <div class="space-y-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-0.5 font-mono text-sm text-gray-400 dark:text-gray-500">{{ $subtitle }}</p>
                    @endif
                </div>
                @include('tasks.partials._meta-chips', ['task' => $task, 'project' => $project])
            </div>

            {{-- Offener Concern als Warnbanner --}}
            @if ($concernOpen)
                @include('tasks.partials._concern-banner', ['task' => $task, 'project' => $project, 'claudeLogoPath' => $claudeLogoPath])
            @endif

            {{-- Zweispaltiges Layout ab Desktop: links 8, rechts 4 --}}
            <div class="grid gap-6 lg:grid-cols-12">
                {{-- Hauptspalte --}}
                <div class="space-y-6 lg:col-span-8">
                    @if (filled($descClean))
                        <section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6" @if ($descLong) x-data="disclosure({ id: 'beschreibung' })" id="beschreibung" @endif>
                            <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('common.description') }}</h3>
                            @if ($descLong)
                                <div :class="open ? '' : 'line-clamp-[8]'"><x-markdown :content="$descClean" /></div>
                                <button type="button" @click="toggle()" class="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                        x-text="open ? '{{ __('tasks.show_less') }}' : '{{ __('tasks.show_more') }}'"></button>
                            @else
                                <x-markdown :content="$descClean" />
                            @endif
                        </section>
                    @endif

                    @if (filled($task->description_target_actual))
                        @include('tasks.partials._target-actual', ['task' => $task])
                    @endif

                    @include('tasks.partials._checklist', [
                        'task' => $task, 'project' => $project, 'kind' => 'acceptance',
                        'title' => __('common.acceptance_criteria'), 'source' => $task->description_acceptance_criteria,
                    ])

                    @include('tasks.partials._checklist', [
                        'task' => $task, 'project' => $project, 'kind' => 'test',
                        'title' => __('tasks.test_instructions'), 'source' => $task->description_test_cases, 'unit' => __('tasks.checked'),
                    ])

                    @if ($hasReview)
                        @include('tasks.partials._review', ['task' => $task])
                    @endif

                    @include('tasks.partials._timeline', ['task' => $task, 'events' => $descParsed['events']])
                </div>

                {{-- Sticky-Sidebar --}}
                <div class="space-y-4 self-start lg:col-span-4 lg:sticky lg:top-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 space-y-3 text-sm">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('common.overview') }}</h3>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400 dark:text-gray-500">{{ __('common.status') }}</span>
                            <x-task-status :status="$task->status" :label="true" />
                        </div>
                        @if ($task->criticality)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 dark:text-gray-500">{{ __('tasks.criticality') }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $task->criticality->badgeClasses() }}">{{ $task->criticality->label() }}</span>
                            </div>
                        @endif
                        @if ($hasReview)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 dark:text-gray-500">{{ __('tasks.review') }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $isApprove ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : ($isChanges ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400') }}">
                                    {{ $rec?->label() ?? __('tasks.pending') }}
                                </span>
                            </div>
                        @endif
                        @if ($task->effort_story_points !== null)
                            <div class="flex items-center justify-between"><span class="text-gray-400 dark:text-gray-500">{{ __('tasks.effort') }}</span><span class="text-gray-700 dark:text-gray-300">{{ __('tasks.points_sp', ['points' => $task->effort_story_points]) }}</span></div>
                        @endif
                    </div>

                    @include('tasks.partials._requirements', ['task' => $task, 'project' => $project])

                    {{-- Leerer Concern: kleine Zeile statt Karte --}}
                    @unless ($concernOpen)
                        <div class="flex items-center justify-between rounded-lg bg-white dark:bg-gray-800 px-5 py-3 text-xs text-gray-500 dark:text-gray-400 shadow">
                            <span>{{ __('tasks.no_concern') }}</span>
                            @can('update', $task)
                                <a href="{{ route('projects.tasks.concern.edit', [$project, $task]) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('common.create') }}</a>
                            @endcan
                        </div>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    {{-- Inline-Alpine-Komponenten: Checkbox-Optimistic-Toggle, Disclosure (Hash-linkbar),
         Concern-Entscheidungs-Wizard. Läuft als nicht-Modul-Script vor dem deferred
         app.js, registriert sich also rechtzeitig auf alpine:init. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            // Ein Checklisten-Item: die Checkbox toggelt nativ (x-model), das Häkchen
            // erscheint sofort; wir persistieren optimistisch und rollen bei Fehler zurück.
            Alpine.data('acItem', (cfg) => ({
                checked: cfg.checked,
                url: cfg.url,
                busy: false,
                toggle() {
                    const next = this.checked; // x-model hat den neuen Wert bereits gesetzt
                    this.$dispatch('item-count', next ? 1 : -1);
                    this.busy = true;
                    fetch(this.url, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ checked: next }),
                    })
                        .then((r) => (r.ok ? r.json() : Promise.reject()))
                        .then(() => this.$dispatch('item-saved'))
                        .catch(() => {
                            this.checked = !next; // Rollback (x-model spiegelt es zurück auf die Box)
                            this.$dispatch('item-count', next ? -1 : 1);
                            this.$dispatch('item-error');
                        })
                        .finally(() => { this.busy = false; });
                },
            }));

            // Aufklapp-Sektion, deren Zustand per URL-Hash verlinkbar ist.
            Alpine.data('disclosure', (cfg = {}) => ({
                open: !!cfg.open,
                id: cfg.id || null,
                init() {
                    if (this.id && window.location.hash === '#' + this.id) this.open = true;
                },
                toggle() {
                    this.open = !this.open;
                    if (!this.id) return;
                    if (this.open) {
                        history.replaceState(null, '', '#' + this.id);
                    } else if (window.location.hash === '#' + this.id) {
                        history.replaceState(null, '', window.location.pathname + window.location.search);
                    }
                },
            }));

            Alpine.data('claudeDecisions', (config) => ({
                alias: config.alias,
                taskName: config.taskName,
                ticketUrl: config.ticketUrl,
                summary: config.summary,
                decisions: config.decisions,
                step: 0,
                answers: {},
                custom: {},

                get total() { return this.decisions.length; },
                get current() { return this.decisions[this.step] || { question: '', options: [] }; },
                get done() { return this.step >= this.total; },
                get answered() { return this.decisions.filter((d, i) => this.value(i) !== null).length; },
                get canProceed() { return this.value(this.step) !== null; },

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
                        @js(__('tasks.concern_decisions_intro')).replace(':ticket', this.alias + '/' + this.taskName),
                        '',
                        'Concern: ' + this.summary,
                        'Ticket: ' + this.ticketUrl,
                        '',
                        @js(__('tasks.decisions_made')),
                    ];
                    this.decisions.forEach((d, i) => {
                        lines.push((i + 1) + '. ' + d.question);
                        lines.push('   → ' + (this.value(i) || @js(__('tasks.not_specified'))));
                    });
                    lines.push('');
                    lines.push(@js(__('tasks.implement_these_decisions')));

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
