import React from 'react';
import { useDroppable } from '@dnd-kit/core';

// One per-status drop section inside a group column. The droppable EXISTS at all
// times (also when not dragging) so @dnd-kit measures it before a drag starts —
// no mount/unmount churn mid-drag, which is what broke drop detection when the
// group used to split into separate grid columns.
function DropSection({ status, label, dotClass, count, cards, dragActive, allowed }) {
    const { setNodeRef, isOver } = useDroppable({ id: status, data: { status } });

    return (
        <div
            ref={setNodeRef}
            className={[
                'rounded-md transition',
                dragActive && ! allowed ? 'opacity-40' : '',
                isOver && dragActive && allowed ? 'ring-2 ring-indigo-400 dark:ring-indigo-500' : '',
            ].join(' ')}
        >
            {/* Status sub-header — always visible, so the group column is
                permanently divided by status (not only while dragging). */}
            <div className="mb-1 flex items-center justify-between gap-1 px-1 text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <span className="flex items-center gap-1 truncate">
                    <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${dotClass}`} aria-hidden />
                    <span className="truncate">{label}</span>
                </span>
                <span>{count}</span>
            </div>
            <div className="space-y-2">{cards}</div>
        </div>
    );
}

// A configured group rendered as a SINGLE column (one grid cell). Cards of all
// member statuses are shown together; while dragging, each member status is a
// labeled drop section so a card can be dropped into a precise status without
// the column layout changing (stable @dnd-kit drop zones).
export default function GroupColumn({ group, members, dragActive, t }) {
    const total = members.reduce((sum, m) => sum + m.count, 0);

    return (
        <div className="board-cell flex h-full w-full min-w-0 flex-col rounded-lg bg-gray-50/70 dark:bg-gray-800/40 p-2">
            <div className="mb-2 flex items-center justify-between gap-2 px-1">
                <span className="flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300">
                    <span className="h-2 w-2 rounded-full bg-gray-400" aria-hidden />
                    <span>{group.label}</span>
                </span>
                <span className="text-xs font-semibold text-gray-400 dark:text-gray-500">{total}</span>
            </div>

            <div className={`flex-1 overflow-y-auto min-h-16 ${dragActive ? 'space-y-2' : 'space-y-2'}`}>
                {members.map((m) => (
                    <DropSection key={m.status} {...m} dragActive={dragActive} />
                ))}
                {total === 0 && (
                    <p className="px-1 py-6 text-center text-xs text-gray-300 dark:text-gray-600 select-none">
                        {t('empty_column')}
                    </p>
                )}
            </div>
        </div>
    );
}
