<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Projekt bearbeiten – <span class="font-mono">{{ $project->alias }}</span>
        </h2>
    </x-slot>

    <x-slot name="subheader">
        <x-project-edit-tabs :project="$project" active="claude" />
    </x-slot>

    @php
        // Token-Last je Option: g = niedrig, y = mittel, r = hoch, n = neutral.
        $badge = fn ($t) => ['g' => '🟢', 'y' => '🟡', 'r' => '🔴'][$t] ?? '⚪';
        $badgeWord = fn ($t) => ['g' => __('claude.low'), 'y' => __('claude.medium'), 'r' => __('claude.high')][$t] ?? __('claude.neutral');
        $boolKey = fn ($v) => ($v === true || $v === '1' || $v === 1) ? '1' : '0';

        // Gruppierte Einstellungen: Label, Kurzhilfe, ausführliche Beschreibung und
        // je Option Token-Last (token) + Pro/Contra (pro/con) für das Hilfe-Icon.
        $groups = [
            'claude.board_output' => [
                ['key' => 'board.scope', 'type' => 'enum', 'label' => 'claude.scope',
                 'desc' => 'claude.controls_how_many_tasks_the_board',
                 'options' => [
                    'next_only' => ['label' => 'claude.best_pick_only', 'token' => 'g',
                        'pro' => 'claude.minimal_context_one_clear_focus_per_run',
                        'con' => 'claude.no_overview_of_alternatives_a_new_fetch'],
                    'pickable' => ['label' => 'claude.pickable_list', 'token' => 'y',
                        'pro' => 'claude.all_currently_startable_tasks_visible',
                        'con' => 'claude.larger_response_than_a_single_pick'],
                    'all' => ['label' => 'claude.all_tasks', 'token' => 'r',
                        'pro' => 'claude.complete_overview_including_blocked',
                        'con' => 'claude.most_expensive_response_lots_of_ballast'],
                 ]],
                ['key' => 'board.format', 'type' => 'enum', 'label' => 'claude.format',
                 'desc' => 'claude.determines_the_board_s_response_format',
                 'options' => [
                    'terse' => ['label' => 'claude.text_terse', 'token' => 'g',
                        'pro' => 'claude.smallest_response_one_line_per_task',
                        'con' => 'claude.only_core_info_no_structured_json'],
                    'lean' => ['label' => 'claude.compact_json', 'token' => 'y',
                        'pro' => 'claude.machine_readable_with_short_keys',
                        'con' => 'claude.slightly_larger_than_plain_text'],
                    'full' => ['label' => 'claude.full_json', 'token' => 'r',
                        'pro' => 'claude.all_computed_fields_no_second_query',
                        'con' => 'claude.largest_response'],
                 ]],
                ['key' => 'board.aggregates', 'type' => 'bool', 'label' => 'claude.aggregates',
                 'desc' => 'claude.include_progress_totals_and_phase',
                 'options' => [
                    '0' => ['label' => 'claude.off', 'token' => 'g',
                        'pro' => 'claude.saves_totals_phase_ballast',
                        'con' => 'claude.no_progress_overview_in_the_response'],
                    '1' => ['label' => 'claude.on', 'token' => 'y',
                        'pro' => 'claude.progress_and_phases_at_a_glance',
                        'con' => 'claude.additional_tokens_per_fetch'],
                 ]],
                ['key' => 'board.diff_mode', 'type' => 'enum', 'label' => 'claude.diff_mode',
                 'desc' => 'claude.whether_an_unchanged_board_is_answered',
                 'options' => [
                    'etag' => ['label' => 'claude.etag_304', 'token' => 'g',
                        'pro' => 'claude.unchanged_board_304_nothing_new_in_the',
                        'con' => 'claude.the_client_must_send_the_etag_along'],
                    'off' => ['label' => 'claude.always_full', 'token' => 'r',
                        'pro' => 'claude.always_complete_data_simple',
                        'con' => 'claude.repeats_the_same_content_on_every_fetch'],
                 ]],
            ],
            'claude.task_details' => [
                ['key' => 'task.fields', 'type' => 'enum', 'label' => 'claude.field_scope',
                 'desc' => 'claude.which_fields_a_task_object_carries',
                 'options' => [
                    'minimal' => ['label' => 'claude.minimal', 'token' => 'g',
                        'pro' => 'claude.only_the_essentials_for_picking_working',
                        'con' => 'claude.missing_details_may_need_to_be_reloaded'],
                    'standard' => ['label' => 'claude.standard', 'token' => 'y',
                        'pro' => 'claude.good_middle_ground_incl_phase_effort_pr',
                        'con' => 'claude.more_than_minimal'],
                    'full' => ['label' => 'claude.full', 'token' => 'r',
                        'pro' => 'claude.everything_incl_timestamps_and_history',
                        'con' => 'claude.largest_task_objects'],
                 ]],
                ['key' => 'claim.return_details', 'type' => 'bool', 'label' => 'claude.details_on_actions',
                 'desc' => 'claude.whether_write_actions_return_the_full',
                 'options' => [
                    '0' => ['label' => 'claude.short_ack', 'token' => 'g',
                        'pro' => 'claude.tiny_write_responses_id_name_status',
                        'con' => 'claude.for_details_the_board_must_be_re_read'],
                    '1' => ['label' => 'claude.full_task', 'token' => 'y',
                        'pro' => 'claude.all_data_directly_after_the_action',
                        'con' => 'claude.every_action_carries_back_the_full_task'],
                 ]],
            ],
            'claude.roundtrips_actions' => [
                ['key' => 'actions.bundling', 'type' => 'bool', 'label' => 'claude.bundle_actions',
                 'desc' => 'claude.set_pr_report_done_optionally_merge_in',
                 'options' => [
                    '1' => ['label' => 'claude.bundled', 'token' => 'g',
                        'pro' => 'claude.one_call_instead_of_three_fewer',
                        'con' => 'claude.less_granular_intermediate_steps'],
                    '0' => ['label' => 'claude.individual', 'token' => 'r',
                        'pro' => 'claude.fine_grained_control_per_step',
                        'con' => 'claude.more_calls_more_context'],
                 ]],
                ['key' => 'response.errors', 'type' => 'enum', 'label' => 'claude.error_output',
                 'desc' => 'claude.verbosity_of_error_responses',
                 'options' => [
                    'minimal' => ['label' => 'claude.code_only', 'token' => 'g',
                        'pro' => 'claude.smallest_error_response_the_http_status',
                        'con' => 'claude.no_plain_text_reasoning'],
                    'standard' => ['label' => 'claude.code_message', 'token' => 'y',
                        'pro' => 'claude.short_understandable_message',
                        'con' => 'claude.slightly_more_text'],
                    'verbose' => ['label' => 'claude.verbose', 'token' => 'r',
                        'pro' => 'claude.full_error_details_for_debugging',
                        'con' => 'claude.the_most_text'],
                 ]],
                ['key' => 'reread.policy', 'type' => 'enum', 'label' => 'claude.board_re_reading',
                 'desc' => 'claude.when_the_client_re_reads_the_board',
                 'options' => [
                    'on_conflict' => ['label' => 'claude.only_on_conflict', 'token' => 'g',
                        'pro' => 'claude.minimal_board_fetches_relies_on_409',
                        'con' => 'claude.states_may_be_briefly_outdated'],
                    'once_per_pick' => ['label' => 'claude.once_per_pick', 'token' => 'y',
                        'pro' => 'claude.current_state_per_task',
                        'con' => 'claude.one_additional_fetch_per_task'],
                    'before_every_action' => ['label' => 'claude.before_every_action', 'token' => 'r',
                        'pro' => 'claude.always_fully_up_to_date',
                        'con' => 'claude.multiplies_the_board_fetches_expensive'],
                 ]],
            ],
            'claude.instructions_conventions' => [
                ['key' => 'instructions.delivery', 'type' => 'enum', 'label' => 'claude.logic_delivery',
                 'desc' => 'claude.how_status_logic_and_rules_reach_the',
                 'options' => [
                    'server_enforced' => ['label' => 'claude.server_enforced', 'token' => 'g',
                        'pro' => 'claude.rules_reside_on_the_server_not_in_the',
                        'con' => 'claude.the_client_doesn_t_know_the_rule'],
                    'changelog' => ['label' => 'claude.changelog_delta', 'token' => 'y',
                        'pro' => 'claude.on_a_version_bump_only_the_change',
                        'con' => 'claude.the_client_already_needs_the_base'],
                    'full_doc' => ['label' => 'claude.full_document', 'token' => 'r',
                        'pro' => 'claude.all_rules_directly_available',
                        'con' => 'claude.large_context_consumption'],
                 ]],
                ['key' => 'conventions.delivery', 'type' => 'enum', 'label' => 'claude.conventions',
                 'desc' => 'claude.how_coding_standards_pr_template_are',
                 'options' => [
                    'server_enforced' => ['label' => 'claude.server_enforced', 'token' => 'g',
                        'pro' => 'claude.ci_lint_enforces_standards_no_context',
                        'con' => 'claude.feedback_only_after_the_run'],
                    'snippet' => ['label' => 'claude.snippet', 'token' => 'y',
                        'pro' => 'claude.only_the_part_relevant_to_the_task',
                        'con' => 'claude.peripheral_knowledge_may_be_missing'],
                    'full_prose' => ['label' => 'claude.full_text', 'token' => 'r',
                        'pro' => 'claude.all_conventions_present',
                        'con' => 'claude.lots_of_prose_per_task'],
                 ]],
            ],
            'claude.execution_client_hint' => [
                ['key' => 'execution.mode', 'type' => 'enum', 'label' => 'claude.execution_model',
                 'desc' => 'claude.how_the_client_processes_tasks_the',
                 'options' => [
                    'headless' => ['label' => 'claude.headless_loop', 'token' => 'g',
                        'pro' => 'claude.fresh_process_per_task_zero_legacy',
                        'con' => 'claude.no_shared_context_across_tasks'],
                    'subagent' => ['label' => 'claude.subagent_per_task', 'token' => 'g',
                        'pro' => 'claude.isolated_small_context_the_orchestrator',
                        'con' => 'claude.some_overhead_per_subagent'],
                    'single_session' => ['label' => 'claude.single_session', 'token' => 'r',
                        'pro' => 'claude.continuous_context_simplest_flow',
                        'con' => 'claude.history_grows_strongly_the_most'],
                 ]],
                ['key' => 'context.between_tasks', 'type' => 'enum', 'label' => 'claude.context_between_tasks',
                 'desc' => 'claude.stop_after_each_task_context_clearable',
                 'options' => [
                    'stop' => ['label' => 'claude.stop_after_task', 'token' => 'g',
                        'pro' => 'claude.context_clearable_clear_stays',
                        'con' => 'claude.restart_needed_per_task'],
                    'continue' => ['label' => 'claude.run_continuously', 'token' => 'r',
                        'pro' => 'claude.without_interruption',
                        'con' => 'claude.growing_history'],
                 ]],
                ['key' => 'parallelism.max_workers', 'type' => 'int', 'label' => 'claude.max_workers',
                 'desc' => 'claude.number_of_parallel_workers_1_32_affects',
                 'options' => []],
            ],
            'claude.working_style_client_hint' => [
                ['key' => 'concerns.attitude', 'type' => 'enum', 'label' => 'claude.handling_of_concerns',
                 'desc' => 'claude.how_readily_the_worker_reports_a',
                 'options' => [
                    'kritisch' => ['label' => 'claude.critical', 'token' => 'n',
                        'pro' => 'claude.highest_safety_ambiguities_and_risks',
                        'con' => 'claude.more_questions_concerns_and'],
                    'ausgewogen' => ['label' => 'claude.balanced', 'token' => 'n',
                        'pro' => 'claude.good_middle_ground_concern_only_on_real',
                        'con' => 'claude.occasionally_an_unnecessary_or_a'],
                    'mutig' => ['label' => 'claude.bold', 'token' => 'n',
                        'pro' => 'claude.fastest_throughput_the_worker',
                        'con' => 'claude.higher_risk_of_wrong_assumptions_and'],
                 ]],
            ],
            'claude.claude_execution_client_hint' => [
                ['key' => 'run.mode', 'type' => 'enum', 'label' => 'claude.permission_mode',
                 'desc' => 'claude.which_mode_claude_runs_in_client',
                 'options' => [
                    'client' => ['label' => 'claude.client_default', 'token' => 'n',
                        'pro' => 'claude.adopts_the_local_setting_no_project',
                        'con' => 'claude.behavior_depends_on_the_respective'],
                    'manual' => ['label' => 'claude.manual', 'token' => 'n',
                        'pro' => 'claude.maximum_control_every_action_is',
                        'con' => 'claude.many_prompts_slower'],
                    'accept_edits' => ['label' => 'claude.auto_accept_edits', 'token' => 'n',
                        'pro' => 'claude.file_changes_without_prompting_faster',
                        'con' => 'claude.less_control_over_individual_edits'],
                    'plan' => ['label' => 'claude.plan_mode', 'token' => 'n',
                        'pro' => 'claude.plan_first_then_implementation_good_for',
                        'con' => 'claude.additional_step_before_the_work'],
                    'auto' => ['label' => 'claude.auto', 'token' => 'n',
                        'pro' => 'claude.works_through_autonomously_no',
                        'con' => 'claude.little_control_only_with_trust'],
                 ]],
                ['key' => 'run.model', 'type' => 'enum', 'label' => 'claude.model',
                 'desc' => 'claude.which_model_claude_works_with_client',
                 'options' => [
                    'client' => ['label' => 'claude.client_default', 'token' => 'n',
                        'pro' => 'claude.uses_the_locally_selected_model',
                        'con' => 'claude.no_project_wide_default'],
                    'opus' => ['label' => 'claude.opus', 'token' => 'n',
                        'pro' => 'claude.strongest_quality_for_difficult_tasks',
                        'con' => 'claude.highest_cost'],
                    'sonnet' => ['label' => 'claude.sonnet', 'token' => 'n',
                        'pro' => 'claude.balanced_between_quality_and_cost',
                        'con' => 'claude.weaker_than_opus_on_very_hard_tasks'],
                    'haiku' => ['label' => 'claude.haiku', 'token' => 'n',
                        'pro' => 'claude.fast_and_cheap',
                        'con' => 'claude.lower_quality_on_complex_tasks'],
                    'fable' => ['label' => 'claude.fable', 'token' => 'n',
                        'pro' => 'claude.model_of_the_claude_5_family',
                        'con' => 'claude.weigh_suitability_depending_on_the_task'],
                 ]],
                ['key' => 'run.effort', 'type' => 'enum', 'label' => 'claude.reasoning_effort',
                 'desc' => 'claude.how_much_thinking_effort_claude_invests',
                 'options' => [
                    'client' => ['label' => 'claude.client_default', 'token' => 'n',
                        'pro' => 'claude.uses_the_local_default',
                        'con' => 'claude.no_project_wide_default'],
                    'low' => ['label' => 'claude.low', 'token' => 'n',
                        'pro' => 'claude.fast_and_cheap',
                        'con' => 'claude.less_thorough'],
                    'medium' => ['label' => 'claude.medium', 'token' => 'n',
                        'pro' => 'claude.balanced_default_compromise',
                        'con' => 'claude.text'],
                    'high' => ['label' => 'claude.high', 'token' => 'n',
                        'pro' => 'claude.more_thorough_on_difficult_tasks',
                        'con' => 'claude.more_tokens_and_time'],
                    'xhigh' => ['label' => 'claude.very_high', 'token' => 'n',
                        'pro' => 'claude.very_thorough',
                        'con' => 'claude.considerably_more_tokens_and_time'],
                    'max' => ['label' => 'claude.maximal_2', 'token' => 'n',
                        'pro' => 'claude.maximum_thoroughness',
                        'con' => 'claude.highest_token_and_time_cost'],
                 ]],
            ],
        ];

        // Daten für die Live-Aktualisierung beim Preset-Wechsel (Alpine).
        $jsMeta = [];
        foreach ($groups as $settings) {
            foreach ($settings as $s) {
                $opts = [];
                foreach ($s['options'] as $val => $opt) {
                    $opts[(string) $val] = ['label' => __($opt['label']), 'token' => $opt['token']];
                }
                $jsMeta[$s['key']] = ['type' => $s['type'], 'options' => $opts];
            }
        }
        // Initiale (explizite) Auswahl je Feld: '' = Profil-Standard.
        $initialValues = [];
        foreach ($jsMeta as $mKey => $m) {
            if (! array_key_exists($mKey, $overrides)) {
                $initialValues[$mKey] = '';
            } elseif ($m['type'] === 'bool') {
                $initialValues[$mKey] = $boolKey($overrides[$mKey]);
            } else {
                $initialValues[$mKey] = (string) $overrides[$mKey];
            }
        }

        // Grobe relative Token-Gewichte je Option (nur für die Schätzung). Der
        // größte Hebel ist das Ausführungsmodell/der Kontext zwischen Tasks.
        $tokenCost = [
            'board.scope' => ['next_only' => 1, 'pickable' => 4, 'all' => 12],
            'board.format' => ['terse' => 1, 'lean' => 3, 'full' => 6],
            'board.aggregates' => ['0' => 0, '1' => 3],
            'board.diff_mode' => ['etag' => 0, 'off' => 4],
            'task.fields' => ['minimal' => 1, 'standard' => 3, 'full' => 6],
            'claim.return_details' => ['0' => 0, '1' => 3],
            'actions.bundling' => ['1' => 0, '0' => 3],
            'response.errors' => ['minimal' => 0, 'standard' => 1, 'verbose' => 3],
            'reread.policy' => ['on_conflict' => 1, 'once_per_pick' => 4, 'before_every_action' => 10],
            'instructions.delivery' => ['server_enforced' => 0, 'changelog' => 4, 'full_doc' => 20],
            'conventions.delivery' => ['server_enforced' => 0, 'snippet' => 5, 'full_prose' => 25],
            'execution.mode' => ['headless' => 0, 'subagent' => 5, 'single_session' => 120],
            'context.between_tasks' => ['stop' => 0, 'continue' => 40],
        ];

        $alpineInit = [
            'profile' => $profile ?: '',
            'presets' => \App\Support\ProjectConfig::PROFILES,
            'defaults' => \App\Support\ProjectConfig::DEFAULTS,
            'meta' => $jsMeta,
            'values' => $initialValues,
            'costs' => $tokenCost,
            'hintKeys' => array_values(\App\Support\ProjectConfig::CLIENT_HINT_KEYS),
        ];

        // Farbiger Token-Punkt (Tailwind) je Token-Stufe.
        $dot = fn ($t) => ['g' => 'bg-green-500', 'y' => 'bg-amber-500', 'r' => 'bg-red-500'][$t] ?? 'bg-gray-400';

        // Kurzbeschreibung je Gruppe (unter dem Kartentitel).
        $groupDescriptions = [
            'claude.board_output' => 'Wie schlank die Board-Antwort ausfällt – Umfang, Format und Caching.',
            'claude.task_details' => 'Wie viele Felder ein einzelner Task und die Antworten von Schreib-Aktionen tragen.',
            'claude.roundtrips_actions' => 'Aufrufe bündeln und Antworten knapp halten, um Roundtrips zu sparen.',
            'claude.instructions_conventions' => 'Wie Regeln und Standards zum Client gelangen – möglichst ohne Kontext-Ballast.',
            'claude.execution_client_hint' => 'Wie der Client Tasks abarbeitet – der größte Token-Hebel.',
            'claude.working_style_client_hint' => 'Wie der Worker mit Unklarheiten und Entscheidungen umgeht.',
            'claude.claude_execution_client_hint' => 'Modus, Modell und Reasoning-Aufwand, mit denen Claude läuft (Client-Standard = lokale Einstellung übernehmen).',
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white rounded-lg shadow p-6 space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ __('claude.claude_configuration') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ __('claude.token_saving_switches_for_the_board') }} <span class="font-mono">v{{ $project->config_version }}</span>
                            {{ __('claude.header') }} <span class="font-mono">X-Planstack-Config-Version</span>{{ __('claude.without_an_extra_call') }}
                        </p>
                    </div>
                    <span class="shrink-0 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-mono text-gray-600">v{{ $project->config_version }}</span>
                </div>
                <p class="text-xs text-gray-400">
                    {{ __('claude.token_load_per_option') }} {{ $badge('g') }} {{ __('claude.low') }} · {{ $badge('y') }} {{ __('claude.medium') }} · {{ $badge('r') }} {{ __('claude.high') }}.
                </p>
            </div>

            <form method="POST" action="{{ route('projects.claude.update', $project) }}" class="space-y-6"
                  x-data="claudeConfig(@js($alpineInit))">
                @csrf
                @method('PUT')

                @php
                    $profilePills = [
                        'recommended' => ['label' => 'claude.claude_recommended', 'dot' => 'bg-indigo-500',
                            'desc' => 'claude.claude_s_recommendation_and_the_default',
                            'pro' => 'claude.roughly_an_order_of_magnitude_fewer',
                            'con' => 'claude.not_quite_as_economical_as_economy_the'],
                        'economy' => ['label' => 'claude.economy', 'dot' => 'bg-green-500',
                            'desc' => 'claude.the_most_economical_package_every',
                            'pro' => 'claude.minimal_token_usage_per_iteration_ideal',
                            'con' => 'claude.responses_contain_only_the_essentials'],
                        'balanced' => ['label' => 'claude.balanced_2', 'dot' => 'bg-amber-500',
                            'desc' => 'claude.the_middle_ground_compact_json_with_the',
                            'pro' => 'claude.good_compromise_between_economy_and',
                            'con' => 'claude.noticeably_more_tokens_than_recommended'],
                        'rich' => ['label' => 'claude.rich', 'dot' => 'bg-red-500',
                            'desc' => 'claude.the_most_detailed_variant_and_at_the',
                            'pro' => 'claude.maximum_transparency_everything_is',
                            'con' => 'claude.highest_token_usage_the_continuous'],
                    ];
                @endphp
                <div class="bg-white rounded-lg shadow p-6" x-data="{ help: false }">
                    <input type="hidden" name="profile" :value="profile" />
                    <div class="flex items-center gap-4">
                        <label class="w-52 shrink-0 text-sm font-medium text-gray-700">{{ __('claude.profile_preset') }}</label>
                        <div class="flex-1 flex flex-wrap items-center justify-end gap-2">
                            @foreach ($profilePills as $val => $p)
                                <button type="button" @click="profile = '{{ $val }}'"
                                        :class="profile === '{{ $val }}'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                    <span class="h-2 w-2 rounded-full {{ $p['dot'] }}"></span>
                                    {{ __($p['label']) }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button" @click="help = ! help" :aria-expanded="help"
                                aria-label="{{ __('claude.explanation_of_the_presets') }}" title="{{ __('common.show_hide_explanation') }}"
                                class="shrink-0 text-gray-400 hover:text-indigo-600 focus:outline-none">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                        </button>
                    </div>

                    <div x-show="help" style="display:none"
                         class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-600">
                        <p>{{ __('claude.a_preset_sets_the_base_values_of_all') }}</p>
                        <ul class="mt-3 space-y-2.5">
                            @foreach ($profilePills as $val => $p)
                                <li>
                                    <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                        <span class="h-2 w-2 rounded-full {{ $p['dot'] }}"></span>
                                        {{ __($p['label']) }}
                                    </div>
                                    <div class="ms-3.5">{{ __($p['desc']) }}</div>
                                    <div class="ms-3.5"><span class="font-medium text-green-700">{{ __('claude.pro') }}</span> {{ __($p['pro']) }}</div>
                                    <div class="ms-3.5"><span class="font-medium text-rose-700">{{ __('claude.con') }}</span> {{ __($p['con']) }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <x-input-error :messages="$errors->get('profile')" class="mt-2" />
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-baseline justify-between gap-4">
                        <h4 class="font-semibold text-gray-700">{{ __('claude.estimated_token_usage_compared_to_the') }}</h4>
                        <span class="shrink-0 text-lg font-bold text-gray-800" x-text="'× ' + tokenRatio().toFixed(1)"></span>
                    </div>
                    <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full transition-all duration-300"
                             :class="tokenBarClass()" :style="'width: ' + Math.max(2, tokenPct()) + '%'"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                        <span>{{ __('claude.minimal_1_0') }}</span>
                        <span>{{ __('claude.maximal') }}</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        {{ __('claude.rough_estimate_of_the_board_task') }} <span class="font-medium">{{ __('claude.execution_model') }}</span> {{ __('claude.and') }} <span class="font-medium">{{ __('claude.context_between_tasks') }}</span>.
                    </p>
                </div>

                @foreach ($groups as $groupTitle => $settings)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h4 class="font-semibold text-gray-700">{{ __($groupTitle) }}</h4>
                        <p class="text-sm text-gray-500 mt-0.5 mb-2">{{ isset($groupDescriptions[$groupTitle]) ? __($groupDescriptions[$groupTitle]) : '' }}</p>
                        <div class="divide-y divide-gray-100">
                            @foreach ($settings as $s)
                                @php $key = $s['key']; @endphp
                                <div class="py-3" x-data="{ help: false }">
                                    <div class="flex items-center gap-4">
                                        <label for="f-{{ $key }}" class="w-52 shrink-0 text-sm font-medium text-gray-700">{{ __($s['label']) }}</label>

                                        <div class="flex-1 flex flex-wrap items-center justify-end gap-3">
                                            @if ($s['type'] === 'int')
                                                <input type="hidden" name="overrides[{{ $key }}]" :value="values['{{ $key }}']" />
                                                {{-- Standard zurücksetzen --}}
                                                <button type="button" @click="select('{{ $key }}', '')"
                                                        :class="isSelected('{{ $key }}', '')
                                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                                            : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'"
                                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                    <span x-text="@js(__('claude.default')) + ' ' + defaultOptLabel('{{ $key }}')"></span>
                                                </button>
                                                <input id="f-{{ $key }}" type="range" min="1" max="32" step="1"
                                                       :value="values['{{ $key }}'] !== '' ? values['{{ $key }}'] : defaultVal('{{ $key }}')"
                                                       @input="values['{{ $key }}'] = $event.target.value"
                                                       class="w-48 accent-indigo-600" />
                                                <span class="w-7 text-right text-sm font-semibold text-gray-800"
                                                      x-text="values['{{ $key }}'] !== '' ? values['{{ $key }}'] : defaultVal('{{ $key }}')"></span>
                                            @else
                                                <input type="hidden" name="overrides[{{ $key }}]" :value="values['{{ $key }}']" />
                                                {{-- Profil-Standard --}}
                                                <button type="button" @click="select('{{ $key }}', '')"
                                                        :class="isSelected('{{ $key }}', '')
                                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                                            : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'"
                                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                    <span class="h-2 w-2 rounded-full" :class="dotClass(defaultToken('{{ $key }}'))"></span>
                                                    <span x-text="@js(__('claude.default')) + ' ' + defaultOptLabel('{{ $key }}')"></span>
                                                </button>
                                                {{-- Explizite Optionen --}}
                                                @foreach ($s['options'] as $val => $opt)
                                                    <button type="button" @click="select('{{ $key }}', '{{ $val }}')"
                                                            :class="isSelected('{{ $key }}', '{{ $val }}')
                                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                                                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium">
                                                        <span class="h-2 w-2 rounded-full {{ $dot($opt['token']) }}"></span>
                                                        {{ __($opt['label']) }}
                                                    </button>
                                                @endforeach
                                            @endif
                                        </div>

                                        <button type="button" @click="help = ! help" :aria-expanded="help"
                                                aria-label="{{ __('claude.explanation_of_label', ['label' => __($s['label'])]) }}" title="{{ __('common.show_hide_explanation') }}"
                                                class="shrink-0 text-gray-400 hover:text-indigo-600 focus:outline-none">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                                        </button>
                                    </div>

                                    <div x-show="help" style="display:none"
                                         class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-600">
                                        <p>{{ __($s['desc']) }}</p>
                                        @if (! empty($s['options']))
                                            <ul class="mt-3 space-y-2.5">
                                                @foreach ($s['options'] as $val => $opt)
                                                    <li>
                                                        <div class="flex items-center gap-1.5 font-medium text-gray-800">
                                                            <span class="h-2 w-2 rounded-full {{ $dot($opt['token']) }}"></span>
                                                            {{ __($opt['label']) }}
                                                            <span class="font-normal text-gray-400">· {{ __('claude.token_load') }} {{ $badgeWord($opt['token']) }}</span>
                                                        </div>
                                                        <div class="ms-3.5"><span class="font-medium text-green-700">{{ __('claude.pro') }}</span> {{ __($opt['pro']) }}</div>
                                                        <div class="ms-3.5"><span class="font-medium text-rose-700">{{ __('claude.con') }}</span> {{ __($opt['con']) }}</div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>

                                    <x-input-error :messages="$errors->get('overrides.'.$key)" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-700 mb-2">{{ __('claude.active_client_hints') }}</h4>
                    <template x-if="liveHints().length">
                        <div>
                            <p class="text-sm text-gray-500 mb-2">{{ __('claude.the_server_transmits_these_deviations') }}</p>
                            <ul class="text-sm font-mono text-gray-600 space-y-1">
                                <template x-for="h in liveHints()" :key="h.key">
                                    <li x-text="h.key + ' = ' + h.value"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <template x-if="! liveHints().length">
                        <p class="text-sm text-gray-500">{{ __('claude.none_the_client_uses_its_built_in') }}</p>
                    </template>

                    <div class="mt-5 border-t border-gray-100 pt-5">
                        <x-input-label for="skill_description" :value="__('claude.skill_instructions_skill_md')" />
                        <p class="mt-1 mb-2 text-xs text-gray-400">
                            {{ __('claude.the_skill_receives_these_instructions') }} <span class="font-mono">v{{ $project->config_version }}</span>{{ __('claude.and_are_reloaded_automatically_by_the') }} <span class="font-mono">/config → instructions</span>{{ __('common.text') }}
                            <span class="font-mono">@{{alias}}</span> {{ __('claude.and') }} <span class="font-mono">@{{name}}</span> {{ __('claude.are_replaced_by_the_key_and_name') }}
                        </p>
                        <textarea id="skill_description" name="skill_description" rows="14" spellcheck="false"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"
                                  :placeholder='__('claude.skill_instructions_for_this_project')'>{{ old('skill_description', $skillText) }}</textarea>
                        <x-input-error :messages="$errors->get('skill_description')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</a>
                    <x-primary-button>{{ __('common.save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Segmentierte Auswahl: hält die explizite Wahl je Feld ('' = Profil-Standard),
        // berechnet die live-aktualisierten Standardwerte beim Preset-Wechsel und den
        // geschätzten Tokenverbrauch relativ zur Minimalconfig.
        window.claudeConfig = function (init) {
            const dots = { g: 'bg-green-500', y: 'bg-amber-500', r: 'bg-red-500' };
            return {
                profile: init.profile,
                presets: init.presets,
                defaults: init.defaults,
                meta: init.meta,
                values: init.values,
                costs: init.costs,
                hintKeys: init.hintKeys,
                select(key, val) { this.values[key] = val; },
                isSelected(key, val) { return (this.values[key] ?? '') === val; },
                // Normalisierter Shipped-Default (ohne Profil) zum Vergleich.
                shippedNorm(key) {
                    const m = this.meta[key];
                    const v = this.defaults[key];
                    if (m && m.type === 'bool') return (v === true || v === '1' || v === 1) ? '1' : '0';
                    return String(v);
                },
                // Client-Hinweise = effektive Werte, die vom Shipped-Default abweichen.
                liveHints() {
                    const out = [];
                    for (const key of this.hintKeys) {
                        const eff = this.effNorm(key);
                        if (eff !== this.shippedNorm(key)) {
                            const m = this.meta[key];
                            const value = (m && m.type === 'bool') ? (eff === '1' ? 'true' : 'false') : eff;
                            out.push({ key, value });
                        }
                    }
                    return out;
                },
                // Effektiver (normalisierter) Wert eines Feldes: explizite Wahl,
                // sonst der Profil-/Default-Wert.
                effNorm(key) {
                    const v = this.values[key] ?? '';
                    return v !== '' ? String(v) : this.defaultNorm(key);
                },
                tokenIndex() {
                    let s = 0;
                    for (const k in this.costs) { s += (this.costs[k][this.effNorm(k)] ?? 0); }
                    return s;
                },
                tokenBounds() {
                    let mn = 0, mx = 0;
                    for (const k in this.costs) {
                        const vals = Object.values(this.costs[k]);
                        mn += Math.min(...vals); mx += Math.max(...vals);
                    }
                    return { mn, mx };
                },
                tokenRatio() {
                    const { mn } = this.tokenBounds();
                    return mn > 0 ? this.tokenIndex() / mn : 1;
                },
                tokenPct() {
                    const { mn, mx } = this.tokenBounds();
                    return mx > mn ? Math.round((this.tokenIndex() - mn) / (mx - mn) * 100) : 0;
                },
                tokenBarClass() {
                    const p = this.tokenPct();
                    return p < 20 ? 'bg-green-500' : (p < 55 ? 'bg-amber-500' : 'bg-red-500');
                },
                dotClass(token) { return dots[token] || 'bg-gray-400'; },
                defaultVal(key) {
                    const preset = this.presets[this.profile] || {};
                    return (preset[key] !== undefined) ? preset[key] : this.defaults[key];
                },
                defaultNorm(key) {
                    const m = this.meta[key];
                    const v = this.defaultVal(key);
                    if (!m || m.type === 'int') return String(v);
                    return m.type === 'bool'
                        ? ((v === true || v === '1' || v === 1) ? '1' : '0')
                        : String(v);
                },
                defaultOptLabel(key) {
                    const m = this.meta[key];
                    if (!m || m.type === 'int') return String(this.defaultVal(key));
                    const opt = m.options[this.defaultNorm(key)] || {};
                    return opt.label || String(this.defaultVal(key));
                },
                defaultToken(key) {
                    const m = this.meta[key];
                    if (!m || m.type === 'int') return 'n';
                    const opt = m.options[this.defaultNorm(key)] || {};
                    return opt.token || 'n';
                },
            };
        };
    </script>
</x-app-layout>
