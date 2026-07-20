import React, { useState } from 'react';
import { useDraggable } from '@dnd-kit/core';

// Presentational card (also used for the drag overlay). No drag wiring here.
export function TaskCardView({
    task,
    t,
    csrf,
    endpoints,
    dimmed,
    dragging,
    overlay,
    listeners,
    attributes,
    setNodeRef,
    next = null,
    rest = [],
    labels = {},
    onMove,
}) {
    const [open, setOpen] = useState(false);
    const stop = (e) => e.stopPropagation();

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
                    onPointerDown={stop}
                    className="font-mono text-sm font-semibold text-indigo-700 dark:text-indigo-400 hover:underline"
                >
                    {task.name}
                </a>
                <div className="flex items-center gap-1">
                    {task.isBlocked && (
                        <span title={task.concernSummary || t('badge_blocked')} className="inline-flex items-center rounded-full bg-rose-100 dark:bg-rose-900/50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300">
                            ⛔ {t('badge_blocked')}
                        </span>
                    )}
                    {task.isConcerned && (
                        <span title={task.concernSummary || t('badge_concerned')} className="inline-flex items-center rounded-full bg-orange-100 dark:bg-orange-900/50 px-2 py-0.5 text-[10px] font-semibold text-orange-700 dark:text-orange-300">
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
                        <a href={task.prUrl || undefined} onPointerDown={stop} className="text-gray-500 dark:text-gray-400 hover:underline">
                            #{task.prNumber}
                        </a>
                    )}
                    {task.storyPoints ? <span>{task.storyPoints} SP</span> : null}
                </span>
            </div>

            {/* Split button: primary = next status, dropdown = the remaining
                allowed statuses. Uses the same move path as drag-and-drop. */}
            {! overlay && next && onMove && (
                <div className="mt-2" onPointerDown={stop}>
                    <div className="flex">
                        <button
                            type="button"
                            onClick={() => onMove(task.id, task.displayStatus, next)}
                            className="flex-1 truncate rounded-l bg-gray-50 dark:bg-gray-800/50 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            → {labels[next] ?? next}
                        </button>
                        {rest.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setOpen((o) => ! o)}
                                aria-label="…"
                                className="rounded-r border-l border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-1.5 py-1 text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                ▾
                            </button>
                        )}
                    </div>
                    {open && rest.length > 0 && (
                        <div className="mt-1 space-y-1">
                            {rest.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    onClick={() => { onMove(task.id, task.displayStatus, s); setOpen(false); }}
                                    className="block w-full truncate rounded bg-gray-50 dark:bg-gray-800/50 px-2 py-1 text-left text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    → {labels[s] ?? s}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// Draggable wrapper (@dnd-kit). The whole card is the drag source; interactive
// children stop pointer propagation so links/buttons stay usable.
export default function TaskCard({ task, t, csrf, endpoints, dimmed, transitions = {}, labels = {}, columnOrder = [], exceptionStatuses = [], onMove }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: task.id,
        data: { from: task.displayStatus },
    });

    // Primary action = the nearest FORWARD status (next by column order); the
    // rest go into the dropdown. Falls back to the first listed target when no
    // forward transition exists (e.g. only backward moves). Exception statuses
    // (blocked/concerned) are never offered here: BLOCKED is derived from gates
    // automatically and CONCERNED needs extra info (a concern report).
    const targets = (transitions[task.displayStatus] ?? []).filter(
        (s) => ! exceptionStatuses.includes(s),
    );
    const pos = (s) => {
        const i = columnOrder.indexOf(s);
        return i === -1 ? Number.POSITIVE_INFINITY : i;
    };
    const cur = columnOrder.indexOf(task.displayStatus);
    const forward = targets
        .filter((s) => pos(s) > cur)
        .sort((a, b) => pos(a) - pos(b));
    const next = forward[0] ?? targets[0] ?? null;
    const rest = targets.filter((s) => s !== next);

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
            next={next}
            rest={rest}
            labels={labels}
            onMove={onMove}
        />
    );
}
