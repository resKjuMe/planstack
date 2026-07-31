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

    /**
     * Kommandospezifische Anleitungen: Text, den NUR das jeweilige Sub-Kommando
     * braucht. Sie stehen absichtlich nicht in der ausgelieferten SKILL.md, sondern
     * werden an die Antwort des Aufrufs gehaengt, ohne den das Kommando nicht
     * stattfinden kann (siehe CommandInstructions). Damit zahlt ein Lauf nur die
     * Anleitung, die er tatsaechlich ausfuehrt — und eine veraltete lokale Kopie
     * dieser Abschnitte kann es nicht mehr geben.
     */
    public const COMMANDS = ['review', 'fix', 'auto'];

    private static function partial(string $path): string
    {
        return is_file($path) ? rtrim((string) file_get_contents($path))."\n" : '';
    }

    /**
     * Die Anleitung eines Sub-Kommandos, oder '' wenn es keine eigene hat (`work`
     * und `settings` brauchen nur den Bootstrap-Teil).
     */
    public static function commandInstructions(string $command): string
    {
        if (! in_array($command, self::COMMANDS, true)) {
            return '';
        }

        return self::partial(resource_path("skill-templates/commands/{$command}.md"));
    }

    /**
     * Alle kommandospezifischen Anleitungen zusammen — der Rueckfallweg: `/config`
     * liefert sie als Teil von `skill_instructions`, damit ein Client sie auch dann
     * bekommt, wenn die Antwort des Pflicht-Calls sie nicht mitgebracht hat.
     */
    public static function allCommandInstructions(): string
    {
        $parts = array_filter(array_map(
            static fn (string $command): string => rtrim(self::commandInstructions($command)),
            self::COMMANDS,
        ));

        return $parts === [] ? '' : implode("\n\n", $parts)."\n";
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
        // Inklusive der kommandospezifischen Teile: `/config` ist der Rueckfallweg,
        // wenn die Antwort des Pflicht-Calls die Anleitung nicht mitgebracht hat
        // (aelterer Server, fehlender Session-Header). Die ausgelieferte SKILL.md
        // enthaelt sie dagegen NICHT — siehe composed().
        $base = rtrim(self::partial(self::skillInstructionsPath()));
        $commands = rtrim(self::allCommandInstructions());

        return implode("\n\n", array_filter([$base, $commands]))."\n";
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
     *
     * Deliberately still a single hash over all three files: the per-project skills
     * (L2LR/LOG) watch exactly this header to notice drift, so its meaning must not
     * narrow. The per-file breakdown below is purely additive.
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
     * A revision per server-maintained file, keyed by the name the API uses for that
     * block (so a client can map a changed entry straight to `?parts=<key>`).
     *
     * Why this exists: `sharedRevision()` is one hash over three files, so editing any
     * of them marks all of them as drifted. A client comparing this map against its
     * stored baseline sees WHICH block changed and pulls only that one back into
     * context, instead of re-reading the whole ~68 KB config document.
     *
     * @return array<string, string>
     */
    public static function revisions(): array
    {
        $revision = static fn (string $text): string => substr(hash('xxh128', $text), 0, 12);

        return [
            'operating_manual' => $revision(self::operatingManual()),
            'status_rules' => $revision(self::statusRules()),
            'skill_instructions' => $revision(self::skillInstructions()),
            'plan_instructions' => $revision(self::planInstructions()),
        ];
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
     * The complete general SKILL.md: bootstrap header (usage + access + self-update)
     * followed by the shared operating manual, status rules and skill instructions.
     * Project-agnostic — per-project behaviour arrives at runtime via client_hints.
     *
     * Single source for both the ZIP download and `GET /api/skill`, so a skill can
     * rewrite its own snapshot with exactly what a fresh install would contain.
     */
    public static function composed(): string
    {
        $parts = [
            rtrim(self::default()),
            rtrim(self::operatingManual()),
            rtrim(self::statusRules()),
            // Bewusst OHNE die kommandospezifischen Teile (review/fix/auto): die
            // kommen zur Laufzeit mit der Antwort des jeweiligen Pflicht-Calls. Eine
            // Datei bleibt es trotzdem — es gibt keine Referenzdateien, die eine
            // Installation nachladen muesste, und ein Client, der diese SKILL.md
            // ueber seine alte schreibt, ist danach vollstaendig arbeitsfaehig.
            rtrim(self::partial(self::skillInstructionsPath())),
        ];

        return implode("\n\n", array_filter($parts))."\n";
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
