// Clientseitige Ableitung der Zeitachsen-Unterseite: der ABHÄNGIGKEITSBAUM kommt
// aus den Tasks des geteilten Stores, die BALKEN aus dem Verlaufs-Endpunkt
// (GET /api/projects/{alias}/timeline). Reine Funktionen, keine Netzzugriffe.
//
// Warum zwei Quellen: der Task kennt nur seinen jetzigen Status — wann er in
// welchem Status LAG, steht nur im Änderungsprotokoll. Der Baum dagegen steckt in
// den Voraussetzungen (prerequisites), die der Store ohnehin lädt.

const DAY_MS = 86400000;

/** Ortszeit-Mitternacht des Tages, in dem `ms` liegt. */
function startOfDay(ms) {
    const d = new Date(ms);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
}

/** Mitternacht `count` Kalendertage nach `ms` — über Date, damit Zeitumstellungen stimmen. */
function addDays(ms, count) {
    const d = new Date(ms);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate() + count).getTime();
}

/**
 * Die Kalenderspalten der Achse: ein Eintrag je Tag, mit seiner Lage in Prozent —
 * damit Gitterlinien und Balken derselben Rechnung folgen (und über eine
 * Zeitumstellung hinweg zusammenpassen, denn Tage sind dann nicht gleich lang).
 */
function buildDays(axisFrom, axisTo, days, locale) {
    const span = axisTo - axisFrom;
    const today = startOfDay(Date.now());
    const dayFmt = new Intl.DateTimeFormat(locale, { day: '2-digit' });
    const monthFmt = new Intl.DateTimeFormat(locale, { month: 'short' });
    const fullFmt = new Intl.DateTimeFormat(locale, { weekday: 'short', day: '2-digit', month: '2-digit' });

    const cells = [];
    for (let i = 0; i < days; i++) {
        const start = addDays(axisFrom, i);
        const end = addDays(axisFrom, i + 1);
        const date = new Date(start);
        const weekday = date.getDay();

        cells.push({
            start,
            end,
            left: ((start - axisFrom) / span) * 100,
            width: ((end - start) / span) * 100,
            dayLabel: dayFmt.format(date),
            // Monatskürzel nur am Monatsanfang (und in der ersten Spalte) — sonst
            // wird die Kopfzeile bei 30 Spalten unlesbar.
            monthLabel: i === 0 || date.getDate() === 1 ? monthFmt.format(date) : null,
            fullLabel: fullFmt.format(date),
            isWeekend: weekday === 0 || weekday === 6,
            isToday: start === today,
        });
    }

    return cells;
}

/** Menschliche Dauer: Minuten, Stunden oder Tage — je nachdem, was sich lohnt. */
function durationLabel(ms, strings, transChoice) {
    const minutes = ms / 60000;
    if (minutes < 60) {
        return transChoice(strings.minutesCount, Math.max(1, Math.round(minutes)), {});
    }
    const hours = minutes / 60;
    if (hours < 24) {
        return transChoice(strings.hoursCount, Math.round(hours), {});
    }
    return transChoice(strings.daysCount, Math.round(hours / 24), {});
}

/**
 * Anteil der Achse, unter dem ein Stück nicht mehr als eigenes Stück lesbar ist
 * (bei 30 Spalten ≈ 3 Stunden bzw. 2–3 Pixel). Kürzere Aufenthalte in Folge werden
 * gebündelt — ein Worker, der binnen Sekunden fünfmal den Status wechselt, würde
 * sonst fünf übereinanderliegende Striche erzeugen, die nichts zeigen.
 */
const MIN_VISIBLE_SHARE = 0.004;

/**
 * Aufeinanderfolgende Aufenthalte DESSELBEN Status zusammenfassen. Solche Paare
 * entstehen im Protokoll real (Statuswechsel hin und sofort zurück) und wären als
 * zwei Stücke nur doppelt gezeichnet.
 */
function mergeSameStatus(stays) {
    const merged = [];
    for (const stay of stays) {
        const prev = merged[merged.length - 1];
        if (prev && prev.key === stay.key && stay.from - prev.to < 1000) {
            prev.to = Math.max(prev.to, stay.to);
            prev.open = stay.open;
            continue;
        }
        merged.push({ ...stay });
    }

    return merged;
}

