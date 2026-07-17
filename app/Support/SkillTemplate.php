<?php

namespace App\Support;

use App\Models\Project;

/**
 * The default Planstack skill text and its placeholder rendering. The template
 * (resources/skill-templates/planstack.md) carries {{alias}}/{{name}} placeholders;
 * they are stored verbatim on a project's skill_description and only replaced
 * with the concrete project values when the skill is downloaded.
 */
class SkillTemplate
{
    public static function path(): string
    {
        return resource_path('skill-templates/planstack.md');
    }

    public static function statusRulesPath(): string
    {
        return resource_path('skill-templates/status-rules.md');
    }

    public static function operatingManualPath(): string
    {
        return resource_path('skill-templates/operating-manual.md');
    }

    public static function skillInstructionsPath(): string
    {
        return resource_path('skill-templates/skill-instructions.md');
    }

    public static function planInstructionsPath(): string
    {
        return resource_path('skill-templates/plan-instructions.md');
    }

    private static function partial(string $path): string
    {
        return is_file($path) ? rtrim((string) file_get_contents($path))."\n" : '';
    }

    /**
     * The shared, server-maintained status rules (single source of truth for all
     * skills).
     */
    public static function statusRules(): string
    {
        return self::partial(self::statusRulesPath());
    }

    /**
     * The shared, server-maintained operating manual — the project-independent
     * workflow that applies to every skill (so it lives here, not in each
     * project's skill text).
     */
    public static function operatingManual(): string
    {
        return self::partial(self::operatingManualPath());
    }

    /**
     * Server-maintained, project-independent instructions for the general
     * `planstack` skill (e.g. the PR-title convention). Served via /config as
     * `skill_instructions`; only the planstack skill re-adopts it on drift, so
     * per-project skills (L2LR/LOG) are unaffected by these directives.
     */
    public static function skillInstructions(): string
    {
        return self::partial(self::skillInstructionsPath());
    }

    /**
     * Server-maintained instructions for the `/planstack plan` sub-command
     * (creating projects/phases/tasks, and the field-by-field task guide incl.
     * IST/SOLL and test cases). Its own versioned file — deliberately NOT part of
     * skill_instructions — fetched fresh from /config on every `plan` call, so it
     * self-updates without a re-download.
     */
    public static function planInstructions(): string
    {
        return self::partial(self::planInstructionsPath());
    }

    /**
     * Independent revision of the plan instructions (surfaced as `plan_revision`
     * via /config). Changes whenever plan-instructions.md is edited.
     */
    public static function planRevision(): string
    {
        return substr(hash('xxh128', self::planInstructions()), 0, 12);
    }

    /**
     * A short content revision covering all server-maintained skill content
     * (operating manual + status rules + planstack skill instructions). Changes
     * whenever any of them is edited, so clients detect drift via the
     * X-Planstack-Skill-Revision header and re-fetch the parts their SKILL.md
     * tells them to re-adopt.
     */
    public static function sharedRevision(): string
    {
        return substr(
            hash('xxh128', self::operatingManual().'::'.self::statusRules().'::'.self::skillInstructions()),
            0,
            12,
        );
    }

    /**
     * The default skill text (with placeholders), or an empty string when the
     * template file is missing.
     */
    public static function default(): string
    {
        $path = self::path();

        return is_file($path) ? rtrim((string) file_get_contents($path))."\n" : '';
    }

    /**
     * Replace the {{alias}}/{{name}} placeholders with the project's values.
     */
    public static function render(string $text, Project $project): string
    {
        return strtr($text, [
            '{{alias}}' => $project->alias,
            '{{name}}' => $project->name,
        ]);
    }
}
