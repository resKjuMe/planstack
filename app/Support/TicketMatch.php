<?php

namespace App\Support;

/**
 * Ticket-Abgleich aus der Review-Zusammenfassung: „Weniger/Mehr als verlangt“ immer als Aufzählung,
 * auch wenn ältere Reviews die Punkte als Fließtext mit „; “ geschrieben haben.
 */
class TicketMatch
{
    public static function asBullets(string $markdown): string
    {
        $out = [];
        foreach (preg_split('/\R/u', $markdown) as $line) {
            if (! preg_match('/^\*\*((?:Weniger|Mehr) als verlangt)\s*:\s*\*\*\s*(.*)$/iu', $line, $m)) {
                $out[] = $line;

                continue;
            }
            $rest = trim($m[2]);
            if ($rest === '' || preg_match('/^nichts\.?$/iu', $rest)) {
                $out[] = $line;

                continue;
            }
            $out[] = "**{$m[1]}:**";
            $out[] = '';
            foreach (self::split($rest) as $item) {
                $out[] = "- {$item}";
            }
            $out[] = '';
        }

        return trim(implode("\n", $out));
    }

    /**
     * Trennt an „; “ außerhalb von Inline-Code und Klammern.
     *
     * @return list<string>
     */
    private static function split(string $text): array
    {
        $items = [];
        $current = '';
        $code = false;
        $depth = 0;
        $chars = mb_str_split($text);
        foreach ($chars as $i => $char) {
            if ($char === '`') {
                $code = ! $code;
            } elseif (! $code && $char === '(') {
                $depth++;
            } elseif (! $code && $char === ')') {
                $depth = max(0, $depth - 1);
            } elseif (! $code && $depth === 0 && $char === ';' && ($chars[$i + 1] ?? ' ') === ' ') {
                $items[] = trim($current);
                $current = '';

                continue;
            }
            $current .= $char;
        }
        $items[] = trim($current);

        return array_values(array_filter($items, fn (string $item) => $item !== ''));
    }
}
