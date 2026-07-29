// Zahlen-/Zeitformate der Auswertungsseiten (Performance je Mitarbeiter,
// persönliche Statistik). Eine Stelle, damit „2,5 Tage" und „180k" überall gleich
// aussehen. Reine Funktionen, keine Netz-/DOM-Zugriffe.
//
// Die Einheiten kommen als `strings` herein (unitMin/unitHours/unitDays), weil sie
// übersetzt sind und der Client kein __() hat.

/** Deutsche Dezimalzahl mit fester Stellenzahl: 2 → "2,0". */
export const deComma = (value, digits = 1) => Number(value || 0).toFixed(digits).replace('.', ',');

/** Wie deComma, aber ohne nachlaufende Null: 2.0 → "2", 2.5 → "2,5". */
export function deTrim(value) {
    return Number(value || 0).toFixed(1).replace('.', ',').replace(/,0$/, '');
}

/** Token-Kurzform wie TaskBoardService::formatTokens: ≥1M → "1,2M", sonst "180k". */
export function formatTokens(tokens) {
    if (!tokens) return '0';
    return tokens >= 1_000_000
        ? (tokens / 1_000_000).toFixed(1).replace('.', ',') + 'M'
        : Math.round(tokens / 1000) + 'k';
}

/**
 * Dauer in Tagen menschenlesbar: unter einer Stunde in Minuten, unter einem Tag
 * in Stunden, darüber in Tagen. null/undefined → null (die Views zeigen dann „—").
 */
export function formatDuration(days, strings) {
    if (days === null || days === undefined) return null;
    const minutes = days * 24 * 60;
    if (minutes < 60) return `${Math.round(Math.max(0, minutes))} ${strings.unitMin}`;
    const hours = minutes / 60;
    if (hours < 24) return `${deComma(hours)} ${strings.unitHours}`;
    return `${deComma(days)} ${strings.unitDays}`;
}

/** Kurzdatum "24.07." — für Achsen und dichte Tabellen. */
export function dateShort(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    const p = (n) => String(n).padStart(2, '0');
    return `${p(d.getDate())}.${p(d.getMonth() + 1)}.`;
}

/** Relative Zeitangabe ("vor 3 Tagen") in der Sprache des Dokuments. */
export function relativeTime(iso, locale) {
    if (!iso) return null;
    const rtf = new Intl.RelativeTimeFormat(locale || 'de', { numeric: 'auto' });
    const diffSec = (new Date(iso).getTime() - Date.now()) / 1000;
    const units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
        ['second', 1],
    ];
    for (const [unit, secs] of units) {
        if (Math.abs(diffSec) >= secs || unit === 'second') return rtf.format(Math.round(diffSec / secs), unit);
    }
    return '';
}

/** Prozent-Abweichung als Label: 0 → "±0 %", positiv mit "+". */
export function deviationLabel(pct) {
    if (pct === null || pct === undefined) return null;
    const rounded = Math.round(pct);
    if (rounded === 0) return '±0 %';
    return rounded > 0 ? `+${rounded} %` : `${rounded} %`;
}

/** Ampelklasse für eine Abweichung: ≤25 % grün, ≤50 % amber, darüber rot. */
export function deviationClass(pct) {
    if (pct === null || pct === undefined) return 'gray';
    const abs = Math.abs(pct);
    return abs <= 25 ? 'green' : abs <= 50 ? 'amber' : 'red';
}

/** Ampelklasse für „Anteil im Ziel" (hoch = gut). */
export function shareClass(pct) {
    if (pct === null || pct === undefined) return 'gray';
    return pct >= 75 ? 'green' : pct >= 50 ? 'amber' : 'red';
}

export const PILL = {
    green: 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    gray: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
};

export const BAR = {
    green: 'bg-green-500',
    amber: 'bg-amber-400',
    red: 'bg-red-400',
    gray: 'bg-gray-300 dark:bg-gray-600',
};

export const TILE_TEXT = {
    green: 'text-green-600 dark:text-green-400',
    amber: 'text-amber-600 dark:text-amber-400',
    red: 'text-red-600 dark:text-red-400',
    gray: 'text-gray-300 dark:text-gray-600',
};
