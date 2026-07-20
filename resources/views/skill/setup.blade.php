<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ __('skill.planstack_skill_for_claude_code') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Intro + Download --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ __('skill.one_skill_for_all_your_projects') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    {{ __('skill.with_the_planstack_skill_claude_code') }}
                    <b>{{ __('skill.cross_project') }}</b> {{ __('skill.it_contains_no_fixed_project_you') }}
                </p>

                <div class="mt-5">
                    <a href="{{ route('skill.download') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                            <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                        </svg>
                        {{ __('skill.download_skill_zip') }}
                    </a>
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                        {{ __('skill.the_zip_contains') }} <span class="font-mono">SKILL.md</span> {{ __('skill.and_a_prefilled') }}
                        <span class="font-mono">config.json</span> {{ __('skill.with_a_freshly_generated_personal') }}
                    </p>
                </div>
            </div>

            {{-- Installation --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ __('skill.installation') }}</h3>
                <ol class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400" style="list-style: decimal inside;">
                    <li>{{ __('skill.download_and_unzip_the_zip') }}</li>
                    <li>{{ __('skill.the_folder') }} <span class="font-mono">planstack/</span> {{ __('skill.to') }}
                        <span class="font-mono">~/.claude/skills/</span> {{ __('skill.move') }}
                        (Windows: <span class="font-mono">%USERPROFILE%\.claude\skills\</span>).</li>
                    <li>{{ __('skill.done_in_claude_code_the_command') }} <span class="font-mono">/planstack</span> {{ __('skill.is_ready') }}</li>
                </ol>
            </div>

            {{-- Benutzung --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ __('skill.usage') }}</h3>
                <dl class="mt-3 space-y-3 text-sm">
                    <div>
                        <dt class="font-mono text-gray-800 dark:text-gray-100">/planstack &lt;PROJEKT&gt;</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ __('skill.works_through_this_project_s_entire') }}</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800 dark:text-gray-100">/planstack &lt;PROJEKT&gt; &lt;TASK&gt;</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ __('skill.works_through_a_single_specific_task') }} (<span class="font-mono">&lt;TASK&gt;</span> {{ __('skill.task_shortcode_e_g') }} <span class="font-mono">C27</span>).</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800 dark:text-gray-100">/planstack review [&lt;PROJEKT&gt;] [&lt;TASK&gt;]</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ __('skill.reviews_tasks_that_are_in_review_with_a') }}</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800 dark:text-gray-100">/planstack fix [&lt;PROJEKT&gt;] &lt;TASK|PR&gt;</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ __('skill.repairs_an_open_pr_resolves_merge') }}</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800 dark:text-gray-100">/planstack settings</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ __('skill.view_change_local_settings_tests') }}</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-gray-800 dark:text-gray-100">/planstack update-config [&lt;PROJEKT&gt;]</dt>
                        <dd class="text-gray-600 dark:text-gray-400">{{ __('skill.pulls_the_latest_general_and_optionally') }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                    <span class="font-mono">&lt;PROJEKT&gt;</span> {{ __('skill.is_the_project_alias_e_g') }} <span class="font-mono">L2L</span>, <span class="font-mono">LOG</span>).
                    {{ __('skill.the_skill_serves_every_project_your') }}
                </p>
            </div>

            {{-- Hinweise --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ __('skill.good_to_know') }}</h3>
                <ul class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400 list-disc ps-5">
                    <li><b>{{ __('skill.token') }}</b> {{ __('skill.on_download_a_personal_access_token_is') }} <a href="{{ route('profile.edit') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('common.profile_api_tokens') }}</a> {{ __('skill.revocable') }}</li>
                    <li><b>{{ __('skill.self_updating') }}</b> {{ __('skill.the_skill_automatically_picks_up') }}</li>
                    <li><b>{{ __('skill.no_fixed_project') }}</b> {{ __('skill.the') }} <span class="font-mono">config.json</span> {{ __('skill.contains_only_access_details_url_token') }}</li>
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
