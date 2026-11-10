<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PsReviewSkill;
use Illuminate\Http\JsonResponse;

class PsReviewSkillController extends Controller
{
    /**
     * GET /api/skill/ps-review — aktuelle SKILL.md und settings.json des ps-review-Skills.
     * Damit aktualisiert sich ein installierter Skill mit seinem Token, ohne ZIP-Download
     * (der braucht eine Web-Session und erzeugt jedes Mal einen neuen Token).
     */
    public function __invoke(): JsonResponse
    {
        $settings = dirname(PsReviewSkill::path()).'/settings.json';

        return response()->json([
            'version' => PsReviewSkill::version(),
            'skill_md' => (string) file_get_contents(PsReviewSkill::path()),
            'settings_json' => is_file($settings) ? (string) file_get_contents($settings) : null,
        ]);
    }
}
