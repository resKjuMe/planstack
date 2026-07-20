@php
    use App\Enums\TaskStatus;

    // Kleiner Helfer: farbiges Status-Badge (nutzt dieselben Klassen wie überall).
    $badge = fn (TaskStatus $s) => '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$s->badgeClasses().'">'.$s->label().'</span>';

    // Bedeutung + Herkunft je Status (gespeichert = per Aktion gesetzt,
    // abgeleitet = nur zur Anzeige aus Abhängigkeiten berechnet).
    $meta = [
        'UNKNOWN'     => ['faq.status_unknown_desc', ['faq.stored', 'faq.derived']],
        'BLOCKED'     => ['faq.status_blocked_desc', ['faq.derived']],
        'CONCERNED'   => ['faq.status_concerned_desc', ['faq.stored']],
        'PICKABLE'    => ['faq.status_pickable_desc', ['faq.stored', 'faq.derived']],
        'CLAIMED'     => ['faq.status_claimed_desc', ['faq.stored']],
        'ANALYZING'   => ['faq.status_analyzing_desc', ['faq.stored']],
        'IN_PROGRESS' => ['faq.status_in_progress_desc', ['faq.stored']],
        'IN_REVIEW'   => ['faq.status_in_review_desc', ['faq.stored']],
        'COMPLETED'   => ['faq.status_completed_desc', ['faq.stored']],
        'MERGED'      => ['faq.status_merged_desc', ['faq.stored']],
    ];

    // Auslöser → resultierender Status (Reihenfolge grob nach Lebenszyklus).
    $transitions = [
        ['faq.trigger_create_task', [TaskStatus::UNKNOWN], 'faq.note_new_task_no_status'],
        ['faq.trigger_claim', [TaskStatus::CLAIMED], 'faq.note_sets_assignee_timestamp'],
        ['faq.trigger_release', [TaskStatus::PICKABLE], 'faq.note_assignee_removed'],
        ['faq.trigger_status_analyze', [TaskStatus::ANALYZING], null],
        ['faq.trigger_status_in_progress', [TaskStatus::IN_PROGRESS], null],
        ['faq.trigger_status_in_review', [TaskStatus::IN_REVIEW], null],
        ['faq.trigger_status_done', [TaskStatus::IN_REVIEW, TaskStatus::IN_PROGRESS], 'faq.note_in_review_if_pr_else_in_progress'],
        ['faq.trigger_report_problem', [TaskStatus::CONCERNED], 'faq.note_unless_already_merged'],
        ['faq.trigger_resolve_problem', [TaskStatus::CLAIMED, TaskStatus::PICKABLE], 'faq.note_claimed_or_pickable_on_resolve'],
        ['faq.trigger_merge_manual', [TaskStatus::MERGED], 'faq.note_stamps_merge_timestamp'],
        ['faq.trigger_pr_sync_detects_merge', [TaskStatus::MERGED], 'faq.note_github_reports_merged'],
        ['faq.trigger_split_task', [TaskStatus::COMPLETED, TaskStatus::UNKNOWN], 'faq.note_split_parent_done_children_pending'],
        ['faq.trigger_manual_edit', [], 'faq.note_any_status_settable'],
        ['faq.trigger_claim_review', [], 'faq.note_no_status_change_sets_reviewer'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('faq.faq') }}</h2>
    </x-slot>

    <x-slot name="subheader">
        <x-faq-tabs active="status-logic" />
    </x-slot>

    <style>[x-cloak]{display:none !important;}</style>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-gray-500">
                {{ __('faq.how_a_task_s_status_comes_about_and_2') }} <span class="font-medium text-gray-700">{{ __('faq.no_formal_state_machine') }}</span> {{ __('faq.every_status_is_set_by_a_concrete') }}
            </p>

            {{-- 1) Status-Legende --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('faq.the_statuses_at_a_glance') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('faq.ten_states_from_an_open_start_to_the') }}</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                            <th class="px-6 py-2 font-medium">{{ __('common.status') }}</th>
                            <th class="px-6 py-2 font-medium">{{ __('faq.value') }}</th>
                            <th class="px-6 py-2 font-medium">{{ __('common.meaning') }}</th>
                            <th class="px-6 py-2 font-medium">{{ __('faq.origin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse(TaskStatus::displayOrder()) as $status)
                            @php [$desc, $kinds] = $meta[$status->name]; @endphp
                            <tr class="border-b border-gray-50 last:border-0 align-top">
                                <td class="px-6 py-3 whitespace-nowrap">{!! $badge($status) !!}</td>
                                <td class="px-6 py-3 whitespace-nowrap font-mono text-xs text-gray-500">{{ $status->value }}</td>
                                <td class="px-6 py-3 text-gray-700">{{ __($desc) }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($kinds as $kind)
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium {{ $kind === 'faq.derived' ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : 'bg-gray-100 text-gray-500' }}">{{ __($kind) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 2) Auslöser → Status --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('faq.how_a_status_is_set') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('faq.each_row_is_an_action_and_the_status_it') }}</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                            <th class="px-6 py-2 font-medium">{{ __('faq.trigger') }}</th>
                            <th class="px-6 py-2 font-medium">{{ __('faq.result') }}</th>
                            <th class="px-6 py-2 font-medium">{{ __('faq.note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transitions as [$trigger, $results, $note])
                            <tr class="border-b border-gray-50 last:border-0 align-top">
                                <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-800">{{ __($trigger) }}</td>
                                <td class="px-6 py-3">
                                    @forelse ($results as $i => $r)
                                        @if ($i > 0)<span class="mx-1 text-gray-400">/</span>@endif{!! $badge($r) !!}
                                    @empty
                                        <span class="text-xs text-gray-400">{{ __('faq.any_no_change') }}</span>
                                    @endforelse
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $note ? __($note) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 3) Regeln für Claude während der Bearbeitung --}}
            <div class="bg-white rounded-lg shadow overflow-hidden ring-1 ring-indigo-100">
                <div class="px-6 py-4 border-b border-gray-100 bg-indigo-50/40">
                    <h3 class="font-semibold text-gray-900">{{ __('faq.rules_for_claude_while_working') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('faq.this_is_how_claude_via_the_api_or_the') }} <span class="font-medium text-gray-700">{{ __('faq.the_api_is_the_single_source_of_truth') }}</span>{{ __('faq.there_are_no_local_status_files') }}
                    </p>
                </div>

                {{-- Ablauf als Statuskette --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex flex-wrap items-center gap-x-1 gap-y-2 text-sm">
                        <span class="text-gray-500">{{ __('faq.read_the_board') }}</span>
                        <span class="text-gray-300">→</span>
                        <span class="text-gray-500">{{ __('faq.choose_the_best_pick_2') }}</span>
                        <span class="text-gray-300">→</span>
                        {!! $badge(TaskStatus::CLAIMED) !!}
                        <span class="text-gray-300">→</span>
                        {!! $badge(TaskStatus::ANALYZING) !!}
                        <span class="text-gray-300">→</span>
                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-50 px-2 py-1 ring-1 ring-gray-100">
                            {!! $badge(TaskStatus::IN_PROGRESS) !!}<span class="text-gray-400">→ PR →</span>{!! $badge(TaskStatus::IN_REVIEW) !!}<span class="text-gray-400">→</span>{!! $badge(TaskStatus::MERGED) !!}
                        </span>
                        <span class="text-gray-300">|</span>
                        <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-1 ring-1 ring-red-100">
                            <span class="text-gray-400">{{ __('faq.or') }}</span>{!! $badge(TaskStatus::CONCERNED) !!}
                        </span>
                    </div>
                </div>

                {{-- Regeln --}}
                <ul class="divide-y divide-gray-50">
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.re_read_the_board_before_every_action') }}</span> {{ __('faq.the_state_changes_as_other_people_work') }}
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.choose_the_best_pick') }}</span> {{ __('faq.the_pickable_task_with_the_most_unlocks') }}
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.claim') }}{!! $badge(TaskStatus::CLAIMED) !!}{{ __('faq.is_atomic') }}</span> {{ __('faq.if_the_api_responds_with') }} <code class="rounded bg-gray-100 px-1 text-xs">409</code>{{ __('faq.the_task_is_already_taken_don_t') }} {!! $badge(TaskStatus::PICKABLE) !!}{{ __('common.text') }}
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.first_analyze') }}{!! $badge(TaskStatus::ANALYZING) !!}{{ __('faq.then_implement') }}{!! $badge(TaskStatus::IN_PROGRESS) !!}{{ __('common.text') }}</span> {{ __('faq.read_the_details_description_acceptance') }}
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.not_doable_report_a_concern') }}{!! $badge(TaskStatus::CONCERNED) !!}{{ __('faq.text') }}</span> {{ __('faq.instead_of_guessing_for_a_blocker_a') }} {!! $badge(TaskStatus::CLAIMED) !!} {{ __('common.or') }} {!! $badge(TaskStatus::PICKABLE) !!}.
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.adding_a_pr_does_not_change_the_status') }}</span> {{ __('faq.but_it_immediately_unlocks_dependent') }} <em>{{ __('faq.open') }}</em> {{ __('faq.pr_satisfies_their_gate_re_read_the') }}
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.report_done') }} {!! $badge(TaskStatus::IN_REVIEW) !!}{{ __('faq.text_2') }}</span> {{ __('faq.provided_a_pr_is_set_without_a_pr_it') }} {!! $badge(TaskStatus::IN_PROGRESS) !!}{{ __('faq.a_pr_alone_does_not_make_the_task_done') }}
                    </li>
                    <li class="px-6 py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ __('faq.only_the_merge_takes_the_task_off_the') }}{!! $badge(TaskStatus::MERGED) !!}{{ __('common.text') }}</span> {{ __('faq.the_merge_call_is_idempotent_the_merge') }} {!! $badge(TaskStatus::COMPLETED) !!} {{ __('faq.arises_only_via_a_split_parent_task') }}
                    </li>
                </ul>
            </div>

            {{-- 4) Abgeleiteter Anzeige-Status --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('faq.derived_display_status') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('faq.an_explicit_work_status') }}{!! $badge(TaskStatus::CLAIMED) !!}, {!! $badge(TaskStatus::ANALYZING) !!}, {!! $badge(TaskStatus::IN_PROGRESS) !!},
                        {!! $badge(TaskStatus::IN_REVIEW) !!}, {!! $badge(TaskStatus::CONCERNED) !!}, {!! $badge(TaskStatus::COMPLETED) !!}, {!! $badge(TaskStatus::MERGED) !!}{{ __('faq.is_shown_unchanged_only_for') }} {!! $badge(TaskStatus::UNKNOWN) !!} {{ __('faq.is_derived_for_display') }}
                    </p>
                </div>
                <ol class="divide-y divide-gray-50">
                    <li class="flex items-start gap-3 px-6 py-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">1</span>
                        <p class="text-sm text-gray-700">{{ __('faq.is_the_stored_status_an_explicit_work') }}</p>
                    </li>
                    <li class="flex items-start gap-3 px-6 py-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">2</span>
                        <p class="text-sm text-gray-700">{{ __('faq.otherwise_is_at_least_one_prerequisite') }} <span class="font-medium">{{ __('faq.not_delivered') }}</span>{{ __('faq.text_3') }} {!! $badge(TaskStatus::BLOCKED) !!}</p>
                    </li>
                    <li class="flex items-start gap-3 px-6 py-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">3</span>
                        <p class="text-sm text-gray-700">{{ __('faq.otherwise_is_the_task') }} <span class="font-medium">{{ __('faq.ready_to_start') }}</span> {{ __('faq.see_pickable_below') }} {!! $badge(TaskStatus::PICKABLE) !!}</p>
                    </li>
                    <li class="flex items-start gap-3 px-6 py-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">4</span>
                        <p class="text-sm text-gray-700">{{ __('faq.otherwise_it_stays_at') }} {!! $badge(TaskStatus::UNKNOWN) !!}.</p>
                    </li>
                </ol>
            </div>

            {{-- 5) Abhängigkeiten & Regeln --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900">{{ __('faq.delivered_pickable') }}</h3>
                    <div class="mt-3 space-y-3 text-sm text-gray-700">
                        <p><span class="font-medium">{{ __('faq.delivered') }}</span> {{ __('faq.a_task_is_as_soon_as_it_has_a_pr') }} <span class="text-gray-400">{{ __('faq.or') }}</span> {{ __('faq.is_done_merged') }}
                            <span class="text-gray-500">{{ __('faq.important_even_a_single') }} <em>{{ __('faq.open') }}</em> {{ __('faq.pr_counts_as_delivered_it_satisfies_the') }}</span></p>
                        <div>
                            <p class="font-medium">{{ __('faq.a_task_is') }} <span class="text-indigo-600">{{ __('common.pickable') }}</span>{{ __('faq.if') }} <span class="font-normal text-gray-500">{{ __('faq.all') }}</span> {{ __('faq.apply') }}</p>
                            <ul class="mt-1 list-disc space-y-1 ps-5 text-gray-600">
                                <li>{{ __('faq.not_claimed_no_assignee') }}</li>
                                <li>{{ __('faq.no_pr_set') }}</li>
                                <li>{{ __('faq.the_status_is_none_of_merged_done') }}</li>
                                <li>{{ __('faq.all_prerequisites_are_delivered') }}</li>
                            </ul>
                        </div>
                        <p><span class="font-medium">{{ __('common.blocked') }}</span> {{ __('faq.vs') }} <span class="font-medium">{{ __('common.pickable') }}</span>{{ __('faq.at_least_one_open_prerequisite_blocked') }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-900">{{ __('faq.bottleneck_in_the_diagram') }}</h3>
                    <div class="mt-3 space-y-3 text-sm text-gray-700">
                        <p>{{ __('faq.a_node_gets_the_bottleneck_marker_when') }} <span class="font-normal text-gray-500">{{ __('faq.all') }}</span> {{ __('faq.apply') }}</p>
                        <ul class="list-disc space-y-1 ps-5 text-gray-600">
                            <li>{{ __('faq.the_task_is') }} <span class="font-medium">{{ __('faq.not') }}</span> {{ __('faq.done_merged') }}</li>
                            <li>{{ __('faq.the_number_of') }} <span class="font-medium">{{ __('faq.in_total') }}</span> {{ __('faq.dependent_downstream_tasks_is_above_the') }}</li>
                            <li>{{ __('faq.and_is_at_least_2') }}</li>
                        </ul>
                        <p class="text-gray-500">{{ __('faq.in_addition_the_pr_sequence_counts_each') }}</p>
                    </div>
                </div>
            </div>

            {{-- 6) CI / PR-Sync --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-900">{{ __('faq.ci_pr_synchronization') }}</h3>
                <div class="mt-3 space-y-3 text-sm text-gray-700">
                    <p>{{ __('faq.the_sync_the_sync_prs_button_or_the') }}</p>
                    <ul class="list-disc space-y-1 ps-5 text-gray-600">
                        <li>{{ __('faq.for_each_pr_the_state_is_fetched_once') }}</li>
                        <li>{{ __('faq.as') }} <span class="font-medium">{{ __('faq.merged') }}</span> {{ __('faq.a_pr_only_counts_if_github') }} <code class="rounded bg-gray-100 px-1 text-xs">merged = true</code> {{ __('faq.or_a') }} <code class="rounded bg-gray-100 px-1 text-xs">merged_at</code>{{ __('faq.timestamp_closed_but_unmerged_prs_are') }}</li>
                        <li>{{ __('faq.on_merge_the_status_is_set_to') }} {!! $badge(TaskStatus::MERGED) !!} {{ __('faq.and_the_merge_timestamp_is_set') }}</li>
                        <li>{{ __('faq.pr_metrics_changed_files_commits') }} <span class="font-medium">{{ __('faq.not') }}</span>{{ __('faq.in_review_never_results_from_the_sync') }}</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
