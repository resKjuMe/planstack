import React, { useState } from 'react';

// An expanded status column. Header shows the status label, count (and WIP
// `current / limit` when a limit is configured), plus a collapse control. The
// body is a drop target for drag-and-drop: only allowed transitions accept a
// drop — disallowed target columns are greyed out and never call preventDefault
// on dragover, so the browser shows a "no-drop" cursor and the drop is refused.
export default function BoardColumn({
    status,
    label,
    dotClass,
    headClass,
    count,
    wipLimit,
    isDragActive,
    isDropAllowed,
    t,
    onCollapse,
    onDrop,
    children,
    footer,
    collapsible = true,
}) {
    const [isOver, setIsOver] = useState(false);
    const overLimit = wipLimit != null && count > wipLimit;

    return (
        <div
            className={[
                // Width comes from the parent grid track (expanded = 1fr share,
                // collapsed bars = fixed); the column just fills its cell.
                'board-cell flex h-full w-full min-w-0 flex-col rounded-lg p-2 transition',
                overLimit ? 'bg-rose-50/70 dark:bg-rose-900/20' : 'bg-gray-50/70 dark:bg-gray-800/40',
                isDragActive && !isDropAllowed ? 'opacity-40' : '',
                isOver && isDropAllowed ? 'ring-2 ring-indigo-400 dark:ring-indigo-500' : '',
            ].join(' ')}
            onDragOver={(e) => {
                if (isDragActive && isDropAllowed) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (!isOver) setIsOver(true);
                }
            }}
            onDragLeave={(e) => {
                // Ignore leaves into child elements.
                if (!e.currentTarget.contains(e.relatedTarget)) setIsOver(false);
            }}
            onDrop={(e) => {
                if (isDragActive && isDropAllowed) {
                    e.preventDefault();
                    setIsOver(false);
                    onDrop(status);
                }
            }}
        >
            <div className="mb-2 flex items-center justify-between gap-2 px-1">
                <button
                    type="button"
                    // Clicking the header of an empty expanded column collapses it
                    // again (per spec); filled columns collapse via the icon.
                    onClick={() => collapsible && count === 0 && onCollapse()}
                    className={`flex items-center gap-2 text-sm font-semibold ${headClass} ${collapsible && count === 0 ? 'cursor-pointer' : 'cursor-default'}`}
                >
                    <span className={`h-2 w-2 rounded-full ${dotClass}`} aria-hidden />
                    <span>{label}</span>
                </button>

                <div className="flex items-center gap-1.5">
                    <span
                        title={
                            wipLimit != null
                                ? (overLimit
                                    ? t('wip_over_title', { current: count, limit: wipLimit })
                                    : t('wip_limit_title', { current: count, limit: wipLimit }))
                                : undefined
                        }
                        className={[
                            'text-xs font-semibold',
                            overLimit ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 dark:text-gray-500',
                        ].join(' ')}
                    >
                        {wipLimit != null ? `${count} / ${wipLimit}` : count}
                    </span>
                    {collapsible && (
                        <button
                            type="button"
                            onClick={onCollapse}
                            title={t('collapse_column')}
                            aria-label={t('collapse_column')}
                            className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            ‹
                        </button>
                    )}
                </div>
            </div>

            <div className="flex-1 space-y-2 overflow-y-auto min-h-16">
                {children}
                {count === 0 && (
                    <p className="px-1 py-6 text-center text-xs text-gray-300 dark:text-gray-600 select-none">
                        {t('empty_column')}
                    </p>
                )}
                {footer}
            </div>
        </div>
    );
}
