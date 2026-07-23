// Baut die Projektkarten clientseitig aus der kompakten Aggregat-Übersicht
// (/api/projects/overview: Zähler/SP + Segment-Buckets je Projekt) + der org-weiten
// status-config (Styling/Reihenfolge/Labels). Kein Laden/Aggregieren aller Tasks
// im Browser. Beschreibung wird als Plaintext ausgegeben (kein Markdown).

import { interpolate } from '../summary/i18n.js';

const BADGE = {
    nicht_gestartet: 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
    in_arbeit: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
    fast_fertig: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
    completed: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
};
const BAR = {
    nicht_gestartet: 'bg-gray-300',
    in_arbeit: 'bg-indigo-600',
    fast_fertig: 'bg-green-500',
    completed: 'bg-blue-500',
};
const AVATAR = ['bg-emerald-600', 'bg-indigo-600', 'bg-rose-600', 'bg-amber-600', 'bg-sky-600', 'bg-fuchsia-600'];

const deComma = (v) => Number(v || 0).toFixed(1).replace('.', ',');

function initialsOf(name) {
    return String(name || '?')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');
}

export function deriveProjectCards({ projects, statusConfig, currentUserId, strings, locale }) {
    const ordered = statusConfig?.statuses || [];

    const categoryLabels = {
        nicht_gestartet: strings.notStarted,
        in_arbeit: strings.inProgress,
        fast_fertig: strings.almostDone,
        completed: strings.completed,
    };

    // Gestapelte Segmente aus den Buckets ({key,count,sp}) in Org-Reihenfolge,
    // SP-gewichtet (ersatzweise nach Anzahl).
    const segmentsFor = (buckets) => {
        const byKey = new Map(buckets.map((b) => [b.key, b]));
        const totalSp = buckets.reduce((a, b) => a + (b.sp || 0), 0);
        const useSp = totalSp > 0;
        const totalWeight = Math.max(1, useSp ? totalSp : buckets.reduce((a, b) => a + (b.count || 0), 0));
        const segs = [];
        for (const st of ordered) {
            const b = byKey.get(st.key);
            if (!b || b.count === 0) continue;
            const weight = useSp ? b.sp : b.count;
            const width = Math.round((weight / totalWeight) * 1000) / 10;
            segs.push({
                label: st.label,
                count: b.count,
                bar: st.bar,
                text: st.text,
                badge: st.badge,
                width,
                pctLabel: width.toFixed(1).replace('.', ','),
            });
        }
        return segs;
    };

    const cards = projects.map((p) => {
        const totalSp = p.total_sp || 0;
        const doneSp = p.done_sp || 0;
        const pct = totalSp > 0 ? (doneSp / totalSp) * 100 : 0;
        const isCompleted = p.completed_at != null;
        const category = isCompleted
            ? 'completed'
            : pct <= 0
                ? 'nicht_gestartet'
                : pct >= 80
                    ? 'fast_fertig'
                    : 'in_arbeit';

        return {
            alias: p.alias,
            name: p.name,
            description: p.description || null,
            diagramUrl: `/projects/${encodeURIComponent(p.alias)}/diagram`,
            category,
            categoryLabel: categoryLabels[category],
            badgeClass: BADGE[category],
            barClass: BAR[category],
            pct: Math.round(pct * 10) / 10,
            pctLabel: deComma(pct),
            segments: segmentsFor(p.segments || []),
            ownerName: p.owner?.name ?? null,
            initials: initialsOf(p.owner?.name),
            avatarClass: AVATAR[(p.created_by_id ?? 0) % AVATAR.length],
            teams: p.teams || [],
            tasksCount: p.tasks_count || 0,
            doneCount: p.done_count || 0,
            tasksLabel: interpolate(strings.countTasks, { count: p.tasks_count || 0 }),
            sp: totalSp,
            mine: p.created_by_id === currentUserId,
            archived: p.archived_at != null,
            searchText: `${p.alias} ${p.name}`.toLowerCase(),
        };
    });

    // Kopfzeile: aktive (nicht archivierte) Projekte.
    const active = cards.filter((c) => !c.archived);
    const activeCount = active.length;
    const openTasks = active.reduce((a, c) => a + (c.tasksCount - c.doneCount), 0);
    const totalSp = active.reduce((a, c) => a + c.sp, 0);
    const nf = new Intl.NumberFormat(locale || 'de-DE');
    const summaryLine =
        `${activeCount} ${activeCount === 1 ? strings.projectSingular : strings.projectsPlural}` +
        ` · ${interpolate(strings.countOpenTasks, { count: nf.format(openTasks) })}` +
        ` · ${interpolate(strings.countStoryPoints, { count: nf.format(totalSp) })}`;

    return { cards, summaryLine };
}
