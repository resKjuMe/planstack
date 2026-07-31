// Raster der Aktivitäts-Heatmap: aus den sparsen Zählern des Endpunkts
// (StatusActivityPresenter) wird ein Gitter Tag × Stunde.
//
// Reine Funktion wie alle Ableitungen der Unterseiten — kein React, keine Fetches.
// Die Buckets tragen ihren Tag als 'YYYY-MM-DD' in der Zone, in der der Server
// gebucketet hat (die des Browsers); die Spalten entstehen hier mit derselben
// Kalenderlogik, damit die Schlüssel zusammenpassen.

export const RANGE_WEEKS = [4, 12, 26];

const HOURS = Array.from({ length: 24 }, (_, h) => h);

const pad = (n) => String(n).padStart(2, '0');
const dateKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/**
 * Zusammengesetzte Farbcodierung: der FARBTON sagt, welche Status-Familie in dieser
 * Stunde überwog, die HELLIGKEIT die Menge (drei Stufen). Der Server liefert die
 * Familie je Kästchen, abgeleitet aus `kind` der Status-Konfiguration:
 *
 *   work   (blau)  — Bearbeitung: claimed, analyzing, in progress, polishing …
 *   review (lila)  — Review: reviewbar, in review, approved, request changes …
 *   other  (grau)  — alles Übrige: pickbar, blockiert, gemergt, unbekannt
 *
 * Drei Stufen statt fünf, weil die blassen Enden zweier Farbtöne ineinanderlaufen —
 * hell-blau und hell-lila sind selbst mit voller Farbsicht kaum zu trennen. Jede
 * Stufe ist gegen die jeweilige Fläche geprüft (monotone Helligkeit, sichtbare
 * Abstände). Blau gegen Lila bleibt für Rotblindheit (Deuteranopie) ein schwacher
 * Kontrast — darum trägt jedes Kästchen seine Aufschlüsselung im Tooltip und die
 * Legende beide Skalen benannt: die Aussage hängt nie allein am Farbton.
 *
 * Der Dunkelmodus ist eigens gestuft (dunkel → hell), keine Umkehrung der hellen
 * Stufen; das leere Kästchen liegt dort DUNKLER als die Karte (gray-900 gegen
 * gray-800), sonst verschwände das Raster im Hintergrund und die leere Stunde wirkte
 * heller als die mit einem Update.
 */
export const EMPTY_CLASS = 'bg-gray-100 dark:bg-gray-900';

export const GROUP_LEVELS = {
    work: ['bg-sky-500 dark:bg-sky-800', 'bg-sky-600 dark:bg-sky-600', 'bg-sky-800 dark:bg-sky-500'],
    review: ['bg-purple-500 dark:bg-purple-900', 'bg-purple-700 dark:bg-purple-700', 'bg-purple-900 dark:bg-purple-500'],
    other: ['bg-gray-400 dark:bg-gray-600', 'bg-gray-600 dark:bg-gray-400', 'bg-gray-800 dark:bg-gray-200'],
};

/** Reihenfolge in Legende, Tooltip UND als Gleichstands-Entscheid (siehe unten). */
export const GROUPS = ['work', 'review', 'other'];

export const LEVELS = 3;

/** Drei Datenstufen relativ zum Maximum; 0 nur, wenn wirklich nichts passiert ist. */
function levelOf(count, max) {
    if (count <= 0) return 0;

    return Math.min(LEVELS, Math.max(1, Math.ceil((count / Math.max(1, max)) * LEVELS)));
}

/** Klasse eines Kästchens: Farbton aus der Familie, Stufe aus der Menge. */
export function cellClass(cell) {
    if (!cell || cell.level <= 0) return EMPTY_CLASS;

    return (GROUP_LEVELS[cell.group] ?? GROUP_LEVELS.other)[cell.level - 1];
}

/**
 * @param {object} args
 * @param {{buckets: Array<{date: string, hour: number, actor: ?number, count: number}>, to: string}|null} args.payload
 * @param {number} args.weeks  Fensterbreite in Wochen (siehe RANGE_WEEKS)
 * @param {?number} args.actor Nur diese Person zählen; null = alle summiert (Vorgabe)
 * @returns {{columns: Array, hours: Array<number>, cells: Map<string, {count: number, level: number}>, max: number, total: number, busiest: ?object}}
 */
export function buildHeatmap({ payload, weeks, actor = null }) {
    const days = Math.max(1, weeks) * 7;
    // Letzte Spalte = der Tag, an dem das Serverfenster endet (heute). Ohne Antwort
    // wird trotzdem ein leeres Raster gezeichnet, damit die Karte nicht springt,
    // sobald die Daten eintreffen.
    const end = payload?.to ? new Date(payload.to) : new Date();

    const columns = [];
    for (let i = days - 1; i >= 0; i--) {
        // Über setDate zurücklaufen: das rechnet in Kalendertagen und übersteht
        // damit Sommerzeit-Sprünge (86.400.000 ms pro Tag täten das nicht).
        const d = new Date(end.getFullYear(), end.getMonth(), end.getDate() - i);
        columns.push({
            key: dateKey(d),
            date: d,
            // Beschriftet wird montags — ein Tick je Woche, ohne die Achse zu füllen.
            tick: d.getDay() === 1,
            weekend: d.getDay() === 0 || d.getDay() === 6,
        });
    }

    const inWindow = new Set(columns.map((c) => c.key));

    // Erst zählen, dann stufen: Maximum und Stufen beziehen sich auf das, was
    // WIRKLICH GEZEIGT wird (Fenster + Personenfilter) — sonst hinge die Farbe eines
    // kurzen Zeitraums an einem Ausreißer außerhalb, und die Karte einer einzelnen
    // Person wäre gegenüber der Team-Spitze fast leer.
    const counts = new Map();
    const groupTotals = { work: 0, review: 0, other: 0 };
    let total = 0;

    for (const b of payload?.buckets ?? []) {
        if (!inWindow.has(b.date)) continue;
        // Ohne Filter zählen ALLE Verursacher zusammen, auch die ohne protokollierten
        // (actor === null: Konsole/Automation).
        if (actor !== null && Number(b.actor) !== Number(actor)) continue;
        const count = Number(b.count) || 0;
        if (count <= 0) continue;

        const group = GROUP_LEVELS[b.group] ? b.group : 'other';
        const key = `${b.date}|${b.hour}`;
        const cell = counts.get(key) ?? { count: 0, groups: { work: 0, review: 0, other: 0 } };
        cell.count += count;
        cell.groups[group] += count;
        counts.set(key, cell);

        groupTotals[group] += count;
        total += count;
    }

    let max = 0;
    let busiest = null;
    for (const [key, cell] of counts) {
        if (cell.count <= max) continue;
        const [date, hour] = key.split('|');
        max = cell.count;
        busiest = { date, hour: Number(hour), count: cell.count };
    }

    const cells = new Map();
    for (const [key, cell] of counts) {
        // Der Farbton folgt der Familie mit den meisten Updates in DIESER Stunde;
        // bei Gleichstand entscheidet die feste Reihenfolge (GROUPS), damit dieselben
        // Daten immer dieselbe Farbe ergeben.
        const group = GROUPS.reduce((best, g) => (cell.groups[g] > cell.groups[best] ? g : best), GROUPS[0]);

        cells.set(key, {
            count: cell.count,
            groups: cell.groups,
            group,
            level: levelOf(cell.count, max),
        });
    }

    return { columns, hours: HOURS, cells, max, total, busiest, days, groupTotals };
}
