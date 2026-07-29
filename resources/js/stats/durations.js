// Verweildauern je Status aus den Task-Rohdaten des Boards zusammensetzen.
//
// Der Server liefert je Task nur die nackten Aufenthalte (`status_durations`:
// Status-KEY, Tage, Aufenthalte, laufende) — Label, Farbe und Reihenfolge löst der
// Client über die ohnehin geladene Status-Konfiguration auf. Diese Datei ist die
// clientseitige Entsprechung zu App\Support\TaskStatusDurations und erzeugt
// DIESELBE Form, die der UserStatisticsPresenter serverseitig baut, damit beide
// Seiten die gleichen Anzeige-Bausteine verwenden können:
//
//   { segments: [{key, label, bar, position, days}], totalDays, medianTaskDays? }
//
// Reine Funktionen, keine Netz-/DOM-Zugriffe.

/** Median einer Zahlenliste, null bei leerer Liste. */
function median(values) {
    if (!values.length) return null;
    const v = [...values].sort((a, b) => a - b);
    const mid = Math.floor(v.length / 2);
    return v.length % 2 === 1 ? v[mid] : (v[mid - 1] + v[mid]) / 2;
}

/**
 * Nachschlagetabelle Status-KEY → { label, bar, position } aus der
 * Status-Konfiguration des Stores.
 */
export function statusLookup(statusConfig) {
    const map = new Map();
    for (const status of statusConfig?.statuses || []) {
        map.set(status.key, {
            label: status.label,
            bar: status.bar || '',
            badge: status.badge || '',
            position: status.position ?? 0,
        });
    }
    return map;
}

/**
 * Aufenthalte einer Task-Menge je Status bündeln.
 *
 * Rückläufer zählen einzeln und werden aufaddiert: läuft ein Task „in Review →
 * in Arbeit → in Review", trägt der Status beide Aufenthalte. `visits > tasks`
 * ist genau daran erkennbar.
 *
 * @returns {{segments: Array, totalDays: number, medianTaskDays: number|null, byKey: Map}}
 */
export function aggregateDurations(tasks, lookup) {
    const byKey = new Map(); // key → { days, visits, open, tasks:Set }
    const totalPerTask = []; // Gesamtzeit je Task (Basis des Medians)

    for (const task of tasks) {
        const rows = task.status_durations;
        if (!Array.isArray(rows) || rows.length === 0) continue;

        let taskTotal = 0;
        for (const row of rows) {
            const days = Number(row.days || 0);
            taskTotal += days;

            let bucket = byKey.get(row.key);
            if (!bucket) {
                bucket = { days: 0, visits: 0, open: 0, tasks: new Set() };
                byKey.set(row.key, bucket);
            }
            bucket.days += days;
            bucket.visits += Number(row.visits || 0);
            bucket.open += Number(row.open || 0);
            bucket.tasks.add(task.id);
        }
        totalPerTask.push(taskTotal);
    }

    const segments = Array.from(byKey.entries())
        .map(([key, bucket]) => {
            const meta = lookup.get(key) || {};
            return {
                key,
                label: meta.label ?? key,
                bar: meta.bar ?? '',
                position: meta.position ?? Number.MAX_SAFE_INTEGER,
                days: bucket.days,
                visits: bucket.visits,
                openVisits: bucket.open,
                tasks: bucket.tasks.size,
            };
        })
        .sort((a, b) => a.position - b.position);

    return {
        segments,
        totalDays: segments.reduce((sum, s) => sum + s.days, 0),
        // Median über die GESAMTZEITEN der Tasks — die typische Task. Nicht der
        // Median über die Status-Zeilen (unvergleichbare Posten) und nicht die
        // Summe (die wächst einfach mit der Zahl der Tasks).
        medianTaskDays: median(totalPerTask),
        byKey,
    };
}

/**
 * Panel-Zeilen „Verweildauer je Status" über eine Task-Menge: je Status die
 * kumulierte Zeit je Task als Median (führend) und Durchschnitt, plus Aufenthalte
 * und Rückläufer.
 *
 * @returns {Array} in Lebenszyklus-Reihenfolge
 */
export function statusDurationRows(tasks, lookup) {
    // Kumulierte Zeit je (Status, Task) — Basis für Median und Ø je Task.
    const perStatus = new Map(); // key → { days, visits, open, perTask: Map }

    for (const task of tasks) {
        const rows = task.status_durations;
        if (!Array.isArray(rows)) continue;

        for (const row of rows) {
            let bucket = perStatus.get(row.key);
            if (!bucket) {
                bucket = { days: 0, visits: 0, open: 0, perTask: new Map() };
                perStatus.set(row.key, bucket);
            }
            const days = Number(row.days || 0);
            bucket.days += days;
            bucket.visits += Number(row.visits || 0);
            bucket.open += Number(row.open || 0);
            bucket.perTask.set(task.id, (bucket.perTask.get(task.id) || 0) + days);
        }
    }

    return Array.from(perStatus.entries())
        .map(([key, bucket]) => {
            const meta = lookup.get(key) || {};
            const perTask = Array.from(bucket.perTask.values());
            return {
                key,
                label: meta.label ?? key,
                bar: meta.bar ?? '',
                badge: meta.badge ?? '',
                position: meta.position ?? Number.MAX_SAFE_INTEGER,
                tasks: perTask.length,
                visits: bucket.visits,
                openVisits: bucket.open,
                totalDays: bucket.days,
                medianPerTaskDays: median(perTask),
                avgPerTaskDays: perTask.length ? bucket.days / perTask.length : null,
                avgPerVisitDays: bucket.visits ? bucket.days / bucket.visits : null,
            };
        })
        .sort((a, b) => a.position - b.position);
}
