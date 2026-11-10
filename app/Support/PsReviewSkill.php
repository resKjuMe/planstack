<?php

namespace App\Support;

/**
 * Der ausgelieferte ps-review-Skill und seine Version aus dem Frontmatter.
 */
class PsReviewSkill
{
    public static function path(): string
    {
        return resource_path('skill-templates/ps-review/SKILL.md');
    }

    public static function version(): ?string
    {
        $text = is_file(self::path()) ? (string) file_get_contents(self::path()) : '';

        return preg_match('/\A---\R(?:.*\R)*?version:\s*"?([0-9][0-9.]*)"?\s*\R(?:.*\R)*?---/u', $text, $m) ? $m[1] : null;
    }

    /** Ohne Versionsangabe gilt ein Review als mit veraltetem Skill erstellt. */
    public static function isOutdated(?string $version): bool
    {
        $current = self::version();

        return $current !== null && ($version === null || version_compare($version, $current, '<'));
    }
}
