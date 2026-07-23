// Clientseitige Ableitung der Summary-Ansicht aus den rohen Store-Daten
// (Tasks + Phasen + Org-Status-Konfiguration). Portiert die Server-Logik aus
// ProjectSummaryController (kpis/phaseRows/pickableCards/phaseBlockers) und
// App\Support\StatusSegments, damit Board und Summary aus DERSELBEN einmalig
// geladenen Datenbasis leben und nur noch partiell nachgeladen werden.
//
// Alle Aggregate sind reine Funktionen der Eingaben — keine Netzwerkzugriffe.

import { interpolate, transChoice } from './i18n.js';

// --- kleine Formatierungs-Helfer (Spiegel der PHP-Seite) -----------------------

// Deutsche Dezimalzahl mit getrimmten Nullen/Komma: 2.0 → "2", 2.5 → "2,5".
function deTrim(value) {
    return Number(value || 0)
        .toFixed(1)
        .replace('.', ',')
        .replace(/,0$/, '');
}

// Token-Kurzform wie TaskBoardService::formatTokens: null → "—", ≥1M → "x,yM",
// sonst gerundete Tausender "xk".
function formatTokens(tokens) {
    if (tokens === null || tokens === undefined) return '—';
    return tokens >= 1_000_000
        ? (tokens / 1_000_000).toFixed(1).replace('.', ',') + 'M'
        : Math.round(tokens / 1000) + 'k';
}

function round1(value) {
    return Math.round(value * 10) / 10;
}

function pct(part, total) {
    return total ? Math.round((part / total) * 100) : 0;
}

// ISO-8601-Kalenderwoche + zugehöriges ISO-Jahr (Spiegel von Carbon isoWeek()).
function isoWeek(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const day = d.getUTCDay() || 7; // Mo=1 … So=7
    d.setUTCDate(d.getUTCDate() + 4 - day); // auf den Donnerstag der Woche
    const year = d.getUTCFullYear();
    const yearStart = new Date(Date.UTC(year, 0, 1));
    const week = Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
    return { week, year };
}

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
        if (Math.abs(diffSec) >= secs || unit === 'second') {
            return rtf.format(Math.round(diffSec / secs), unit);
        }
    }
    return '';
}

// --- Haupt-Ableitung -----------------------------------------------------------

/**
 * @param {object} args
 * @param {Array}  args.tasks           rohe API-Tasks (TaskResource fields=full)
 * @param {Array}  args.phases          [{id, name, position}]
 * @param {object} args.statusConfig    { statuses: [...geordnet], roleKey }
 * @param {object} args.strings         Label-/Template-Strings (teils mit :platzhaltern)
 * @param {string} args.taskUrlTemplate URL-Template mit __ID__-Platzhalter
 * @param {string} args.locale          z. B. "de" | "en" (für relative Zeit)
 * @returns {{kpis: object, rows: Array, pickable: Array, pickableCount: number}}
 */
