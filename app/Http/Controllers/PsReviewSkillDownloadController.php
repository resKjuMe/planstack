<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PsReviewSkillDownloadController extends Controller
{
    private const TEMPLATE_DIR = 'skill-templates/ps-review';

    /**
     * Skill ps-review als ZIP für ~/.claude/skills/: SKILL.md, settings.json und
     * eine config.json mit API-URL und frisch erzeugtem Token des Users (unter
     * Profil → API-Token widerrufbar). Damit läuft der Skill auch ohne den
     * Planstack-Skill.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $dir = resource_path(self::TEMPLATE_DIR);
        abort_unless(is_file("{$dir}/SKILL.md"), 404, 'Skill-Vorlage nicht gefunden.');

        $tmp = tempnam(sys_get_temp_dir(), 'ps-review');
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP-Archiv konnte nicht erstellt werden.');
        }

        $zip->addFile("{$dir}/SKILL.md", 'ps-review/SKILL.md');
        $zip->addFile("{$dir}/settings.json", 'ps-review/settings.json');

        $token = $request->user()->createToken('ps-review skill '.now()->format('Y-m-d H:i'));
        $zip->addFromString('ps-review/config.json', json_encode([
            'base_url' => config('planstack.skill_api_url'),
            'token' => $token->plainTextToken,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $zip->close();

        return response()
            ->download($tmp, 'ps-review-skill.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