/**
 * Die Balken-Stücke eines Tasks: ein Stück je Status-Aufenthalt, auf das Fenster
 * zugeschnitten. Wartestatus (pickbar) bleiben draußen — der Balken soll den Weg
 * VOM CLAIM BIS ZUR FREIGABE zeigen, nicht das Liegen davor. Der Eintritt in einen
 * Abschluss-Status wird stattdessen als Markierung zurückgegeben.
 */
function buildBars({ stays, statusByKey, axisFrom, axisTo, nowMs, strings, interpolate, transChoice, timeFmt }) {
    const span = axisTo - axisFrom;
    const minVisibleMs = span * MIN_VISIBLE_SHARE;

    const work = [];
    let doneAt = null;

    for (const stay of stays) {
        const status = statusByKey.get(stay.key);
        const from = Date.parse(stay.from);
        // Ein laufender Aufenthalt endet „jetzt", nicht am Rand der letzten Spalte.
        const to = stay.open ? nowMs : Date.parse(stay.to);
        if (!Number.isFinite(from) || !Number.isFinite(to)) continue;

        if (status?.counts_as_done) {
            // Der Abschluss ist ein ZEITPUNKT (der Eintritt in den Status), kein
            // Zeitraum — der früheste im Fenster gewinnt.
            if (from >= axisFrom && from <= axisTo && (doneAt === null || from < doneAt.at)) {
                doneAt = { at: from, left: ((from - axisFrom) / span) * 100, label: status.label };
            }
            continue;
        }

        // Wartezeit vor der Bearbeitung gehört nicht in den Balken.
        if (!status || status.kind === 'waiting') continue;
        if (to <= axisFrom || from >= axisTo) continue;

        work.push({ key: stay.key, status, from, to, open: !!stay.open });
    }

    work.sort((a, b) => a.from - b.from || a.to - b.to);

    // Ein Stück fürs Zeichnen: auf das Fenster zugeschnitten, Tooltip mit den
    // ECHTEN (unbeschnittenen) Grenzen.
    const piece = (from, to, status, key, open, extra = {}) => ({
        key,
        label: status.label,
        bar: status.bar,
        kind: status.kind,
        open,
        left: ((Math.max(from, axisFrom) - axisFrom) / span) * 100,
        width: ((Math.min(to, axisTo) - Math.max(from, axisFrom)) / span) * 100,
        startsBefore: from < axisFrom,
        ...extra,
    });

    const bars = [];
    let run = [];

    // Ein Lauf kurzer Aufenthalte wird ein Stück: gefärbt nach dem längsten davon,
    // beschriftet mit der Zahl der Wechsel — so bleibt sichtbar, DASS dort etwas
    // passiert ist, ohne unlesbare Striche zu stapeln.
    const flushRun = () => {
        if (run.length === 0) return;

        if (run.length === 1) {
            const s = run[0];
            bars.push(
                piece(s.from, s.to, s.status, s.key, s.open, {
                    title: interpolate(strings.barTooltip, {
                        status: s.status.label,
                        from: timeFmt.format(new Date(s.from)),
                        to: s.open ? strings.stillOpen : timeFmt.format(new Date(s.to)),
                        duration: durationLabel(s.to - s.from, strings, transChoice),
                    }),
                }),
            );
        } else {
            const from = run[0].from;
            const to = run[run.length - 1].to;
            const open = run[run.length - 1].open;
            const dominant = run.reduce((a, b) => (b.to - b.from > a.to - a.from ? b : a));

            bars.push(
                piece(from, to, dominant.status, dominant.key, open, {
                    mixed: run.length,
                    title: interpolate(strings.mixedTooltip, {
                        count: run.length,
                        from: timeFmt.format(new Date(from)),
                        to: open ? strings.stillOpen : timeFmt.format(new Date(to)),
                        duration: durationLabel(to - from, strings, transChoice),
                    }),
                }),
            );
        }

        run = [];
    };

    for (const stay of mergeSameStatus(work)) {
        if (stay.to - stay.from < minVisibleMs) {
            run.push(stay);
            continue;
        }
        flushRun();
        run = [stay];
        flushRun();
    }
    flushRun();

    return { bars, doneAt };
}

