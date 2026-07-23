// Clientseitige Ableitung der PR-Sequenz aus den rohen Store-Daten. Portiert
// ProjectPrSequenceController + status/pr-sequence.blade.php (Sequenznummer,
// Abhängigkeits-Chips, Reihenfolge aktiv→Flaschenhals→Sequenz, Blockiert-Einklappen,
// erledigte PRs, Filter-Zähler, Kennzahlen). So lebt auch die PR-Sequenz aus
// derselben einmalig geladenen Datenbasis und aktualisiert sich live (0 Calls).

function formatTokens(tokens) {
    if (tokens === null || tokens === undefined) return '—';
    return tokens >= 1_000_000
        ? (tokens / 1_000_000).toFixed(1).replace('.', ',') + 'M'
        : Math.round(tokens / 1000) + 'k';
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

// Filter-Kategorie der Chips (an der Aktions-Rolle/Kind ausgerichtet).
function categoryOf(role, kind) {
    switch (role) {
        case 'PICKABLE':
            return 'pickable';
        case 'BLOCKED':
            return 'blocked';
        case 'CONCERNED':
            return 'concerned';
        case 'CLAIMED':
            return 'claimed';
        default:
            return kind === 'exception' ? 'concerned' : 'other';
    }
}

/**
 * @param {object} args
 * @param {Array}  args.tasks
 * @param {object} args.statusConfig  { statuses, roleKey }
 * @param {string} args.taskUrlTemplate
 * @param {string} args.locale
 */
export function deriveSequence({ tasks, statusConfig, taskUrlTemplate, locale }) {
    const roleKey = statusConfig?.roleKey || {};
    const byKey = new Map((statusConfig?.statuses || []).map((s) => [s.key, s]));
    const byId = new Map(tasks.map((t) => [t.id, t]));

    const url = (id) => (taskUrlTemplate ? taskUrlTemplate.replace('__ID__', String(id)) : '#');
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;
    const statusOf = (t) => byKey.get(displayKeyOf(t));
    const isDone = (t) => !!statusOf(t)?.counts_as_done;
    const isDelivered = (t) => t.pr_number != null || isDone(t);
    const sp = (t) => Number(t.effort?.story_points || 0);

    // Direkte Abhängige je Task (über den vollen Satz) — Flaschenhals ab ≥ 3.
    const directDeps = {};
    for (const t of tasks) {
        for (const pre of t.prerequisites || []) {
            directDeps[pre.id] = (directDeps[pre.id] || 0) + 1;
        }
    }

    // Sequenznummer über den nicht-gemergten Satz in Board-Reihenfolge (nach id).
    const ordered = [...tasks].sort((a, b) => a.id - b.id);
    const nonMerged = ordered.filter((t) => statusOf(t)?.role !== 'MERGED');
    const seq = {};
    nonMerged.forEach((t, i) => {
        seq[t.id] = i + 1;
    });

    const kindOf = (t) => statusOf(t)?.kind;
    const isActive = (t) =>
        ['active', 'review'].includes(kindOf(t)) && statusOf(t)?.role !== 'CLAIMED';
    const dependentsOf = (t) => directDeps[t.id] || 0;
    const isBottleneck = (t) => dependentsOf(t) >= 3;

    const completedTasks = nonMerged.filter((t) => kindOf(t) === 'done');
    const openTasks = nonMerged.filter((t) => kindOf(t) !== 'done');

    const buildRow = (t) => {
        const st = statusOf(t);
        const role = st?.role;
        const depItems = (t.prerequisites || []).map((p) => {
            const parent = byId.get(p.id);
            return { name: p.name, met: parent ? isDelivered(parent) : false };
        });
        const files = t.affected_files ?? null;
        const spv = sp(t);
        const reason = t.concern?.summary || t.concern?.blocker || null;

        return {
            id: t.id,
            cat: categoryOf(role, st?.kind),
            role,
            isActive: isActive(t),
            isBottleneck: isBottleneck(t),
            dependents: dependentsOf(t),
            big: spv >= 10 || (files ?? 0) >= 30 ? { sp: spv, files, isLargest: false } : null,
            name: t.name,
            url: url(t.id),
            claudeHref: 'claudetask:' + encodeURIComponent('/L2LR ' + t.name),
            pr: t.pr_number,
            prUrl: t.pr_url,
            phaseShort: String(t.phase?.name ?? '—').split(' · ')[0],
            seq: seq[t.id],
            statusLabel: st?.label,
            rail: st?.bar,
            badge: st?.badge,
            summary: t.summary,
            claimer: role === 'CLAIMED' ? t.claimed_by || null : null,
            claimedAgo: role === 'CLAIMED' && t.claimed_at ? relativeTime(t.claimed_at, locale) : null,
            reason: role === 'CONCERNED' && reason && reason !== t.summary ? reason : null,
            depOpen: depItems.filter((d) => !d.met).length,
            depItems,
            sp: spv,
            tokens: formatTokens(t.effort?.tokens ?? null),
            files,
        };
    };

    const maxSp = openTasks.reduce((m, t) => Math.max(m, sp(t)), 0);
    const rows = openTasks.map(buildRow);
    // „größter PR"-Markierung, sobald SP ≥ 10 und = Maximum.
    for (const r of rows) {
        if (r.big && r.big.sp >= 10 && r.big.sp === maxSp) r.big.isLargest = true;
    }

    // Reihenfolge: aktiv zuerst, dann Flaschenhälse (viele Abhängige zuerst),
    // dann Rest in Sequenz-Reihenfolge.
    const sortKey = (r) =>
        [
            r.isActive ? 0 : r.isBottleneck ? 1 : 2,
            r.isBottleneck && !r.isActive ? Math.max(0, 999 - r.dependents) : 0,
            Math.min(9999, r.seq || 0),
        ];
    rows.sort((a, b) => {
        const ka = sortKey(a);
        const kb = sortKey(b);
        for (let i = 0; i < ka.length; i++) if (ka[i] !== kb[i]) return ka[i] - kb[i];
        return 0;
    });

    // Blockierte PRs ohne Flaschenhals-Status ab > 5 hinter einen Expander.
    const blockedPlain = rows.filter((r) => r.role === 'BLOCKED' && !r.isBottleneck && !r.isActive);
    const collapseBlocked = blockedPlain.length > 5;
    const blockedIds = new Set(collapseBlocked ? blockedPlain.map((r) => r.id) : []);
    const main = collapseBlocked ? rows.filter((r) => !blockedIds.has(r.id)) : rows;
    const blockedCollapsed = collapseBlocked ? blockedPlain : [];

    const countRole = (roleVal) => openTasks.filter((t) => statusOf(t)?.role === roleVal).length;
    const counts = {
        all: openTasks.length,
        pickable: countRole('PICKABLE'),
        blocked: countRole('BLOCKED'),
        concerned: countRole('CONCERNED'),
        claimed: countRole('CLAIMED'),
    };

    const totalSp = openTasks.reduce((a, t) => a + sp(t), 0);
    const criticalPath = openTasks
        .filter(isBottleneck)
        .sort((a, b) => seq[a.id] - seq[b.id])
        .map((t) => t.name)
        .join(' → ');

    return {
        counts,
        metrics: {
            openCount: counts.all,
            totalSp,
            blockedCount: counts.blocked,
            criticalPath: criticalPath !== '' ? criticalPath : '—',
        },
        main,
        blockedCollapsed,
        collapseBlocked,
        blockedPlainCount: blockedPlain.length,
        completed: completedTasks.map((t) => ({ name: t.name, url: url(t.id), pr: t.pr_number })),
    };
}
