import React from 'react';
import { useDraggable } from '@dnd-kit/core';

// Presentational card (also used for the drag overlay). No drag wiring here.
export function TaskCardView({ task, t, csrf, endpoints, dimmed, dragging, overlay, listeners, attributes, setNodeRef }) {
    const claimUrl = endpoints.claim.replace('__TASK__', String(task.id));

    return (
        <div
            ref={setNodeRef}
            {...(attributes || {})}
            {...(listeners || {})}
            className={[
                'group select-none rounded-lg bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-100 dark:ring-gray-700 p-3 transition',
                overlay ? 'cursor-grabbing shadow-lg rotate-1' : 'cursor-grab active:cursor-grabbing',
                dragging ? 'opacity-40' : '',
                dimmed ? 'opacity-40' : '',
            ].join(' ')}
        >
            <div className="flex items-center justify-between gap-2">
                <a
                    href={task.url}
                    onPointerDown={(e) => e.stopPropagation()}
                    className="font-mono text-sm font-semibold text-indigo-700 dark:text-indigo-400 hover:underline"
                >
                    {task.name}
                </a>
                <div className="flex items-center gap-1">
                    {task.isBlocked && (
                        <span
                            title={task.concernSummary || t('badge_blocked')}
                            className="inline-flex items-center rounded-full bg-rose-100 dark:bg-rose-900/50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300"
                        >
                            ⛔ {t('badge_blocked')}
                        </span>
                    )}
                    {task.isConcerned && (
                        <span
                            title={task.concernSummary || t('badge_concerned')}
                            className="inline-flex items-center rounded-full bg-orange-100 dark:bg-orange-900/50 px-2 py-0.5 text-[10px] font-semibold text-orange-700 dark:text-orange-300"
                        >
                            ⚠ {t('badge_concerned')}
                        </span>
                    )}
                </div>
            </div>

            <p className="mt-1 text-sm text-gray-700 dark:text-gray-300 line-clamp-3">{task.summary}</p>

            <div className="mt-2 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                <span className="truncate">{task.claimerName ?? t('unassigned')}</span>
                <span className="flex items-center gap-2 shrink-0">
                    {task.prNumber && (
                        <a href={task.prUrl || undefined} onPointerDown={(e) => e.stopPropagation()} className="text-gray-500 dark:text-gray-400 hover:underline">
                            #{task.prNumber}
                        </a>
                    )}
                    {task.storyPoints ? <span>{task.storyPoints} SP</span> : null}
                </span>
            </div>

            {task.canClaim && !overlay && (
                <form method="POST" action={claimUrl} className="mt-2" onPointerDown={(e) => e.stopPropagation()}>
                    <input type="hidden" name="_token" value={csrf} />
                    <button
                        type="submit"
                        className="w-full rounded bg-gray-50 dark:bg-gray-800/50 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        {task.isClaimed ? t('release') : t('claim')}
                    </button>
                </form>
            )}
        </div>
    );
}

// Draggable wrapper (@dnd-kit). The whole card is the drag source; interactive
// children stop pointer propagation so links/buttons stay clickable.
export default function TaskCard({ task, t, csrf, endpoints, dimmed }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: task.id,
        data: { from: task.displayStatus },
    });

    return (
        <TaskCardView
            task={task}
            t={t}
            csrf={csrf}
            endpoints={endpoints}
            dimmed={dimmed}
            dragging={isDragging}
            setNodeRef={setNodeRef}
            listeners={listeners}
            attributes={attributes}
        />
    );
}
