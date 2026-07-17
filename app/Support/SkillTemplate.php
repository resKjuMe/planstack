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
     * A short content revision covering all shared skill content (operating
     * manual + status rules). Changes whenever either partial is edited, so
     * clients detect drift via the X-Planstack-Skill-Revision header and re-fetch.
     */
    public static function sharedRevision(): string
    {
        return substr(hash('xxh128', self::operatingManual().'::'.self::statusRules()), 0, 12);
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
