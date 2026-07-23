// Clientseitige Ableitung der Kalibrierung aus dem geteilten Tasks-Store. Möglich,
// seit TaskResource pro Task die aggregierten PR-Ist-Kennzahlen (pr_stats:
// changed_files/additions/deletions/commits + merged_at/updated_at) mitliefert —
// vorher fehlten genau diese GitHub-Ist-Daten, weshalb es einen eigenen Endpunkt gab.
// Portiert App\Support\CalibrationPresenter. Reine Funktion, keine Netzzugriffe.

import { interpolate } from '../summary/i18n.js';

function relativeTime(dateStr, locale) {
    const rtf = new Intl.RelativeTimeFormat(locale || 'de', { numeric: 'auto' });
    const diffSec = (new Date(dateStr).getTime() - Date.now()) / 1000;
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

const deComma = (v, digits = 1) => Number(v || 0).toFixed(digits).replace('.', ',');

function dateShort(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    const p = (n) => String(n).padStart(2, '0');
    return `${p(d.getDate())}.${p(d.getMonth() + 1)}.`;
}

function deviationClass(pct) {
    if (pct === null) return 'gray';
    const a = Math.abs(pct);
    return a <= 25 ? 'green' : a <= 50 ? 'amber' : 'red';
}

function deviationLabel(pct) {
    if (pct === null) return null;
    if (pct === 0) return '±0 %';
    return pct > 0 ? `+${pct} %` : `${pct} %`;
}

const PILL = {
    green: 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    gray: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
};
const BAR = { green: 'bg-green-400', amber: 'bg-amber-400', red: 'bg-red-300', gray: 'bg-gray-200' };

function median(values) {
    if (!values.length) return null;
    const v = [...values].sort((a, b) => a - b);
    const n = v.length;
    const mid = Math.floor(n / 2);
    return n % 2 === 1 ? v[mid] : Math.round((v[mid - 1] + v[mid]) / 2);
}

function formatDuration(days, strings) {
    const minutes = days * 24 * 60;
    if (minutes < 60) return `${Math.round(Math.max(0, minutes))} ${strings.unitMin}`;
    const hours = minutes / 60;
    if (hours < 24) return `${deComma(hours)} ${strings.unitHours}`;
    return `${deComma(days)} ${strings.unitDays}`;
}

export function deriveCalibration({ tasks, statusConfig, strings, taskUrlTemplate, locale }) {
    const roleKey = statusConfig?.roleKey || {};
    const byKey = new Map((statusConfig?.statuses || []).map((s) => [s.key, s]));
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;
    const roleOf = (t) => byKey.get(displayKeyOf(t))?.role;
    const url = (id) => (taskUrlTemplate ? taskUrlTemplate.replace('__ID__', String(id)) : '#');

    const merged = tasks.filter((t) => roleOf(t) === 'MERGED' && t.pr_stats);

    const rows = merged
        .map((t) => {
            const prs = t.pr_stats;
            const filesActual = prs.changed_files;
            const filesEstimated = t.affected_files != null ? Number(t.affected_files) : null;
            const deviationPct =
                filesEstimated !== null && filesEstimated > 0
                    ? Math.round(((filesActual - filesEstimated) / filesEstimated) * 100)
                    : null;
            const mergedAt = prs.merged_at || null;
            const claimedAt = t.claimed_at || null;
            const cycleDays =
                claimedAt && mergedAt
                    ? Math.abs(new Date(mergedAt).getTime() - new Date(claimedAt).getTime()) / 86400000
                    : null;
            const sp = Number(t.effort?.story_points || 0);
            const timePerSpDays = cycleDays !== null && sp ? cycleDays / sp : null;

            return {
                name: t.name,
                url: url(t.id),
                storyPoints: t.effort?.story_points ?? null,
                claimedAt,
                mergedAt,
                mergedTs: mergedAt ? new Date(mergedAt).getTime() : 0,
                dateShort: dateShort(mergedAt),
                filesEstimated,
                filesActual,
                deviationPct,
                deviationLabel: deviationLabel(deviationPct),
                deviationClass: deviationClass(deviationPct),
                timePerSpDays,
                timePerSpLabel: timePerSpDays !== null ? formatDuration(timePerSpDays, strings) : null,
                additions: prs.additions,
                deletions: prs.deletions,
                commits: prs.commits,
                durationDays: cycleDays,
                updatedAt: prs.updated_at || null,
            };
        })
        .sort((a, b) => b.mergedTs - a.mergedTs);

    const withDeviation = rows.filter((r) => r.deviationPct !== null);

    // Zeilen-Payload (filter-/sortierbar in der View).
    const rowData = rows.map((r) => {
        const hasEstimate = r.deviationPct !== null;
        const abs = hasEstimate ? Math.abs(r.deviationPct) : null;
        return {
            name: r.name,
            url: r.url,
            dateShort: r.dateShort,
            meta: `${r.commits} Commits · +${r.additions}/−${r.deletions}`,
            sp: r.storyPoints,
            filesEstimated: r.filesEstimated ?? null,
            filesActual: r.filesActual,
            hasEstimate,
            deviationLabel: r.deviationLabel,
            pillClass: PILL[r.deviationClass],
            barClass: BAR[r.deviationClass],
            barWidth: abs !== null ? Math.min(100, abs) : 0,
            timePerSp: r.timePerSpLabel ?? '—',
            isOutlier: hasEstimate && abs > 50,
            sortDev: abs ?? -1,
            sortSp: Number(r.storyPoints ?? -1),
            sortDate: r.mergedTs,
            sortTime: r.timePerSpDays ?? -1,
        };
    });

    // KPIs
    const med = median(withDeviation.map((r) => r.deviationPct));
    const completedSp = rows.reduce((a, r) => a + Number(r.storyPoints || 0), 0);
    const claims = rows.map((r) => r.claimedAt).filter(Boolean).map((d) => new Date(d).getTime());
    const merges = rows.map((r) => r.mergedAt).filter(Boolean).map((d) => new Date(d).getTime());
    const firstClaim = claims.length ? Math.min(...claims) : null;
    const lastMerge = merges.length ? Math.max(...merges) : null;
    const spanDays = firstClaim && lastMerge ? Math.abs(lastMerge - firstClaim) / 86400000 : null;
    const spPerDay = spanDays !== null && spanDays > 0 && completedSp > 0 ? completedSp / spanDays : null;
    const daysPerSp = spanDays !== null && completedSp > 0 ? spanDays / completedSp : null;
    const withEstimate = withDeviation.length;
    const total = rows.length;
    const lastSyncTs = rows.map((r) => r.updatedAt).filter(Boolean).map((d) => new Date(d).getTime());
    const lastSync = lastSyncTs.length ? relativeTime(new Date(Math.max(...lastSyncTs)).toISOString(), locale) : null;

    const medianHint =
        med === null
            ? strings.medianHintNoEstimates
            : med > 5
                ? strings.medianHintTooSmall
                : med < -5
                    ? strings.medianHintTooLarge
                    : strings.medianHintOk;

    const kpis = {
        total,
        lastSync,
        median: med,
        medianLabel: deviationLabel(med),
        medianClass: deviationClass(med),
        medianHint,
        spPerDay,
        daysPerSpLabel: daysPerSp !== null ? formatDuration(daysPerSp, strings) : null,
        hits: withDeviation.filter((r) => Math.abs(r.deviationPct) <= 25).length,
        hitsTotal: withEstimate,
        withEstimate,
        noEstimate: total - withEstimate,
    };

    // Scatter
    const points = withDeviation.map((r) => ({
        x: r.filesEstimated,
        y: r.filesActual,
        hit: Math.abs(r.deviationPct) <= 25,
        name: r.name,
    }));
    let maxVal = 0;
    for (const p of points) maxVal = Math.max(maxVal, p.x, p.y);
    let axis = Math.max(10, Math.ceil(Math.max(maxVal, 10) / 10) * 10);
    if (maxVal === axis) axis += 10;
    const scatter = { points, axis };

    // Treffsicherheit nach zusammenhängenden SP-Bereichen
    const bySp = {};
    for (const r of withDeviation) {
        const sp = Number(r.storyPoints);
        if (sp <= 0) continue;
        (bySp[sp] ||= []).push(r);
    }
    const spVals = Object.keys(bySp).map(Number).sort((a, b) => a - b);
    const spAccuracy = [];
    if (spVals.length) {
        const ranges = [];
        let start = spVals[0];
        let prev = spVals[0];
        for (const v of spVals.slice(1)) {
            if (v === prev + 1) {
                prev = v;
                continue;
            }
            ranges.push([start, prev]);
            start = prev = v;
        }
        ranges.push([start, prev]);
        for (const [lo, hi] of ranges) {
            let group = [];
            for (let s = lo; s <= hi; s++) if (bySp[s]) group = group.concat(bySp[s]);
            const t = group.length;
            const hits = group.filter((r) => Math.abs(r.deviationPct) <= 25).length;
            spAccuracy.push({
                label: lo === hi ? `${lo} SP` : `${lo}–${hi} SP`,
                lo,
                hi,
                hits,
                total: t,
                pct: t ? Math.round((hits / t) * 100) : 0,
            });
        }
    }

    // Handlungsempfehlung: größte SP-Gruppe, die nie trifft.
    const bad = spAccuracy.filter((g) => g.total >= 1 && g.pct === 0 && g.lo >= 5).sort((a, b) => b.lo - a.lo);
    const tip = bad.length ? interpolate(strings.accuracyTip, { lo: bad[0].lo }) : null;

    // Nach SP gruppiert (mit Zykluszeit)
    const groupMap = {};
    for (const r of rows) {
        if (!r.storyPoints || r.durationDays === null) continue;
        (groupMap[r.storyPoints] ||= []).push(r);
    }
    const groups = Object.keys(groupMap)
        .map(Number)
        .sort((a, b) => a - b)
        .map((sp) => {
            const g = groupMap[sp];
            const avg = g.reduce((a, r) => a + r.durationDays, 0) / g.length;
            return {
                storyPoints: sp,
                avgDuration: Math.round(avg * 10) / 10,
                rows: [...g].sort((a, b) => b.mergedTs - a.mergedTs).map((r) => ({ name: r.name, url: r.url })),
            };
        });

    return { rowData, kpis, groups, scatter, spAccuracy, tip };
}
