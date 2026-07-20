<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Best-effort-Parser für die Freitext-Felder eines Tasks. Es gibt kein
 * erzwungenes Format — diese Heuristiken zerlegen Prosa für die Detailseite
 * (und den Convert-Endpoint) in strukturierte Häppchen und fallen bei fehlender
 * Struktur auf den Rohtext zurück.
 */
class TaskContentParser
{
    /**
     * Zerlegt einen Checklisten-Freitext in Einzel-Items.
     *
     * Erkennt nummerierte Listen (`1.`/`1)`), Bullets (`-`/`*`/`•`) und sonst
     * einzelne Zeilen. Für `kind=test` werden Zeilen mit Präfix „Erwartung:" als
     * Prüfschritt (role `expectation`) und „Hinweis:" als Fußnote (role `hint`)
     * markiert; sonst `step`. Für `kind=acceptance` ist die Rolle `item`.
     *
     * @return array<int, array{text: string, role: string}>
     */
    public static function checklist(string $text, string $kind = 'acceptance'): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Zeilen als primäre Trennung; steht alles in einer Zeile, an inline
        // nummerierten Markern („1) …2) …") bzw. Semikolon aufteilen.
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        if (count($lines) <= 1) {
            $blob = $lines[0] ?? $text;
            $split = preg_split('/\s*(?=\d+[\.\)]\s)/', $blob) ?: [$blob];
            if (count($split) <= 1) {
                $split = preg_split('/\s*;\s*/', $blob) ?: [$blob];
            }
            $lines = array_values(array_filter(array_map('trim', $split), fn ($l) => $l !== ''));
        }

        $items = [];
        $section = null; // aktuelle AK-Sektion (scope|done_when|contract)

        foreach ($lines as $line) {
            // Akzeptanzkriterien: Abschnitts-Überschriften auf eigener Zeile
            // („Scope:", „Done when:", „Contract:") setzen die Rolle der folgenden
            // Items; nur „Done when" ist abhakbar.
            if ($kind === 'acceptance'
                && preg_match('/^\**\s*(Scope|Done when|Contract)\s*\**\s*:?\s*$/iu', $line, $hm)) {
                $section = match (strtolower($hm[1])) {
                    'scope' => 'scope',
                    'done when' => 'done_when',
                    'contract' => 'contract',
                };

                continue;
            }

            // Führende Marker entfernen: „1.", „1)", „-", „*", „•".
            $clean = preg_replace('/^\s*(?:\d+[\.\)]|[-*•])\s*/u', '', $line) ?? $line;
            $clean = trim($clean);
            if ($clean === '') {
                continue;
            }

            if ($kind === 'test') {
                $role = 'step';
                if (preg_match('/^\**\s*Erwartung\s*:\s*\**\s*(.+)$/isu', $clean, $m)) {
                    $role = 'expectation';
                    $clean = trim($m[1]);
                } elseif (preg_match('/^\**\s*Hinweis\s*:\s*\**\s*(.+)$/isu', $clean, $m)) {
                    $role = 'hint';
                    $clean = trim($m[1]);
                }
            } else {
                $role = $section ?? 'item';
            }

            $items[] = ['text' => $clean, 'role' => $role];
        }

        return $items;
    }

    /**
     * Erkennt eine IST/SOLL-Gegenüberstellung. Gibt `['ist' => …, 'soll' => …]`
     * zurück, wenn mindestens einer der Abschnitte `IST:`/`SOLL:` vorhanden ist,
     * sonst `null` (Aufrufer rendert dann den Rohtext).
     *
     * @return array{ist: ?string, soll: ?string}|null
     */
    public static function targetActual(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Label optional mit Markdown-Fett (**IST:**) / Bindestrich-Varianten.
        $hasIst = preg_match('/(?:^|\n)\s*\**\s*IST\b[\s:–-]/iu', $text);
        $hasSoll = preg_match('/(?:^|\n)\s*\**\s*SOLL\b[\s:–-]/iu', $text);

        if (! $hasIst && ! $hasSoll) {
            return null;
        }

        $ist = null;
        $soll = null;

        if (preg_match('/\**\s*IST\b\s*\**\s*[:–-]?\s*(.*?)(?=(?:^|\n)\s*\**\s*SOLL\b|\z)/isu', $text, $m)) {
            $ist = trim($m[1]) ?: null;
        }
        if (preg_match('/\**\s*SOLL\b\s*\**\s*[:–-]?\s*(.*?)(?=(?:^|\n)\s*\**\s*IST\b|\z)/isu', $text, $m)) {
            $soll = trim($m[1]) ?: null;
        }

        if ($ist === null && $soll === null) {
            return null;
        }

        return ['ist' => $ist, 'soll' => $soll];
    }

    /**
     * Löst Verlaufs-Absätze aus einer Beschreibung heraus („Umgesetzt:",
     * „Scope-Entscheidung:", „Concern gelöst", „Bestätigt …"). Gibt die
     * bereinigte Beschreibung plus die extrahierten Events zurück (Datum
     * best-effort erkannt, sonst null → ans Ende der Timeline).
     *
     * @return array{clean: string, events: array<int, array{label: string, text: string, date: ?Carbon}>}
     */
    public static function descriptionEvents(string $text): array
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return ['clean' => '', 'events' => []];
        }

        // Match tokens stay the (German) markers actually written in task
        // descriptions; the display label is looked up per locale. 'match' is
        // used by the timeline to strip the prefix, 'key' for the shown title.
        $labels = [
            'umgesetzt' => ['match' => 'Umgesetzt', 'key' => 'implemented'],
            'scope-entscheidung' => ['match' => 'Scope-Entscheidung', 'key' => 'scope_decision'],
            'concern gelöst' => ['match' => 'Concern gelöst', 'key' => 'concern_resolved'],
            'bestätigt' => ['match' => 'Bestätigt', 'key' => 'confirmed'],
        ];
        $pattern = '/^\s*\**\s*(Umgesetzt|Scope-Entscheidung|Concern gelöst|Bestätigt)\b[^\n]*/iu';

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept = [];
        $events = [];

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $m)) {
                $def = $labels[mb_strtolower(trim($m[1]))] ?? null;
                $matchToken = $def['match'] ?? trim($m[1]);
                $label = $def ? __('timeline.'.$def['key']) : trim($m[1]);
                // Datum best-effort: dd.mm.yyyy oder yyyy-mm-dd im Absatz.
                $date = null;
                if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $line, $d)) {
                    $date = self::safeDate(sprintf('%04d-%02d-%02d', $d[3], $d[2], $d[1]));
                } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $line, $d)) {
                    $date = self::safeDate("{$d[1]}-{$d[2]}-{$d[3]}");
                }

                $events[] = [
                    'label' => $label,
                    'match' => $matchToken,
                    'text' => trim($line),
                    'date' => $date,
                ];

                continue;
            }

            $kept[] = $line;
        }

        return [
            'clean' => trim(implode("\n", $kept)),
            'events' => $events,
        ];
    }

    private static function safeDate(string $iso): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $iso)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
