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
