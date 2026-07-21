<?php

namespace App\Http\Controllers\Api;

use App\Models\OrgStatusTransition;
use App\Models\Project;
use App\Support\ProjectConfig;
use App\Support\SkillTemplate;
use App\Support\StatusRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        // Status-Regeln = geteilte Basis + org-spezifischer Block (tatsächliche
        // Status/Übergänge/Automationen dieser Organisation). Die Skill-Revision
        // bezieht diesen Block ein, damit Clients Statusänderungen als Drift
        // erkennen (status_config_version fliesst ueber den Inhalt mit ein).
        $statusRules = $project->organization
            ? rtrim(SkillTemplate::statusRules())."\n\n".StatusRules::forOrganization($project->organization)
            : SkillTemplate::statusRules();
        $skillRevision = substr(hash(
            'xxh128',
            SkillTemplate::operatingManual().'::'.$statusRules.'::'.SkillTemplate::skillInstructions(),
        ), 0, 12);

        // Nur die projektspezifische Ergänzung (leer, wenn nichts hinterlegt).
        $notes = filled($project->skill_description)
            ? SkillTemplate::render($project->skill_description, $project)
            : '';

        return [
            'config_version' => $project->config_version,
            // Org-weite Status-Config-Version (Header X-Planstack-Status-Config-Version):
            // die coarse Drift-Marke, die ein Client auf dem Hot-Path beobachtet.
            // Weicht sie vom lokalen Stand ab → hier die config_versions je Tabelle
            // vergleichen und nur die betroffene Config neu übernehmen.
            'status_config_version' => $project->organization?->status_config_version,
            // Feingranulare Drift-Erkennung je Org-Config-Tabelle: der jüngste
            // updated_at (ISO-8601 oder null, wenn leer). Rein additiv — die
            // Projektconfig-Logik oben (config_version/skill_revision) bleibt
            // unverändert. Ein Client vergleicht diese Werte mit seinem lokalen
            // Stand und zieht bei Abweichung NUR die betroffene Config nach,
            // statt das gesamte Skill-Dokument neu zu laden.
            'config_versions' => $this->configVersions($project),
            'profile' => $stored['profile'] ?? null,
            'overrides' => $stored['overrides'] ?? [],
            'effective' => $effective,
            'client_hints' => ProjectConfig::clientHints($effective),
            // Geteilte, projektunabhängige Inhalte (für alle Skills) + Revision
            // (Header X-Planstack-Skill-Revision) — der Skill lädt sie bei Drift
            // nach, statt neu heruntergeladen zu werden.
            'operating_manual' => SkillTemplate::operatingManual(),
            'status_rules' => $statusRules,
            // Projektübergreifende Anweisungen des allgemeinen planstack-Skills
            // (z. B. PR-Titel-Konvention). Nur der planstack-Skill lädt sie nach.
            'skill_instructions' => SkillTemplate::skillInstructions(),
            'skill_revision' => $skillRevision,
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

    /**
     * The latest updated_at per organisation config table (ISO-8601 or null).
     * Lets a client detect which single config area drifted and re-adopt only
     * that one, instead of reloading the whole skill document.
     *
     * @return array<string, ?string>
     */
    private function configVersions(Project $project): array
    {
        $org = $project->organization;

        if ($org === null) {
            return [
                'statuses' => null,
                'status_groups' => null,
                'transitions' => null,
                'status_automations' => null,
                'event_automations' => null,
                'custom_fields' => null,
            ];
        }

        $statusIds = $org->statuses()->pluck('id');

        return [
            'statuses' => self::ts($org->statuses()->max('updated_at')),
            'status_groups' => self::ts($org->statusGroups()->max('updated_at')),
            'transitions' => self::ts(
                $statusIds->isEmpty()
                    ? null
                    : OrgStatusTransition::query()->whereIn('from_status_id', $statusIds)->max('updated_at'),
            ),
            'status_automations' => self::ts($org->statusAutomations()->max('updated_at')),
            'event_automations' => self::ts($org->eventAutomations()->max('updated_at')),
            'custom_fields' => self::ts($org->customFields()->max('updated_at')),
        ];
    }

    /**
     * Normalise a raw MAX(updated_at) value (string|Carbon|null) to ISO-8601,
     * or null when the table has no rows for this organisation.
     */
    private static function ts(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
