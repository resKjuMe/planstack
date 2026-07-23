<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ClaudeConfigPresenter;
use App\Support\ProjectConfig;
use App\Support\ProjectEditTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The "Claude" sub-page of project editing: a web UI over the same board-protocol
 * config the API exposes at /api/projects/{project}/config. Saving bumps
 * config_version so the skill detects drift.
 */
class ProjectClaudeController extends Controller
{
    public function edit(Project $project, ClaudeConfigPresenter $presenter): InertiaResponse
    {
        $this->authorize('update', $project);

        return Inertia::render('ProjectClaude', array_merge($presenter->props($project), [
            'editTabs' => ProjectEditTabs::for($project, 'claude'),
            'updateUrl' => route('projects.claude.update', $project),
            'cancelUrl' => route('projects.show', $project),
            'flash' => ['status' => session('status'), 'error' => session('error')],
            'strings' => [
                'editTitle' => __('projects.edit_project'),
                'configuration' => __('claude.claude_configuration'),
                'tokenSavingText' => __('claude.token_saving_switches_for_the_board'),
                'headerText' => __('claude.header'),
                'withoutExtraCall' => __('claude.without_an_extra_call'),
                'tokenLoadPerOption' => __('claude.token_load_per_option'),
                'low' => __('claude.low'),
                'medium' => __('claude.medium'),
                'high' => __('claude.high'),
                'neutral' => __('claude.neutral'),
                'profilePreset' => __('claude.profile_preset'),
                'showHideExplanation' => __('common.show_hide_explanation'),
                'presetIntro' => __('claude.a_preset_sets_the_base_values_of_all'),
                'estimatedTokenUsage' => __('claude.estimated_token_usage_compared_to_the'),
                'minimal10' => __('claude.minimal_1_0'),
                'maximal' => __('claude.maximal'),
                'roughEstimatePre' => __('claude.rough_estimate_of_the_board_task'),
                'executionModel' => __('claude.execution_model'),
                'and' => __('claude.and'),
                'contextBetweenTasks' => __('claude.context_between_tasks'),
                'defaultWord' => __('claude.default'),
                'tokenLoad' => __('claude.token_load'),
                'pro' => __('claude.pro'),
                'con' => __('claude.con'),
                'activeClientHints' => __('claude.active_client_hints'),
                'serverTransmits' => __('claude.the_server_transmits_these_deviations'),
                'noneBuiltIn' => __('claude.none_the_client_uses_its_built_in'),
                'skillLabel' => __('claude.skill_instructions_skill_md'),
                'skillHintPre' => __('claude.the_skill_receives_these_instructions'),
                'skillHintMid' => __('claude.and_are_reloaded_automatically_by_the'),
                'skillHintReplace' => __('claude.are_replaced_by_the_key_and_name'),
                'skillPlaceholder' => __('claude.skill_instructions_for_this_project'),
                'cancel' => __('common.cancel'),
                'save' => __('common.save'),
            ],
        ]));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'profile' => ['nullable', Rule::in(array_keys(ProjectConfig::PROFILES))],
            'overrides' => ['nullable', 'array'],
            'skill_description' => ['nullable', 'string'],
        ]);

        // Only explicit (non-empty) selections become overrides; "" means
        // "use the profile/default value" and is dropped. Keep '0'/'false'.
        $raw = array_filter(
            (array) $request->input('overrides', []),
            fn ($v) => $v !== '' && $v !== null,
        );

        $config = [
            'profile' => $request->input('profile') ?: null,
            'overrides' => ProjectConfig::validateOverrides($raw),
        ];

        $update = [
            'config' => $config,
            'config_version' => $project->config_version + 1,
        ];

        // Skill-Anweisungen werden hier mit-versioniert, damit der Skill sie bei
        // Drift nachladen kann (siehe /config → instructions).
        if ($request->has('skill_description')) {
            $update['skill_description'] = $validated['skill_description'] ?: null;
        }

        $project->update($update);

        return redirect()
            ->route('projects.claude.edit', $project)
            ->with('status', "Claude-Konfiguration gespeichert (v{$project->config_version}).");
    }
}
