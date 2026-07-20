import React from 'react';

// Narrow left-hand lane collecting the off-flow exception states (BLOCKED /
// CONCERNED). These are exclusive statuses in the data model with no preserved
// "last regular status", so a task in one of them cannot keep its position in
// the flow columns — it lives here instead, with a badge on the card. This is
// the documented fallback from the task brief (requirement 5).
//
// Cards here stay draggable: dropping one onto an allowed flow column (e.g.
// PICKABLE / CLAIMED) moves it back into the workflow.
export default function ExceptionLane({ t, tasks, renderCard, onCollapse }) {
    if (tasks.length === 0) return null;

    return (
        <div className="flex h-full w-full min-w-0 flex-col rounded-lg bg-rose-50/60 dark:bg-rose-900/15 p-2 ring-1 ring-rose-100 dark:ring-rose-900/40">
            <div className="mb-2 flex items-center justify-between gap-2 px-1">
                <span className="flex items-center gap-2 text-sm font-semibold text-rose-700 dark:text-rose-300">
                    <span className="h-2 w-2 rounded-full bg-rose-500" aria-hidden />
                    {t('exceptions_lane')}
                </span>
                <div className="flex items-center gap-1.5">
                    <span className="text-xs font-semibold text-rose-500 dark:text-rose-400">{tasks.length}</span>
                    <button
                        type="button"
                        onClick={onCollapse}
                        title={t('collapse_column')}
                        aria-label={t('collapse_column')}
                        className="text-rose-400 hover:text-rose-600 dark:text-rose-500 dark:hover:text-rose-300"
                    >
                        ‹
                    </button>
                </div>
            </div>
            <div className="flex-1 space-y-2 overflow-y-auto min-h-16">
                {tasks.map((task) => renderCard(task))}
            </div>
        </div>
    );
}