/**
 * @param {object} args
 * @param {Array}  args.tasks            rohe API-Tasks (TaskResource fields=full)
 * @param {Array}  args.phases           [{id, name, position}]
 * @param {object} args.statusConfig     { statuses, roleKey }
 * @param {object} args.history          Antwort von GET /api/projects/{alias}/timeline
 * @param {string} args.taskUrlTemplate  mit __ID__
 * @param {string} args.locale
 * @param {object} args.strings
 * @param {Function} args.interpolate
 * @param {Function} args.transChoice
 * @returns {{roots: Array, days: Array, legend: Array, axisFrom: number, axisTo: number,
 *           nowLeft: number, windowDays: number, totalCount: number, activeCount: number}}
 */
export function deriveTimeline({
    tasks,
    phases,
    statusConfig,
    history,
    taskUrlTemplate,
    locale,
    strings,
    interpolate,
    transChoice,
}) {
    const statuses = statusConfig?.statuses || [];
    const roleKey = statusConfig?.roleKey || {};
    const statusByKey = new Map(statuses.map((s) => [s.key, s]));
    const byId = new Map(tasks.map((t) => [t.id, t]));

    // Achse: ganze Kalendertage, damit die Spalten gleich breit sind. Das Fenster
    // des Servers endet „jetzt" — der heutige Tag wird auf seine volle Breite
    // ergänzt und `jetzt` als Linie darin markiert.
    const windowDays = history?.days || 30;
    const axisFrom = startOfDay(Date.parse(history?.from ?? '') || Date.now() - (windowDays - 1) * DAY_MS);
    const axisTo = addDays(axisFrom, windowDays);
    const nowMs = Date.parse(history?.to ?? '') || Date.now();

    const timeFmt = new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

    const phasePosition = new Map((phases || []).map((p) => [p.id, p.position ?? 0]));
    const url = (id) => (taskUrlTemplate ? taskUrlTemplate.replace('__ID__', String(id)) : null);
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;

    // Reihenfolge im Baum: Phase, dann Name (numerisch, damit T-2 vor T-10 steht).
    const collator = new Intl.Collator(locale, { numeric: true, sensitivity: 'base' });
    const sortedTasks = [...tasks].sort((a, b) => {
        const pa = a.phase_id != null ? phasePosition.get(a.phase_id) ?? Number.MAX_SAFE_INTEGER : Number.MAX_SAFE_INTEGER;
        const pb = b.phase_id != null ? phasePosition.get(b.phase_id) ?? Number.MAX_SAFE_INTEGER : Number.MAX_SAFE_INTEGER;
        return pa !== pb ? pa - pb : collator.compare(String(a.name), String(b.name));
    });
    const rank = new Map(sortedTasks.map((t, i) => [t.id, i]));

    // Ein Task hängt oft von mehreren Voraussetzungen ab — im BAUM steht er unter
    // der ersten (nach obiger Reihenfolge); die weiteren nennt die Zeile als
    // Querverweis. So erscheint jeder Task genau einmal.
    const parentOf = new Map();
    for (const t of sortedTasks) {
        const pres = (t.prerequisites || [])
            .map((p) => p.id)
            .filter((id) => id !== t.id && byId.has(id))
            .sort((a, b) => (rank.get(a) ?? 0) - (rank.get(b) ?? 0));
        parentOf.set(t.id, pres.length > 0 ? pres[0] : null);
    }

    // Zyklen (A setzt B voraus und umgekehrt) würden den Baum unendlich tief
    // machen: die Kante, die zurückführt, wird gekappt — der Task wird zur Wurzel.
    for (const t of sortedTasks) {
        const seen = new Set([t.id]);
        let cur = parentOf.get(t.id);
        while (cur != null) {
            if (seen.has(cur)) {
                parentOf.set(t.id, null);
                break;
            }
            seen.add(cur);
            cur = parentOf.get(cur);
        }
    }

    const historyByTask = history?.tasks || {};
    const nodes = new Map();

    for (const t of sortedTasks) {
        const key = displayKeyOf(t);
        const status = statusByKey.get(key);
        const { bars, doneAt } = buildBars({
            stays: historyByTask[t.id] || historyByTask[String(t.id)] || [],
            statusByKey,
            axisFrom,
            axisTo,
            nowMs,
            strings,
            interpolate,
            transChoice,
            timeFmt,
        });

        const parentId = parentOf.get(t.id);

        nodes.set(t.id, {
            id: t.id,
            name: t.name,
            summary: t.summary,
            url: url(t.id),
            pr: t.pr_number ?? null,
            prUrl: t.pr_url ?? null,
            statusKey: key,
            statusLabel: status?.label ?? t.display_status_label ?? key,
            badge: status?.badge ?? '',
            claimer: t.claimed_by ?? null,
            sp: Number(t.effort?.story_points || 0),
            // Querverweise: alle Voraussetzungen außer der, unter der er hängt.
            extraDeps: (t.prerequisites || [])
                .filter((p) => p.id !== parentId && p.id !== t.id)
                .map((p) => ({ id: p.id, name: p.name, url: url(p.id) })),
            bars,
            doneAt,
            hasOwnActivity: bars.length > 0 || doneAt !== null,
            children: [],
            descendants: 0,
            hasActivity: false, // unten inkl. Nachfolger
        });
    }

    const roots = [];
    for (const t of sortedTasks) {
        const node = nodes.get(t.id);
        const parent = parentOf.get(t.id) != null ? nodes.get(parentOf.get(t.id)) : null;
        if (parent) parent.children.push(node);
        else roots.push(node);
    }

    // Nachfolger-Zahl und „irgendwo darunter war Aktivität" in einem Durchlauf von
    // unten nach oben: die Filter-Pille darf einen Zweig nicht abschneiden, dessen
    // Kind sich bewegt hat.
    const rollUp = (node) => {
        let count = 0;
        let active = node.hasOwnActivity;
        for (const child of node.children) {
            rollUp(child);
            count += 1 + child.descendants;
            active = active || child.hasActivity;
        }
        node.descendants = count;
        node.hasActivity = active;
    };
    for (const root of roots) rollUp(root);

    // Legende: nur Status, die im Fenster wirklich vorkommen — in der Reihenfolge
    // der Status-Konfiguration.
    const seenKeys = new Set();
    for (const node of nodes.values()) {
        for (const bar of node.bars) seenKeys.add(bar.key);
    }
    const legend = statuses
        .filter((s) => seenKeys.has(s.key))
        .map((s) => ({ key: s.key, label: s.label, bar: s.bar }));

    return {
        roots,
        days: buildDays(axisFrom, axisTo, windowDays, locale),
        legend,
        axisFrom,
        axisTo,
        nowLeft: Math.min(100, Math.max(0, ((nowMs - axisFrom) / (axisTo - axisFrom)) * 100)),
        windowDays,
        totalCount: nodes.size,
        activeCount: Array.from(nodes.values()).filter((n) => n.hasOwnActivity).length,
    };
}

/**
 * Baum → Zeilenliste für das Rendern: eingeklappte Knoten verbergen ihre Nachfolger,
 * die Filter-Pille „nur mit Aktivität" behält Vorfahren aktiver Tasks.
 *
 * @param {Array} roots
 * @param {Set<number>} collapsed
 * @param {boolean} activeOnly
 * @returns {Array<{node: object, depth: number, hasChildren: boolean, isCollapsed: boolean, hiddenChildren: number}>}
 */
export function flattenTree(roots, collapsed, activeOnly) {
    const rows = [];

    const walk = (node, depth) => {
        if (activeOnly && !node.hasActivity) return;

        const children = activeOnly ? node.children.filter((c) => c.hasActivity) : node.children;
        const isCollapsed = collapsed.has(node.id);

        rows.push({
            node,
            depth,
            hasChildren: children.length > 0,
            isCollapsed,
            hiddenChildren: isCollapsed ? children.length : 0,
        });

        if (!isCollapsed) {
            for (const child of children) walk(child, depth + 1);
        }
    };

    for (const root of roots) walk(root, 0);

    return rows;
}
