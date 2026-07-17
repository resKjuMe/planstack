<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Support\ProjectConfig;
use App\Support\SkillTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-project board-protocol configuration (token-saving knobs).
 *
 * The client only needs `board`/task calls to work — those carry the version
 * header and, on drift, the hint delta. This endpoint is for *inspecting and
 * editing* the config (a settings UI or a one-off `curl`), not the hot path.
 */
class ProjectConfigController extends ApiController
{
    /**
     * GET /api/projects/{project}/config — the stored + effective config plus the
     * catalogue (profiles and per-key options) so a UI can render the knobs.
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($this->present($project));
    }

    /**
     * PUT /api/projects/{project}/config — set the profile and/or overrides.
     * Bumps config_version on every successful change so clients detect drift.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'profile' => ['sometimes', 'nullable', Rule::in(array_keys(ProjectConfig::PROFILES))],
            'overrides' => ['sometimes', 'array'],
        ]);

        $current = is_array($project->config) ? $project->config : [];

        $config = [
            'profile' => $request->has('profile') ? $request->input('profile') : ($current['profile'] ?? null),
            'overrides' => $request->has('overrides')
                ? ProjectConfig::validateOverrides((array) $request->input('overrides'))
                : ($current['overrides'] ?? []),
        ];

        $project->update([
            'config' => $config,
            'config_version' => $project->config_version + 1,
        ]);

        return response()->json($this->present($project->refresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Project $project): array
    {
        $stored = is_array($project->config) ? $project->config : [];
        $effective = $project->effectiveConfig();

        // Nur die projektspezifische Ergänzung (leer, wenn nichts hinterlegt).
        $notes = filled($project->skill_description)
            ? SkillTemplate::render($project->skill_description, $project)
            : '';

        return [
            'config_version' => $project->config_version,
            'profile' => $stored['profile'] ?? null,
            'overrides' => $stored['overrides'] ?? [],
            'effective' => $effective,
            'client_hints' => ProjectConfig::clientHints($effective),
            // Geteilte, projektunabhängige Inhalte (für alle Skills) + Revision
            // (Header X-Planstack-Skill-Revision) — der Skill lädt sie bei Drift
            // nach, statt neu heruntergeladen zu werden.
            'operating_manual' => SkillTemplate::operatingManual(),
            'status_rules' => SkillTemplate::statusRules(),
            // Projektübergreifende Anweisungen des allgemeinen planstack-Skills
            // (z. B. PR-Titel-Konvention). Nur der planstack-Skill lädt sie nach.
            'skill_instructions' => SkillTemplate::skillInstructions(),
            'skill_revision' => SkillTemplate::sharedRevision(),
            // Anweisungen für `/planstack plan` (Projekt/Phasen/Tasks anlegen,
            // Task-Felder-Leitfaden) — eigene, versionierte Datei, bei jedem
            // plan-Aufruf frisch geladen (self-updating), daher separat.
            'plan_instructions' => SkillTemplate::planInstructions(),
            'plan_revision' => SkillTemplate::planRevision(),
            // Projektspezifische Zusatz-Anweisungen (aus dem Claude-Feld).
            'instructions' => $notes,
            'catalog' => [
                'profiles' => array_keys(ProjectConfig::PROFILES),
                'options' => ProjectConfig::OPTIONS,
                'bool_keys' => ProjectConfig::BOOL_KEYS,
                'int_keys' => ProjectConfig::INT_KEYS,
                'defaults' => ProjectConfig::DEFAULTS,
            ],
        ];
    }
}
