<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SkillTemplate;
use Illuminate\Http\JsonResponse;

class SkillController extends Controller
{
    /**
     * GET /api/skill — the current general SKILL.md as text, plus its revision.
     *
     * Exists so an installed skill can repair its own snapshot: the SKILL.md on disk
     * is a copy of the server-maintained text, and a client that only wrote the new
     * `skill_revision` into its config (without rewriting the file) would follow a
     * stale copy forever — drift detection stays silent because the revisions match.
     * With this endpoint the client can overwrite SKILL.md with exactly what a fresh
     * install carries, using its API token (unlike /skill/download, which needs a
     * web session and mints a new token).
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'skill_md' => SkillTemplate::composed(),
            'skill_revision' => SkillTemplate::sharedRevision(),
            'plan_revision' => SkillTemplate::planRevision(),
        ]);
    }
}
