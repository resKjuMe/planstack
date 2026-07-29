// Clientseitige Ableitung der Performance-Ansicht (Leistung je Mitarbeiter) aus
// dem geteilten Projekt-Store. Wie Summary/Kalibrierung eine REINE Funktion der
// bereits geladenen Tasks — kein eigener Endpunkt, keine Netzzugriffe.
//
// Datenbasis ist ALLES, was ein Task an Statistik hergibt (TaskResource
// fields=full):
//   Zuordnung   claimed_by_id/claimed_by (Bearbeiter), reviewed_by/reviewed_by_name
//   Schätzung   effort.story_points/man_days/tokens, affected_files, criticality
//   Ist-Werte   pr_stats (changed_files/additions/deletions/commits/merged_at)
//   Zeitachse   claimed_at, merged_at, last_reviewed_at
//   Qualität    last_review_recommendation, pr_ci_failed, pr_unresolved_threads,
//               pr_review_decision, concern
//   Board       display_status (→ counts_as_done/delivered), gate, unlocks, pickable
//
// Zuordnung: ein Task zählt für seinen aktuellen Claimer. Wird ein Task
// freigegeben, löscht der Server claimed_by_id (Status-Effekt PICKABLE/@clear) —
// die Auswertung zeigt also den aktuellen Stand, keine Historie. Review-Arbeit
// wird zusätzlich über reviewed_by gezählt, damit sie überhaupt sichtbar ist.

import { transChoice } from '../summary/i18n.js';
import { aggregateDurations, statusDurationRows, statusLookup } from '../stats/durations.js';
import { BAR, PILL, deTrim, deviationLabel, formatDuration, formatTokens, shareClass } from '../stats/format.js';

function median(values) {
    if (!values.length) return null;
    const v = [...values].sort((a, b) => a - b);
    const mid = Math.floor(v.length / 2);
    return v.length % 2 === 1 ? v[mid] : (v[mid - 1] + v[mid]) / 2;
}

function initialsOf(name) {
    const parts = String(name || '?').trim().split(/\s+/).slice(0, 2);
    return parts.map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
}

// Stabile, gut unterscheidbare Avatar-Farbe aus dem Namen (kein Zufall, damit
// dieselbe Person über Renders/Reloads dieselbe Farbe behält).
const AVATARS = [
    'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300',
];

function avatarClass(id) {
    return AVATARS[Math.abs(Number(id) || 0) % AVATARS.length];
}

const DAY_MS = 86400000;

/**
 * @param {object} args
 * @param {Array}  args.tasks           rohe API-Tasks (TaskResource fields=full)
 * @param {object} args.statusConfig    { statuses: [...], roleKey }
 * @param {object} args.strings         Labels/Templates aus dem Presenter
 * @param {string} args.taskUrlTemplate URL-Template mit __ID__-Platzhalter
 * @returns {{people: Array, team: object, unassigned: object, scales: object}}
 */
