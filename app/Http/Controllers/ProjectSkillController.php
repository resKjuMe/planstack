<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

        // The SKILL.md text comes from the project settings (skill_description),
        // falling back to the bundled default template. The stored text carries
        // {{alias}}/{{name}} placeholders that are resolved here at download time.
        $skillText = filled($project->skill_description)
            ? $project->skill_description
            : SkillTemplate::default();

        if (blank($skillText)) {
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

        $zip->addFromString(
            "{$folder}/SKILL.md",
            rtrim(SkillTemplate::render($skillText, $project))."\n",
        );

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
