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
 * Fünf Stufen einer EINFARBIGEN Skala (Sequential: Helligkeit trägt die Menge).
 * Stufe 0 ist „nichts passiert" und bleibt neutral grau — bewusst nicht die
 * hellste Farbstufe, sonst wäre „ein Update" von „kein Update" kaum zu
 * unterscheiden. Der Dunkelmodus ist eigens gestuft (dunkel → hell), keine
 * Umkehrung der hellen Stufen; das leere Kästchen liegt dort DUNKLER als die Karte
 * (gray-900 gegen gray-800), sonst verschwände das Raster im Hintergrund und die
 * leere Stunde wirkte heller als die mit einem Update.
 */
const LEVEL_CLASS = [
    'bg-gray-100 dark:bg-gray-900',
    'bg-indigo-200 dark:bg-indigo-900',
    'bg-indigo-400 dark:bg-indigo-700',
    'bg-indigo-600 dark:bg-indigo-500',
    'bg-indigo-800 dark:bg-indigo-300',
];

export const LEGEND_CLASSES = LEVEL_CLASS;

/** Vier Datenstufen relativ zum Maximum; 0 nur, wenn wirklich nichts passiert ist. */
function levelOf(count, max) {
    if (count <= 0) return 0;

    return Math.min(4, Math.max(1, Math.ceil((count / Math.max(1, max)) * 4)));
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
    let total = 0;

    for (const b of payload?.buckets ?? []) {
        if (!inWindow.has(b.date)) continue;
        // Ohne Filter zählen ALLE Verursacher zusammen, auch die ohne protokollierten
        // (actor === null: Konsole/Automation).
        if (actor !== null && Number(b.actor) !== Number(actor)) continue;
        const count = Number(b.count) || 0;
        if (count <= 0) continue;

        const key = `${b.date}|${b.hour}`;
        counts.set(key, (counts.get(key) ?? 0) + count);
        total += count;
    }

    let max = 0;
    let busiest = null;
    for (const [key, count] of counts) {
        if (count <= max) continue;
        const [date, hour] = key.split('|');
        max = count;
        busiest = { date, hour: Number(hour), count };
    }

    const cells = new Map();
    for (const [key, count] of counts) {
        cells.set(key, { count, level: levelOf(count, max) });
    }

    return { columns, hours: HOURS, cells, max, total, busiest, days };
}