export function derivePerformance({ tasks, statusConfig, strings, taskUrlTemplate }) {
    const s = strings || {};
    const roleKey = statusConfig?.roleKey || {};
    const byKey = new Map((statusConfig?.statuses || []).map((st) => [st.key, st]));

    const taskUrl = (id) => (taskUrlTemplate ? taskUrlTemplate.replace('__ID__', String(id)) : '#');

    // Status-KEY → Label/Farbe/Position für die Verweildauern (der Server schickt
    // je Task nur die nackten Aufenthalte, siehe TaskResource.status_durations).
    const lookup = statusLookup(statusConfig);

    // Board-Anzeigeschlüssel (Waiting-Tasks tragen den ROLLEN-Namen, siehe
    // BoardPresenter) → Org-Status und damit dessen done/delivered-Flags + Styling.
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;
    const statusOf = (t) => byKey.get(displayKeyOf(t));
    const isDone = (t) => !!statusOf(t)?.counts_as_done;
    const kindOf = (t) => statusOf(t)?.kind ?? null;

    const spOf = (t) => Number(t.effort?.story_points || 0);
    const tokensOf = (t) => Number(t.effort?.tokens || 0);
    const manDaysOf = (t) => Number(t.effort?.man_days || 0);

    // --- Personen sammeln: Claimer (Bearbeiter) UND Reviewer -------------------
    /** @type {Map<number, object>} */
    const people = new Map();

    const bucket = (id, name) => {
        if (id == null) return null;
        let p = people.get(id);
        if (!p) {
            p = {
                id,
                name: name || `#${id}`,
                claimed: [], // alle aktuell zugeordneten Tasks
                reviewed: [], // Tasks, in denen die Person Reviewer ist
            };
            people.set(id, p);
        }
        // Ein später gefundener echter Name gewinnt gegen den Fallback "#id".
        if (name && p.name.startsWith('#')) p.name = name;
        return p;
    };

    for (const t of tasks) {
        const claimer = bucket(t.claimed_by_id, t.claimed_by);
        if (claimer) claimer.claimed.push(t);

        const reviewer = bucket(t.reviewed_by, t.reviewed_by_name);
        if (reviewer) reviewer.reviewed.push(t);
    }

    // --- Kennzahlen je Person -------------------------------------------------
    const rows = Array.from(people.values()).map((p) => {
        const own = p.claimed;
        const delivered = own.filter(isDone);
        const open = own.filter((t) => !isDone(t));

        const sum = (list, fn) => list.reduce((a, t) => a + fn(t), 0);

        const deliveredSp = sum(delivered, spOf);
        const openSp = sum(open, spOf);
        const totalSp = deliveredSp + openSp;

        // Zykluszeit: Claim → Merge. merged_at des Tasks, sonst der PR-Merge
        // (pr_stats.merged_at) — beides wird gepflegt, aber nicht immer beides.
        const cycles = [];
        for (const t of delivered) {
            const mergedAt = t.merged_at || t.pr_stats?.merged_at || null;
            if (!t.claimed_at || !mergedAt) continue;
            const days = (new Date(mergedAt).getTime() - new Date(t.claimed_at).getTime()) / DAY_MS;
            if (Number.isFinite(days) && days >= 0) cycles.push({ days, sp: spOf(t) });
        }
        const cycleDaysList = cycles.map((c) => c.days);
        const cycleMedian = median(cycleDaysList);
        const cycleAvg = cycleDaysList.length
            ? cycleDaysList.reduce((a, b) => a + b, 0) / cycleDaysList.length
            : null;
        const perSpList = cycles.filter((c) => c.sp > 0).map((c) => c.days / c.sp);
        const timePerSp = median(perSpList);

        // Velocity: gelieferte SP über die Spanne erster Claim → letzter Merge.
        // Bezugsgröße sind nur die GELIEFERTEN Tasks — ein alter, noch offener Claim
        // würde die Spanne sonst strecken und die Velocity künstlich drücken.
        const claimTs = delivered.map((t) => t.claimed_at).filter(Boolean).map((d) => new Date(d).getTime());
        const mergeTs = delivered
            .map((t) => t.merged_at || t.pr_stats?.merged_at)
            .filter(Boolean)
            .map((d) => new Date(d).getTime());
        const spanDays =
            claimTs.length && mergeTs.length
                ? Math.max(0, (Math.max(...mergeTs) - Math.min(...claimTs)) / DAY_MS)
                : null;
        // Erst ab zwei gelieferten Tasks und einem Tag Spanne — aus einem einzigen
        // Task in wenigen Stunden ließe sich sonst eine Fantasie-Wochenleistung
        // hochrechnen.
        const spPerWeek =
            spanDays !== null && spanDays >= 1 && deliveredSp > 0 && mergeTs.length >= 2
                ? (deliveredSp / spanDays) * 7
                : null;
        const lastDelivery = mergeTs.length ? new Date(Math.max(...mergeTs)).toISOString() : null;

        // Schätzgüte: Ist-Dateien (pr_stats) gegen Schätzung (affected_files).
        const deviations = [];
        for (const t of delivered) {
            const est = t.affected_files != null ? Number(t.affected_files) : null;
            const actual = t.pr_stats?.changed_files;
            if (!est || est <= 0 || actual == null) continue;
            deviations.push(Math.round(((actual - est) / est) * 100));
        }
        const accuracyHits = deviations.filter((d) => Math.abs(d) <= 25).length;
        const accuracyTotal = deviations.length;
        const accuracyPct = accuracyTotal ? Math.round((accuracyHits / accuracyTotal) * 100) : null;
        const medianDeviation = median(deviations);

        // --- Qualität -----------------------------------------------------------
        // WICHTIG: last_review_recommendation, pr_ci_failed, pr_unresolved_threads
        // und das Vorhandensein eines Concerns sind MOMENTAUFNAHMEN. Wird nach
        // „Änderungen erbeten" nachgearbeitet und freigegeben, steht dort APPROVE;
        // ein behobener CI-Fehler, ein aufgelöster Kommentar-Thread und ein
        // aufgelöster Concern (die Zeile wird gelöscht) verschwinden ebenso. Die
        // Labels sagen das deshalb ausdrücklich („aktuell …", „offene …").
        const reviewed = own.filter((t) => t.last_review_recommendation);
        const requestChanges = reviewed.filter((t) => t.last_review_recommendation === 'REQUEST_CHANGES').length;
        const approved = reviewed.filter((t) => t.last_review_recommendation === 'APPROVE').length;
        const firstPassPct = reviewed.length ? Math.round((approved / reviewed.length) * 100) : null;

        // Der VERLAUF dagegen kommt aus dem Audit-Log (rework_count, gesetzt vom
        // Board-Endpunkt): wie oft je Änderungen erbeten wurden. Überlebt Approve.
        const reworkTasks = own.filter((t) => Number(t.rework_count || 0) > 0).length;
        const reworkMultiple = own.filter((t) => Number(t.rework_count || 0) > 1).length;
        const reworkTotal = sum(own, (t) => Number(t.rework_count || 0));

        // CI und Review-Threads stehen nur, wenn der PR-Status je gesynct wurde —
        // sonst wäre „0" kein Befund, sondern fehlende Messung.
        const prSynced = own.filter((t) => t.pr_status_synced_at).length;
        const ciFailed = prSynced ? own.filter((t) => Number(t.pr_ci_failed || 0) > 0).length : null;
        const openThreads = prSynced ? sum(own, (t) => Number(t.pr_unresolved_threads || 0)) : null;

        const concerns = own.filter((t) => t.concern).length;
        const exceptions = own.filter((t) => kindOf(t) === 'exception').length;

        // criticality ist ein optionales Pflegefeld: ohne einen einzigen gesetzten
        // Wert sagt „0 kritische Tasks" nichts über Risiko, nur über die Pflege.
        const criticalityKnown = own.filter((t) => t.criticality).length;
        const critical = criticalityKnown
            ? own.filter((t) => t.criticality === 'high' || t.criticality === 'critical').length
            : null;

        // Umfang: Ist-Kennzahlen der PRs + geplante Tokens.
        const prTasks = own.filter((t) => t.pr_stats);
        const files = sum(prTasks, (t) => Number(t.pr_stats.changed_files || 0));
        const additions = sum(prTasks, (t) => Number(t.pr_stats.additions || 0));
        const deletions = sum(prTasks, (t) => Number(t.pr_stats.deletions || 0));
        const commits = sum(prTasks, (t) => Number(t.pr_stats.commits || 0));
        const tokens = sum(own, tokensOf);
        const manDays = sum(own, manDaysOf);
        const unlocks = sum(open, (t) => Number(t.unlocks || 0));

        // WIP: offene, geclaimte Tasks — plus das Alter des ältesten Claims, damit
        // liegen gebliebene Arbeit auffällt.
        const openClaimTs = open.map((t) => t.claimed_at).filter(Boolean).map((d) => new Date(d).getTime());
        const oldestClaimDays = openClaimTs.length ? (Date.now() - Math.min(...openClaimTs)) / DAY_MS : null;

        const openTasks = open
            .map((t) => {
                const st = statusOf(t);
                const claimedDays = t.claimed_at ? (Date.now() - new Date(t.claimed_at).getTime()) / DAY_MS : null;
                return {
                    id: t.id,
                    name: t.name,
                    summary: t.summary,
                    url: taskUrl(t.id),
                    statusLabel: st?.label ?? t.display_status_label ?? t.display_status,
                    statusBadge: st?.badge ?? PILL.gray,
                    sp: spOf(t) || null,
                    ageDays: claimedDays,
                    ageLabel: formatDuration(claimedDays, s),
                    prUrl: t.pr_url || null,
                    prNumber: t.pr_number || null,
                    ciFailed: Number(t.pr_ci_failed || 0) > 0,
                    openThreads: Number(t.pr_unresolved_threads || 0),
                    hasConcern: !!t.concern,
                    isException: kindOf(t) === 'exception',
                };
            })
            .sort((a, b) => (b.ageDays ?? -1) - (a.ageDays ?? -1));

        // Verweildauern der eigenen Tasks (Verlauf aus dem Protokoll, Rückläufer
        // zählen einzeln) — Grundlage des Balkens in der Tabellenspalte.
        const durations = aggregateDurations(own, lookup);

        // Review-Beitrag (als Reviewer, unabhängig davon, wer geclaimt hat).
        const reviewsGiven = p.reviewed.length;
        const reviewedAuthors = new Set(
            p.reviewed.map((t) => t.claimed_by_id).filter((id) => id != null && id !== p.id),
        ).size;
        const reviewTs = p.reviewed
            .map((t) => t.last_reviewed_at)
            .filter(Boolean)
            .map((d) => new Date(d).getTime());
        const lastReview = reviewTs.length ? new Date(Math.max(...reviewTs)).toISOString() : null;

        const accClass = shareClass(accuracyPct);
        const fpClass = shareClass(firstPassPct);

        return {
            id: p.id,
            name: p.name,
            initials: initialsOf(p.name),
            avatarClass: avatarClass(p.id),

            tasksTotal: own.length,
            deliveredCount: delivered.length,
            deliveredSp,
            openCount: open.length,
            openSp,
            totalSp,
            deliveredSharePct: own.length ? Math.round((delivered.length / own.length) * 100) : 0,

            cycleMedian,
            cycleMedianLabel: formatDuration(cycleMedian, s),
            cycleAvgLabel: formatDuration(cycleAvg, s),
            timePerSpLabel: formatDuration(timePerSp, s),
            spPerWeek,
            spPerWeekLabel: spPerWeek !== null ? deTrim(spPerWeek) : null,
            lastDelivery,

            accuracyHits,
            accuracyTotal,
            accuracyPct,
            accuracyClass: accClass,
            accuracyPill: PILL[accClass],
            accuracyBar: BAR[accClass],
            medianDeviation,
            medianDeviationLabel: deviationLabel(medianDeviation),

            reviewedCount: reviewed.length,
            requestChanges,
            approved,
            firstPassPct,
            firstPassPill: PILL[fpClass],
            reworkTasks,
            reworkMultiple,
            reworkTotal,
            prSynced,
            ciFailed,
            openThreads,
            concerns,
            exceptions,
            critical,
            criticalityKnown,

            files,
            additions,
            deletions,
            commits,
            tokens,
            tokensLabel: formatTokens(tokens),
            manDays,
            unlocks,

            wip: open.length,
            oldestClaimDays,
            oldestClaimLabel: formatDuration(oldestClaimDays, s),
            openTasks,

            reviewsGiven,
            reviewedAuthors,
            lastReview,

            durations: durations.segments.length ? durations : null,

            // Sortierschlüssel (fehlende Werte sortieren nach unten).
            sortName: p.name.toLocaleLowerCase(),
            sortDeliveredSp: deliveredSp,
            sortDelivered: delivered.length,
            sortCycle: cycleMedian ?? Number.POSITIVE_INFINITY,
            sortAccuracy: accuracyPct ?? -1,
            sortOpen: open.length,
            sortReviews: reviewsGiven,
            sortDuration: durations.medianTaskDays ?? -1,
        };
    });

    // --- Team-Aggregate -------------------------------------------------------
    // Noch offene Tasks, die niemandem zugeordnet sind — der Rest der Arbeit, der
    // in keiner Personenzeile auftaucht.
    const unassignedTasks = tasks.filter((t) => t.claimed_by_id == null && !isDone(t));

    const teamCycles = rows.flatMap((r) => (r.cycleMedian !== null ? [r.cycleMedian] : []));
    const teamCycleMedian = median(teamCycles);
    const teamAccHits = rows.reduce((a, r) => a + r.accuracyHits, 0);
    const teamAccTotal = rows.reduce((a, r) => a + r.accuracyTotal, 0);

    const team = {
        people: rows.length,
        peopleWithOpen: rows.filter((r) => r.openCount > 0).length,
        deliveredCount: rows.reduce((a, r) => a + r.deliveredCount, 0),
        deliveredSp: rows.reduce((a, r) => a + r.deliveredSp, 0),
        cycleMedianLabel: formatDuration(teamCycleMedian, s),
        accuracyHits: teamAccHits,
        accuracyTotal: teamAccTotal,
        accuracyPct: teamAccTotal ? Math.round((teamAccHits / teamAccTotal) * 100) : null,
        reviewsGiven: rows.reduce((a, r) => a + r.reviewsGiven, 0),
        reworkTasks: rows.reduce((a, r) => a + r.reworkTasks, 0),
        tasksTotal: rows.reduce((a, r) => a + r.tasksTotal, 0),
    };
    team.accuracyClass = shareClass(team.accuracyPct);

    const unassigned = {
        count: unassignedTasks.length,
        sp: unassignedTasks.reduce((a, t) => a + spOf(t), 0),
    };
    unassigned.note = transChoice(s.unassignedSub, unassigned.count, { sp: unassigned.sp });

    // Skalen für die Vergleichsbalken (0 → kein Balken statt Division durch 0).
    const scales = {
        maxSp: Math.max(1, ...rows.map((r) => r.totalSp)),
        maxCycle: Math.max(...rows.map((r) => r.cycleMedian ?? 0), 0.0001),
        // Balkenbreite der Verweildauer-Spalte trägt den Median je Task, der
        // Maßstab entsprechend den größten dieser Mediane.
        maxDuration: Math.max(0, ...rows.map((r) => r.durations?.medianTaskDays ?? 0)),
    };

    // Projektweite Verweildauer je Status — dasselbe Panel wie in der
    // persönlichen Statistik, hier über ALLE Tasks des Projekts (auch die ohne
    // Zuordnung, denn die Zeit ist trotzdem vergangen).
    const statusDurations = statusDurationRows(tasks, lookup);

    return { people: rows, team, unassigned, scales, statusDurations };
}
