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
     * The blocks a client may request individually via `?parts=`. The version/drift
     * fields are NOT listed — they always ship, so one `?parts=` call is enough to
     * both re-adopt a block and refresh the local baseline.
     */
    private const PARTS = [
        'status_rules',
        'operating_manual',
        'skill_instructions',
        'plan_instructions',
        'config',
        'catalog',
    ];

    /**
     * GET /api/projects/{project}/config — the stored + effective config plus the
     * catalogue (profiles and per-key options) so a UI can render the knobs.
     *
     * The full payload is ~68 KB, almost all of it server-maintained markdown. A
     * client that only needs to re-adopt one block (the usual case: `status_rules`
     * after an org workflow change, detected via config_versions) can ask for just
     * that one with `?parts=status_rules` and save the rest. Without `parts` the
     * response is unchanged, so existing clients are unaffected.
     *
     * Also revalidatable: the ETag covers the rendered payload — including the org
     * status block and the project's own config, not just the template files — so an
     * organisation changing its workflow reliably invalidates it.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $parts = $this->requestedParts($request);

        $response = response()->json($this->present($project, $parts));

        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * The `parts` filter as a list, or null when the client wants everything.
     *
     * @return list<string>|null
     */
    private function requestedParts(Request $request): ?array
    {
        if (! $request->has('parts')) {
            return null;
        }

        $requested = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('parts')),
        )));

        $unknown = array_diff($requested, self::PARTS);

        if ($requested === [] || $unknown !== []) {
            abort(422, 'Unbekannte parts: '.implode(', ', $unknown === [] ? ['(leer)'] : $unknown)
                .'. Erlaubt: '.implode(', ', self::PARTS).'.');
        }

        return $requested;
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
     * @param  list<string>|null  $parts  Only these blocks (null = everything).
     * @return array<string, mixed>
     */
    private function present(Project $project, ?array $parts = null): array
    {
        $wants = static fn (string $part): bool => $parts === null || in_array($part, $parts, true);

        // Die Versions-/Drift-Felder sind IMMER dabei: ein `?parts=`-Aufruf soll
        // gleichzeitig den Block liefern und die lokale Baseline erneuern können,
        // ohne dass der Client dafür ein zweites Mal anfragen muss.
        $payload = [
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
            // Revision der geteilten Datei-Inhalte (Header X-Planstack-Skill-Revision).
            'skill_revision' => SkillTemplate::sharedRevision(),
            // Eigene Revision der plan-Anweisungen (self-updating, separat versioniert).
            'plan_revision' => SkillTemplate::planRevision(),
            // Revision je Block: sagt — anders als skill_revision — WELCHER Block
            // gedriftet ist, sodass der Client nur diesen per `?parts=<key>` nachliest.
            'revisions' => SkillTemplate::revisions(),
        ];

        if ($wants('config')) {
            $stored = is_array($project->config) ? $project->config : [];
            $effective = $project->effectiveConfig();

            $payload['profile'] = $stored['profile'] ?? null;
            $payload['overrides'] = $stored['overrides'] ?? [];
            $payload['effective'] = $effective;
            $payload['client_hints'] = ProjectConfig::clientHints($effective);
            // Projektspezifische Zusatz-Anweisungen (aus dem Claude-Feld), leer wenn
            // nichts hinterlegt.
            $payload['instructions'] = filled($project->skill_description)
                ? SkillTemplate::render($project->skill_description, $project)
                : '';
        }

        // Geteilte, projektunabhängige Inhalte (für alle Skills) — der Skill lädt sie
        // bei Drift nach, statt neu heruntergeladen zu werden.
        if ($wants('operating_manual')) {
            $payload['operating_manual'] = SkillTemplate::operatingManual();
        }

        if ($wants('status_rules')) {
            // Status-Regeln = geteilte Basis + org-spezifischer Block (tatsächliche
            // Status/Übergänge/Automationen dieser Organisation). Nur der Body-Inhalt
            // `status_rules` trägt den Org-Block; die Skill-Revision NICHT — sie deckt
            // (wie der Header X-Planstack-Skill-Revision) ausschliesslich die geteilten
            // Datei-Inhalte ab. Org-Status-Drift signalisiert stattdessen
            // status_config_version/config_versions. So sind Body-`skill_revision` und
            // Header identisch, und der Client schreibt keine Baseline, die dauerhaft
            // als Drift gilt.
            $payload['status_rules'] = $project->organization
                ? rtrim(SkillTemplate::statusRules())."\n\n".StatusRules::forOrganization($project->organization)
                : SkillTemplate::statusRules();
        }

        // Projektübergreifende Anweisungen des allgemeinen planstack-Skills
        // (z. B. PR-Titel-Konvention). Nur der planstack-Skill lädt sie nach.
        if ($wants('skill_instructions')) {
            $payload['skill_instructions'] = SkillTemplate::skillInstructions();
        }

        // Anweisungen für `/planstack plan` (Projekt/Phasen/Tasks anlegen,
        // Task-Felder-Leitfaden) — eigene, versionierte Datei, bei jedem
        // plan-Aufruf frisch geladen (self-updating), daher separat.
        if ($wants('plan_instructions')) {
            $payload['plan_instructions'] = SkillTemplate::planInstructions();
        }

        if ($wants('catalog')) {
            $payload['catalog'] = [
                'profiles' => array_keys(ProjectConfig::PROFILES),
                'options' => ProjectConfig::OPTIONS,
                'bool_keys' => ProjectConfig::BOOL_KEYS,
                'int_keys' => ProjectConfig::INT_KEYS,
                'defaults' => ProjectConfig::DEFAULTS,
            ];
        }

        return $payload;
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
