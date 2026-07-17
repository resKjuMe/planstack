<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectConfig;
use App\Support\SkillTemplate;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ProjectSkillController extends Controller
{
    /**
     * Download the Planstack Claude-Code skill as a ready-to-install ZIP: the skill's
     * own files verbatim plus a config.json prefilled with the configured API
     * URL, the project alias and a freshly minted personal access token for the
     * current user — so the skill works out of the box. The token is revocable
     * under Profile → API-Token. Unzip into ~/.claude/skills/ for the <alias>/ folder.
     */
    public function __invoke(Request $request, Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);

        // The SKILL.md is composed at download time from: the bundled bootstrap
        // header, the project's optional custom notes (skill_description), a
        // compact snapshot of the effective Claude config, and the shared
        // operating manual + status rules. Kept compact for token efficiency; the
        // server stays the source of truth and self-updates via the version headers.
        $skillMd = $this->composeSkill($project);

        if (blank($skillMd)) {
            abort(404, 'Skill-Vorlage nicht gefunden.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'planstack');
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP-Archiv konnte nicht erstellt werden.');
        }

        // The skill folder is named after the project alias (safe: alpha_dash),
        // so it installs as ~/.claude/skills/<alias>/ instead of a generic Planstack/.
        $folder = $project->alias;

        $zip->addFromString("{$folder}/SKILL.md", $skillMd);

        // Mint a fresh token for the current user and embed it, so the skill is
        // usable immediately. Named after the project + timestamp so the user can
        // identify and revoke it under Profile → API-Token.
        $token = $request->user()->createToken("planstack {$project->alias} ".now()->format('Y-m-d H:i'));

        // Prefilled config: configured (production) API, project, embedded token.
        // base_url defaults to the deployed URL, not the current request host.
        $config = [
            'base_url' => config('planstack.skill_api_url'),
            'token' => $token->plainTextToken,
            'project' => $project->alias,
            // Baseline für die Drift-Erkennung: der Skill sendet diese Version als
            // X-Planstack-Client-Config-Version; weicht sie von der Server-Version
            // ab, liefert das Board einen client_hints-Block (siehe SKILL.md §5).
            'config_version' => $project->config_version,
            // Revision der geteilten Skill-Inhalte (Betriebshandbuch + Statusregeln).
            // Weicht der Header X-Planstack-Skill-Revision davon ab → /config lesen
            // und operating_manual + status_rules neu befolgen.
            'skill_revision' => SkillTemplate::sharedRevision(),
        ];
        $zip->addFromString(
            "{$folder}/config.json",
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        // Ready-to-use MCP server config for Claude Code: the per-project remote
        // MCP endpoint plus the same embedded token. Copy into the project root as
        // .mcp.json, or register it via `claude mcp add` (see MCP.md).
        $mcpBase = rtrim((string) config('planstack.skill_api_url'), '/');
        $mcpUrl = "{$mcpBase}/projects/{$project->alias}/mcp";
        $mcpName = "planstack-{$project->alias}";
        $mcpConfig = [
            'mcpServers' => [
                $mcpName => [
                    'type' => 'http',
                    'url' => $mcpUrl,
                    'headers' => ['Authorization' => 'Bearer '.$token->plainTextToken],
                ],
            ],
        ];
        $zip->addFromString(
            "{$folder}/.mcp.json",
            json_encode($mcpConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
        $zip->addFromString("{$folder}/MCP.md", $this->mcpReadme($mcpName, $mcpUrl));

        $zip->close();

        return response()
            ->download($tmp, "planstack-skill-{$project->alias}.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Compose the full SKILL.md: bootstrap header + optional project notes +
     * effective-config snapshot + shared operating manual + status rules.
     */
    private function composeSkill(Project $project): string
    {
        $parts = [rtrim(SkillTemplate::render(SkillTemplate::default(), $project))];

        if (filled($project->skill_description)) {
            $parts[] = "## Projektspezifische Hinweise\n\n"
                .rtrim(SkillTemplate::render($project->skill_description, $project));
        }

        $parts[] = rtrim($this->configSnapshot($project));
        $parts[] = rtrim(SkillTemplate::operatingManual());
        $parts[] = rtrim(SkillTemplate::statusRules());

        return implode("\n\n", $parts)."\n";
    }

    /**
     * Actionable gloss per client-hint value — so the skill knows what to *do*,
     * not just the bare key/value. Only the chosen value is emitted (token-lean).
     *
     * @var array<string, array<string, string>>
     */
    private const HINT_GLOSS = [
        'execution.mode' => [
            'headless' => 'jeden Task in einem frischen Prozess/Session abarbeiten',
            'subagent' => 'jeden Task in einem isolierten Subagenten-Kontext abarbeiten',
            'single_session' => 'alle Tasks in einer durchgehenden Session abarbeiten',
        ],
        'context.between_tasks' => [
            'stop' => 'nach jedem Task anhalten (Kontext leeren, z. B. /clear)',
            'continue' => 'ohne Unterbrechung weiterarbeiten',
        ],
        'reread.policy' => [
            'on_conflict' => 'Board nur bei Kollision (409/422) neu lesen',
            'once_per_pick' => 'Board einmal pro Pick neu lesen',
            'before_every_action' => 'Board vor jeder Aktion neu lesen',
        ],
        'actions.bundling' => [
            'true' => 'PR + fertig (+ optional merge) via /complete bündeln',
            'false' => 'PR, Status und Merge als Einzelaufrufe',
        ],
        'instructions.delivery' => [
            'server_enforced' => 'Regeln serverseitig erzwungen — nicht zusätzlich in den Kontext ziehen',
            'changelog' => 'nur das Changelog-Delta beachten',
            'full_doc' => 'das vollständige Regeldokument nutzen',
        ],
        'conventions.delivery' => [
            'server_enforced' => 'Konventionen werden per CI/Lint erzwungen — nicht zusätzlich laden',
            'snippet' => 'nur den relevanten Konventions-Ausschnitt nutzen',
            'full_prose' => 'die vollständigen Konventionen beachten',
        ],
        'concerns.attitude' => [
            'kritisch' => 'Concern früh/häufig melden (jede Unklarheit)',
            'ausgewogen' => 'Concern nur bei echten Blockern/Unklarheiten',
            'mutig' => 'eigenständig vernünftig annehmen, nur harte Blocker melden',
        ],
    ];

    /**
     * A compact, actionable snapshot of the project's effective board config:
     * behaviour hints with a one-line meaning, plus a single info line for the
     * server-enforced settings (which the skill needn't interpret).
     */
    private function configSnapshot(Project $project): string
    {
        $eff = $project->effectiveConfig();
        $hintKeys = ProjectConfig::CLIENT_HINT_KEYS;

        $behaviour = [];
        foreach ($hintKeys as $key) {
            $value = $eff[$key] ?? null;
            if ($key === 'parallelism.max_workers') {
                $behaviour[] = "- `{$key}` = {$value} → bis zu {$value} Worker parallel";

                continue;
            }
            $val = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $text = self::HINT_GLOSS[$key][$val] ?? '';
            $behaviour[] = "- `{$key}` = {$val}".($text !== '' ? " → {$text}" : '');
        }

        $enforced = [];
        foreach ($eff as $key => $value) {
            if (in_array($key, $hintKeys, true)) {
                continue;
            }
            $enforced[] = $key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return "## Board-Konfiguration (Stand: v{$project->config_version})\n\n"
            ."Diese Hinweise steuern **dein Verhalten**:\n\n"
            .implode("\n", $behaviour)."\n\n"
            ."Server-erzwungen (wirkt automatisch, nur zur Info): "
            .implode(', ', $enforced).'.';
    }

    /**
     * Short how-to for the bundled MCP server config (Markdown).
     */
    private function mcpReadme(string $name, string $url): string
    {
        return <<<MD
        # MCP-Server (Alternative zu curl)

        Dieses Paket enthält eine vorbefüllte `.mcp.json` für den **Planstack-MCP-Server**
        dieses Projekts. Statt die REST-API per curl anzusprechen, kann Claude Code die
        Board-/Task-/Phasen-Operationen als **MCP-Tools** nutzen (`get_board`, `list_tasks`,
        `get_task`, `claim_task`, `set_task_status`, `set_task_pr`, `merge_task`,
        `set_task_gate`, `report_concern`, `create_task`, `split_task`, …).

        - **Transport:** Streamable HTTP (JSON-RPC 2.0)
        - **URL:** `{$url}`
        - **Auth:** derselbe Bearer-Token wie in `config.json`

        ## Aktivieren

        **Variante A — projektlokal:** Die `.mcp.json` aus diesem Ordner in das
        Wurzelverzeichnis deines Projekts kopieren. Claude Code lädt sie beim Start.

        **Variante B — global registrieren:**

        ```bash
        claude mcp add --transport http {$name} "{$url}" \\
          --header "Authorization: Bearer <token-aus-config.json>"
        ```

        Der Token ist unter **Profil → API-Token** widerrufbar.
        MD;
    }
}
