<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The "Claude" sub-page of project editing: a web UI over the same board-protocol
 * config the API exposes at /api/projects/{project}/config. Saving bumps
 * config_version so the skill detects drift.
 */
class ProjectClaudeController extends Controller
{
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        $stored = is_array($project->config) ? $project->config : [];

        return view('projects.claude', [
            'project' => $project,
            'profile' => $stored['profile'] ?? \App\Support\ProjectConfig::DEFAULT_PROFILE,
            'overrides' => $stored['overrides'] ?? [],
            'effective' => $project->effectiveConfig(),
            'clientHints' => $project->clientHints(),
            'skillText' => $project->skill_description ?? '',
        ]);
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
