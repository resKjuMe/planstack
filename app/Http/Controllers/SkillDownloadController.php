<?php

namespace App\Http\Controllers;

use App\Support\SkillTemplate;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SkillDownloadController extends Controller
{
    /**
     * Download the general, project-independent Planstack Claude-Code skill as a
     * ready-to-install ZIP: the bootstrap SKILL.md (usage "/planstack <PROJECT>
     * [<TASK>]") plus the shared operating manual + status rules, and a config.json
     * prefilled with the configured API URL and a freshly minted personal access
     * token for the current user.
     *
     * The project is deliberately NOT baked in — it is passed dynamically as the
     * skill argument, so one install works across every project the user's token
     * can access. The token is revocable under Profile → API-Token. Unzip into
     * ~/.claude/skills/ for the planstack/ folder.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $skillMd = $this->composeSkill();

        if (blank($skillMd)) {
            abort(404, 'Skill-Vorlage nicht gefunden.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'planstack');
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP-Archiv konnte nicht erstellt werden.');
        }

        $folder = 'planstack';

        $zip->addFromString("{$folder}/SKILL.md", $skillMd);

        // Mint a fresh user token (valid for every project the user can access)
        // and embed it, so the skill works out of the box. Named with a timestamp
        // so the user can identify and revoke it under Profile → API-Token.
        $token = $request->user()->createToken('planstack skill '.now()->format('Y-m-d H:i'));

        // Prefilled config: configured (production) API + embedded token. No
        // `project` (dynamic — passed as the skill argument) and no
        // `config_version` (per-project — learned at runtime via the board's
        // X-Planstack-Config-Version header / client_hints block).
        $config = [
            'base_url' => config('planstack.skill_api_url'),
            'token' => $token->plainTextToken,
            // Revision der geteilten Skill-Inhalte (Betriebshandbuch + Statusregeln).
            // Weicht der Header X-Planstack-Skill-Revision davon ab → /config lesen
            // und operating_manual + status_rules neu befolgen (siehe SKILL.md).
            'skill_revision' => SkillTemplate::sharedRevision(),
        ];
        $zip->addFromString(
            "{$folder}/config.json",
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $zip->close();

        return response()
            ->download($tmp, 'planstack-skill.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Compose the full general SKILL.md: bootstrap header (usage + access +
     * self-update) followed by the shared operating manual + status rules.
     * Project-agnostic, so no per-project notes or config snapshot are baked in —
     * per-project behaviour is delivered at runtime via the board's client_hints.
     */
    private function composeSkill(): string
    {
        $parts = [
            rtrim(SkillTemplate::default()),
            rtrim(SkillTemplate::operatingManual()),
            rtrim(SkillTemplate::statusRules()),
        ];

        return implode("\n\n", array_filter($parts))."\n";
    }
}