export function deriveSummary({ tasks, phases, statusConfig, strings, taskUrlTemplate, locale }) {
    const s = strings || {};
    const roleKey = statusConfig?.roleKey || {};
    const orderedStatuses = statusConfig?.statuses || [];
    const byKey = new Map(orderedStatuses.map((st) => [st.key, st]));
    const byId = new Map(tasks.map((t) => [t.id, t]));

    const taskUrl = (id) => (taskUrlTemplate ? taskUrlTemplate.replace('__ID__', String(id)) : '#');

    // Board-Anzeigeschlüssel eines Tasks; Waiting-Tasks (display_status = Rollen-
    // name BLOCKED/PICKABLE) werden wie im BoardPresenter auf den Org-Key gemappt.
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;
    const isDone = (t) => !!byKey.get(displayKeyOf(t))?.counts_as_done;
    const isDelivered = (t) => t.pr_number != null || isDone(t);

    const sp = (t) => Number(t.effort?.story_points || 0);
    const manDays = (t) => Number(t.effort?.man_days || 0);
    const tokensOf = (t) => Number(t.effort?.tokens || 0);
    const filesOf = (t) => Number(t.affected_files || 0);

    return {
        kpis: deriveKpis(),
        rows: derivePhaseRows(),
        pickable: derivePickable(),
        pickableCount: tasks.filter((t) => t.pickable).length,
    };

    // Gestapelte Status-Balken-Segmente einer Task-Menge (SP-gewichtet, sonst
    // nach Anzahl). Reihenfolge = orderedStatuses (fertig → … → Ausnahme).
    function segmentsFor(phaseTasks) {
        const count = {};
        const spByKey = {};
        for (const t of phaseTasks) {
            const k = displayKeyOf(t);
            count[k] = (count[k] || 0) + 1;
            spByKey[k] = (spByKey[k] || 0) + sp(t);
        }
        const totalSp = Object.values(spByKey).reduce((a, b) => a + b, 0);
        const useSp = totalSp > 0;
        const totalWeight = Math.max(
            1,
            useSp ? totalSp : Object.values(count).reduce((a, b) => a + b, 0),
        );

        const segs = [];
        for (const st of orderedStatuses) {
            if ((count[st.key] || 0) === 0) continue;
            const weight = useSp ? spByKey[st.key] : count[st.key];
            const width = round1((weight / totalWeight) * 100);
            segs.push({
                label: st.label,
                count: count[st.key],
                bar: st.bar,
                text: st.text,
                badge: st.badge,
                width,
                pctLabel: width.toFixed(1).replace('.', ','),
            });
        }
        return segs;
    }

    function deriveKpis() {
        const total = tasks.length;
        const done = tasks.filter(isDone);
        const remaining = tasks.filter((t) => !isDone(t));

        const sum = (list, fn) => list.reduce((a, t) => a + fn(t), 0);
        const totalSp = sum(tasks, sp);
        const doneSp = sum(done, sp);
        const totalFiles = sum(tasks, filesOf);
        const doneFiles = sum(done, filesOf);
        const totalTokens = sum(tasks, tokensOf);
        const doneTokens = sum(done, tokensOf);

        const tiles = [
            {
                title: s.progress,
                pct: pct(done.length, total),
                sub: interpolate(s.doneOfTotalPrs, { done: done.length, total }),
            },
            {
                title: s.storyPoints,
                pct: pct(doneSp, totalSp),
                sub: interpolate(s.doneOfTotalSp, { done: doneSp, total: totalSp }),
            },
            {
                title: s.files,
                pct: pct(doneFiles, totalFiles),
                sub: interpolate(s.doneOfTotalFiles, { done: doneFiles, total: totalFiles }),
            },
            {
                title: s.tokens,
                pct: pct(doneTokens, totalTokens),
                sub: interpolate(s.doneOfTotalTokens, {
                    done: formatTokens(doneTokens),
                    total: formatTokens(totalTokens),
                }),
            },
        ];

        // Velocity + letzter Merge brauchen echte Merge-Zeitstempel.
        const merged = tasks
            .filter((t) => t.status === 'MERGED' && t.merged_at)
            .sort((a, b) => new Date(a.merged_at) - new Date(b.merged_at));

        let velocity = null;
        let lastMerge = null;

        if (merged.length) {
            const last = merged[merged.length - 1];
            lastMerge = {
                title: s.lastMergeTitle,
                when: relativeTime(last.merged_at, locale),
                pr: last.pr_number ? '#' + last.pr_number : last.name,
            };

            const days = (Date.now() - new Date(merged[0].merged_at).getTime()) / 86400000;
            const weeks = Math.max(1 / 7, days / 7);
            const rate = sum(merged, sp) / weeks;

            if (rate > 0) {
                const eta = new Date(Date.now() + Math.ceil(sum(remaining, sp) / rate) * 7 * 86400000);
                const iso = isoWeek(eta);
                velocity = {
                    title: s.velocityTitle,
                    rate: deTrim(round1(rate)),
                    unit: s.spWk,
                    sub: interpolate(s.forecastEta, { eta: `KW ${iso.week}/${iso.year}` }),
                };
            }
        }

        return { tiles, velocity, lastMerge };
    }

    function derivePhaseRows() {
        const sorted = [...phases].sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

        return sorted.map((phase) => {
            const pt = tasks.filter((t) => t.phase_id === phase.id);
            const remaining = pt.filter((t) => !isDone(t)); // Fortschritt: nur erledigt/gemergt
            const doneCount = pt.length - remaining.length;

            const sum = (list, fn) => list.reduce((a, t) => a + fn(t), 0);

            return {
                phase: phase.name,
                done: doneCount,
                total: pt.length,
                pct: pct(doneCount, pt.length),
                open_prs: remaining.map((t) => {
                    const st = byKey.get(displayKeyOf(t));
                    return {
                        name: t.name,
                        url: taskUrl(t.id),
                        badge: st?.badge || '',
                        label: st?.label || displayKeyOf(t),
                        summary: t.summary,
                    };
                }),
                open_prs_label: interpolate(s.openPrsCount, { count: remaining.length }),
                statuses: segmentsFor(pt),
                pt: {
                    remaining: deTrim(sum(remaining, manDays)),
                    total: deTrim(sum(pt, manDays)),
                },
                files: {
                    remaining: sum(remaining, filesOf),
                    total: sum(pt, filesOf),
                },
                tokens: {
                    remaining: formatTokens(sum(remaining, tokensOf) || null),
                    total: formatTokens(sum(pt, tokensOf) || null),
                },
                blocked_by: phaseBlockers(phase, pt).map((blocker) =>
                    interpolate(s.blockedByBlocker, { blocker }),
                ),
            };
        });
    }

    // Kurznamen der Phasen, die unerfüllte Voraussetzungen der (noch nicht
    // gelieferten) Tasks dieser Phase halten — also die blockierenden Phasen.
    function phaseBlockers(phase, phaseTasks) {
        const remaining = phaseTasks.filter((t) => !isDelivered(t));
        const blockers = {}; // position → Kurzname
        for (const task of remaining) {
            for (const pre of task.prerequisites || []) {
                const parent = byId.get(pre.id);
                if (!parent || isDelivered(parent)) continue;
                if (parent.phase_id !== phase.id) {
                    const pos = parent.phase?.position ?? 9999;
                    blockers[pos] = (parent.phase?.name || '').split(' ')[0];
                }
            }
        }
        return Object.keys(blockers)
            .map(Number)
            .sort((a, b) => a - b)
            .map((k) => blockers[k]);
    }

    function derivePickable() {
        return tasks
            .filter((t) => t.pickable)
            .sort((a, b) => (b.unlocks || 0) - (a.unlocks || 0))
            .map((t, i) => ({
                name: t.name,
                url: taskUrl(t.id),
                sp: sp(t),
                tokens: formatTokens(tokensOf(t) || null),
                files: t.affected_files ?? '—',
                unlocks: t.unlocks || 0,
                unlocksLabel:
                    (t.unlocks || 0) > 0 ? transChoice(s.unlocksFollowupPrs, t.unlocks) : null,
                summary: t.summary,
                best: i === 0,
            }));
    }
}
