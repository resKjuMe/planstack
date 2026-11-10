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
     * Gruppen „Weniger/Mehr als verlangt“ mit ihren Punkten; null, wenn der Abschnitt anders aufgebaut ist.
     * Ein Punkt „Kennung: Rest“ liefert die Kennung (mit Doppelpunkt) separat.
     *
     * @return array{less: list<array{lead: ?string, text: string}>, more: list<array{lead: ?string, text: string}>}|null
     */
    public static function parse(string $markdown): ?array
    {
        $groups = ['less' => [], 'more' => []];
        $current = null;
        foreach (preg_split('/\R/u', self::asBullets($markdown)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^\*\*(Weniger|Mehr) als verlangt\s*:\s*\*\*\s*(.*)$/iu', $line, $m)) {
                $current = mb_strtolower($m[1]) === 'weniger' ? 'less' : 'more';

                continue;
            }
            if ($current === null) {
                return null;
            }
            if (preg_match('/^\s*[-*+]\s+(.*)$/u', $line, $m)) {
                $groups[$current][] = $m[1];
            } elseif (preg_match('/^\s{2,}\S/u', $line) && $groups[$current] !== []) {
                $groups[$current][array_key_last($groups[$current])] .= ' '.trim($line);
            } else {
                return null;
            }
        }

        return $current === null ? null : array_map(fn (array $items) => array_map(self::lead(...), $items), $groups);
    }

    /** @return array{lead: ?string, text: string} */
    private static function lead(string $item): array
    {
        $code = false;
        foreach (mb_str_split($item) as $i => $char) {
            if ($i > 160) {
                break;
            }
            if ($char === '`') {
                $code = ! $code;
            } elseif (! $code && $char === ':' && mb_substr($item, $i + 1, 1) === ' ') {
                $rest = trim(mb_substr($item, $i + 1));

                return $rest === '' ? ['lead' => null, 'text' => $item] : ['lead' => mb_substr($item, 0, $i + 1), 'text' => $rest];
            }
        }

        return ['lead' => null, 'text' => $item];
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
