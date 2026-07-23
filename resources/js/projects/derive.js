// Baut die Projektkarten clientseitig aus /api/projects (Projekt-Stammdaten) +
// /api/tasks (alle Tasks org-weit) + org-weiter status-config. Portiert die frühere
// serverseitige Kartenaufbereitung (Fortschritt, Kategorie, Status-Segmente).
// Beschreibung wird als Plaintext ausgegeben (kein Markdown).

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

export function deriveProjectCards({ projects, tasks, statusConfig, currentUserId, strings, locale }) {
    const roleKey = statusConfig?.roleKey || {};
    const ordered = statusConfig?.statuses || [];
    const byKey = new Map(ordered.map((s) => [s.key, s]));
    const displayKeyOf = (t) => roleKey[t.display_status] || t.display_status;
    const isDone = (t) => !!byKey.get(displayKeyOf(t))?.counts_as_done;
    const sp = (t) => Number(t.effort?.story_points || 0);

    // Tasks nach Projekt gruppieren.
    const tasksByProject = new Map();
    for (const t of tasks) {
        const pid = t.project_id;
        if (!tasksByProject.has(pid)) tasksByProject.set(pid, []);
        tasksByProject.get(pid).push(t);
    }

    const categoryLabels = {
        nicht_gestartet: strings.notStarted,
        in_arbeit: strings.inProgress,
        fast_fertig: strings.almostDone,
        completed: strings.completed,
    };

    const segmentsFor = (projTasks) => {
        const count = {};
        const spByKey = {};
        for (const t of projTasks) {
            const k = displayKeyOf(t);
            count[k] = (count[k] || 0) + 1;
            spByKey[k] = (spByKey[k] || 0) + sp(t);
        }
        const totalSp = Object.values(spByKey).reduce((a, b) => a + b, 0);
        const useSp = totalSp > 0;
        const totalWeight = Math.max(1, useSp ? totalSp : Object.values(count).reduce((a, b) => a + b, 0));
        const segs = [];
        for (const st of ordered) {
            if ((count[st.key] || 0) === 0) continue;
            const weight = useSp ? spByKey[st.key] : count[st.key];
            const width = Math.round((weight / totalWeight) * 1000) / 10;
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
    };

    const cards = projects.map((p) => {
        const projTasks = tasksByProject.get(p.id) || [];
        const tasksCount = projTasks.length;
        const done = projTasks.filter(isDone);
        const totalSp = projTasks.reduce((a, t) => a + sp(t), 0);
        const doneSp = done.reduce((a, t) => a + sp(t), 0);
        const closedCount = done.length;
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
            segments: segmentsFor(projTasks),
            ownerName: p.owner?.name ?? null,
            initials: initialsOf(p.owner?.name),
            avatarClass: AVATAR[(p.created_by_id ?? 0) % AVATAR.length],
            teams: (p.teams || []).map((t) => t.name),
            tasksCount,
            closedCount,
            tasksLabel: interpolate(strings.countTasks, { count: tasksCount }),
            sp: totalSp,
            mine: p.created_by_id === currentUserId,
            archived: p.archived_at != null,
            searchText: `${p.alias} ${p.name}`.toLowerCase(),
        };
    });

    // Kopfzeile: aktive (nicht archivierte) Projekte.
    const active = cards.filter((c) => !c.archived);
    const activeCount = active.length;
    const openTasks = active.reduce((a, c) => a + (c.tasksCount - c.closedCount), 0);
    const totalSp = active.reduce((a, c) => a + c.sp, 0);
    const nf = new Intl.NumberFormat(locale || 'de-DE');
    const summaryLine =
        `${activeCount} ${activeCount === 1 ? strings.projectSingular : strings.projectsPlural}` +
        ` · ${interpolate(strings.countOpenTasks, { count: nf.format(openTasks) })}` +
        ` · ${interpolate(strings.countStoryPoints, { count: nf.format(totalSp) })}`;

    return { cards, summaryLine };
}
