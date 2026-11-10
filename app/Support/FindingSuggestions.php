<?php

namespace App\Support;

/**
 * Zerlegt die Befund-Nachricht in Text und die Vorschlags-Optionen
 * im Format `- **A (80 %):** Fix`.
 */
class FindingSuggestions
{
    private const OPTION = '/^(\s*)[-*]\s+\*\*([A-Z])\s*\((\d{1,3})\s*%\)\s*:?\s*\*\*\s*:?\s*(.*)$/u';

    private const HEADING = '/^\s*\*\*Vorschl(?:ag|äge)\s*:?\s*\*\*\s*:?\s*$/u';

    /**
     * @return array{before: string, options: list<array{letter: string, percent: int, text: string}>, after: string}|null
     */
    public static function parse(string $message): ?array
    {
        $lines = preg_split('/\R/u', $message);
        $start = null;
        foreach ($lines as $i => $line) {
            if (preg_match(self::OPTION, $line, $m) && $m[2] === 'A') {
                $start = $i;
                break;
            }
        }
        if ($start === null) {
            return null;
        }

        $options = [];
        $end = count($lines);
        for ($i = $start; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match(self::OPTION, $line, $m)) {
                $options[] = ['letter' => $m[2], 'percent' => min(100, (int) $m[3]), 'lines' => [$m[4]]];

                continue;
            }
            // Fortsetzungszeilen sind eingerückt; Leerzeilen nur, wenn danach noch eingerückt weitergeht.
            if (trim($line) === '') {
                $next = $lines[$i + 1] ?? '';
                if (preg_match('/^\s{2,}\S/u', $next) || preg_match(self::OPTION, $next)) {
                    $options[array_key_last($options)]['lines'][] = '';

                    continue;
                }
                $end = $i;
                break;
            }
            if (preg_match('/^\s{2,}\S/u', $line)) {
                $options[array_key_last($options)]['lines'][] = $line;

                continue;
            }
            $end = $i;
            break;
        }

        $before = array_slice($lines, 0, $start);
        while ($before !== [] && trim(end($before)) === '') {
            array_pop($before);
        }
        if ($before !== [] && preg_match(self::HEADING, end($before))) {
            array_pop($before);
        }

        return [
            'before' => trim(implode("\n", $before)),
            'options' => array_map(fn (array $o) => [
                'letter' => $o['letter'],
                'percent' => $o['percent'],
                'text' => self::dedent($o['lines']),
            ], $options),
            'after' => trim(implode("\n", array_slice($lines, $end))),
        ];
    }

    /**
     * Gliedert Text aus `**Was:** …`-Absätzen in Label und Inhalt; null, wenn er nicht so beginnt.
     *
     * @return list<array{label: string, text: string}>|null
     */
    public static function sections(string $text): ?array
    {
        $sections = [];
        foreach (preg_split('/\R/u', trim($text)) as $line) {
            if (preg_match('/^\*\*([^*\n]{1,40}?)\s*:\s*\*\*\s*(.*)$/u', $line, $m)) {
                $sections[] = ['label' => $m[1], 'lines' => [$m[2]]];

                continue;
            }
            if ($sections === []) {
                return null;
            }
            $sections[array_key_last($sections)]['lines'][] = $line;
        }

        return $sections === [] ? null : array_map(fn (array $s) => ['label' => $s['label'], 'text' => trim(implode("\n", $s['lines']))], $sections);
    }

    /** @param list<string> $lines */
    private static function dedent(array $lines): string
    {
        $first = array_shift($lines);
        $indents = array_map(fn (string $l) => strlen($l) - strlen(ltrim($l)), array_filter($lines, fn (string $l) => trim($l) !== ''));
        $cut = $indents === [] ? 0 : min($indents);

        return trim(implode("\n", [$first, ...array_map(fn (string $l) => substr($l, $cut), $lines)]));
    }
}
