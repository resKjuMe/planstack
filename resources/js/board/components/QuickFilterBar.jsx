import React from 'react';

// Quick-filter bar above the board. Filters are visually marked when active and
// individually clearable. "Only mine" and the assignee select are the same axis
// (assignee); "only mine" is the shortcut for the current user. "Highlight
// blocked" does not remove cards — it dims the non-blocked ones.
export default function QuickFilterBar({
    t,
    filters,
    setFilters,
    assignees,
    currentUserId,
    staleDays,
    staleCount,
    hasGroups = false,
    ungrouped = false,
    onToggleUngrouped,
}) {
    const set = (patch) => setFilters((f) => ({ ...f, ...patch }));

    const anyActive =
        filters.onlyMine ||
        filters.highlightBlocked ||
        filters.assignee !== 'all';

    const chip = (active, onClick, label) => (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={[
                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ring-1 transition',
                active
                    ? 'bg-indigo-600 text-white ring-indigo-600'
                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700',
            ].join(' ')}
        >
            {label}
            {active && <span aria-hidden className="text-white/80">✕</span>}
        </button>
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {t('filters')}
            </span>

            {chip(
                filters.onlyMine,
                () => set({ onlyMine: !filters.onlyMine }),
                t('only_mine'),
            )}

            {chip(
                filters.highlightBlocked,
                () => set({ highlightBlocked: !filters.highlightBlocked }),
                t('highlight_blocked'),
            )}

            {hasGroups && chip(ungrouped, onToggleUngrouped, t('ungroup'))}

            <label className="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                <span>{t('assignee')}</span>
                <select
                    value={filters.onlyMine ? String(currentUserId) : filters.assignee}
                    disabled={filters.onlyMine}
                    onChange={(e) => set({ assignee: e.target.value })}
                    className="rounded-md border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-1 pl-2 pr-7 text-xs disabled:opacity-50"
                >
                    <option value="all">{t('assignee_all')}</option>
                    <option value="unassigned">{t('assignee_unassigned')}</option>
                    {assignees.map((a) => (
                        <option key={a.id} value={String(a.id)}>
                            {a.name}
                        </option>
                    ))}
                </select>
            </label>

            {staleCount > 0 && (
                <>
                    <span className="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700" aria-hidden />
                    <span className="text-xs text-gray-400 dark:text-gray-500">
                        {t('merged_hidden_hint', { days: staleDays })}
                    </span>
                    {chip(
                        filters.showStaleMerged,
                        () => set({ showStaleMerged: !filters.showStaleMerged }),
                        filters.showStaleMerged ? t('hide_old_merged') : t('show_old_merged'),
                    )}
                </>
            )}

            {anyActive && (
                <button
                    type="button"
                    onClick={() =>
                        set({ onlyMine: false, highlightBlocked: false, assignee: 'all' })
                    }
                    className="ml-auto text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline"
                >
                    {t('clear_filters')}
                </button>
            )}
        </div>
    );
}
