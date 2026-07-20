{{-- Offener Concern als Warnbanner direkt unter dem Kopf (Titel, Autor, Kurztext,
     Aktionen + „Entscheidung mit Claude finden"). Nur bei vorhandenem Concern. --}}
@props(['task', 'project', 'claudeLogoPath'])

@php $c = $task->concern; @endphp

<div class="rounded-lg border border-orange-300 bg-orange-50 p-5">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-semibold text-orange-900">{{ $c->summary }}</p>
                    <p class="text-xs text-orange-700/80">{{ __('tasks.concern_by_name', ['name' => $c->creator?->name]) }}</p>
                </div>
                @can('update', $task)
                    <div class="flex items-center gap-3 text-sm">
                        <a href="{{ route('projects.tasks.concern.edit', [$project, $task]) }}" class="font-medium text-orange-800 hover:underline">{{ __('common.edit') }}</a>
                        <form method="POST" action="{{ route('projects.tasks.concern.destroy', [$project, $task]) }}"
                              onsubmit="return confirm('{{ __('tasks.remove_concern') }}');">
                            @csrf @method('DELETE')
                            <button class="font-medium text-red-600 hover:underline">{{ __('common.remove') }}</button>
                        </form>
                    </div>
                @endcan
            </div>

            @php
                $details = array_filter([
                    'tasks.context' => $c->description_context,
                    'tasks.blocker' => $c->description_blocker,
                    'tasks.misconception' => $c->description_misconception,
                ]);
            @endphp
            @if ($details)
                <div class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
                    @foreach ($details as $label => $value)
                        <div @class(['sm:col-span-2' => $label === 'tasks.blocker'])>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-orange-700/70">{{ __($label) }}</dt>
                            <dd class="text-orange-900/90"><x-markdown :content="$value" /></dd>
                        </div>
                    @endforeach
                </div>
            @endif

            @php
                // Entscheidungen strukturiert einlesen (eine pro Zeile, Optionen als CSV ";").
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
                    if (count($parts) && preg_match('/^(.*?)\(a\)\s*(.+)$/su', $question, $m)) {
                        $question = trim($m[1], " \t\n\r\0\x0B:-") ?: __('tasks.decision');
                        array_unshift($parts, trim($m[2]));
                    }
                    $options = array_map(
                        fn ($o) => preg_replace('/^\([a-z]\)\s*/i', '', $o),
                        array_filter($parts, fn ($o) => $o !== ''),
                    );
                    $decisions[] = ['question' => $question, 'options' => array_values($options)];
                }
            @endphp

            @if (count($decisions))
                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-orange-700/70">{{ __('common.open_decisions') }}</h4>
                        <button type="button" x-data
                                @click="$dispatch('open-modal', 'claude-decisions')"
                                class="inline-flex items-center gap-2 rounded-md bg-[#D97757] px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-[#c96544] focus:outline-none focus:ring-2 focus:ring-[#D97757] focus:ring-offset-1">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
                            {{ __('tasks.find_a_decision_with_claude') }}
                        </button>
                    </div>
                    <ul class="space-y-2">
                        @foreach ($decisions as $d)
                            <li class="text-sm">
                                <span class="font-medium text-orange-900">{{ $d['question'] }}</span>
                                @if (count($d['options']))
                                    <ul class="ms-4 list-disc text-orange-800/80">
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
                        <div class="mb-4 flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D97757]/10">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="#D97757" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900">{{ __('tasks.find_a_decision_with_claude') }}</h3>
                                <p class="truncate text-xs text-gray-500" x-text="summary"></p>
                            </div>
                        </div>

                        <template x-if="!done">
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs text-gray-400">
                                    <span x-text="'{{ __('tasks.decision') }} ' + (step + 1) + ' {{ __('tasks.of') }} ' + total"></span>
                                    <span x-text="answered + ' {{ __('tasks.answered') }}'"></span>
                                </div>
                                <div class="mb-5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-[#D97757] transition-all" :style="'width:' + ((step + 1) / total * 100) + '%'"></div>
                                </div>

                                <p class="mb-3 font-medium text-gray-900" x-text="current.question"></p>

                                <div class="space-y-2">
                                    <template x-for="(opt, i) in current.options" :key="i">
                                        <button type="button" @click="choose(opt)"
                                                class="flex w-full items-start gap-2 rounded-md border px-3 py-2 text-left text-sm transition"
                                                :class="answers[step] === opt ? 'border-[#D97757] bg-[#D97757]/5 text-gray-900 ring-1 ring-[#D97757]' : 'border-gray-200 text-gray-700 hover:border-gray-300 hover:bg-gray-50'">
                                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500" x-text="String.fromCharCode(65 + i)"></span>
                                            <span x-text="opt"></span>
                                        </button>
                                    </template>
                                </div>

                                <div class="mt-3">
                                    <label class="mb-1 block text-xs text-gray-400" x-text="current.options.length ? '{{ __('tasks.or_your_own_answer') }}' : '{{ __('tasks.answer') }}'"></label>
                                    <input type="text" x-model="custom[step]" @input="answers[step] = undefined"
                                           placeholder="{{ __('tasks.enter_your_own_decision') }}"
                                           class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#D97757] focus:ring-[#D97757]">
                                </div>

                                <div class="mt-6 flex items-center justify-between">
                                    <button type="button" @click="prev()" x-show="step > 0" class="text-sm text-gray-500 hover:text-gray-700">{{ __('tasks.back') }}</button>
                                    <span x-show="step === 0"></span>
                                    <button type="button" @click="next()" :disabled="!canProceed"
                                            class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40"
                                            x-text="step + 1 === total ? '{{ __('tasks.to_summary') }}' : '{{ __('tasks.next') }}'"></button>
                                </div>
                            </div>
                        </template>

                        <template x-if="done">
                            <div>
                                <p class="mb-3 text-sm text-gray-500">{{ __('tasks.decisions_made_review_them_and_launch') }}</p>
                                <ol class="mb-5 space-y-3">
                                    <template x-for="(d, i) in decisions" :key="i">
                                        <li class="text-sm">
                                            <p class="font-medium text-gray-800" x-text="(i + 1) + '. ' + d.question"></p>
                                            <div class="ms-4 flex items-center justify-between gap-3">
                                                <span class="text-[#a8492e]" x-text="'→ ' + (value(i) || '{{ __('tasks.not_specified') }}')"></span>
                                                <button type="button" class="shrink-0 text-xs text-indigo-600 hover:underline" @click="step = i">{{ __('tasks.change') }}</button>
                                            </div>
                                        </li>
                                    </template>
                                </ol>
                                <div class="flex items-center justify-between">
                                    <button type="button" @click="step = total - 1" class="text-sm text-gray-500 hover:text-gray-700">{{ __('tasks.back') }}</button>
                                    <button type="button" @click="launch()"
                                            class="inline-flex items-center gap-2 rounded-md bg-[#D97757] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#c96544]">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="{{ $claudeLogoPath }}" /></svg>
                                        {{ __('tasks.implement_with_claude') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </x-modal>
            @endif
        </div>
    </div>
</div>
