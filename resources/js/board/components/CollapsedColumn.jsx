import React from 'react';
import { useDroppable } from '@dnd-kit/core';

// A collapsed column: a narrow (~38px) vertical bar with a rotated label and the
// count inside the status dot. Clicking expands it. While dragging it is also a
// @dnd-kit drop zone (drop straight onto the bar) when the transition is allowed
// — no hover-to-expand needed.
export default function CollapsedColumn({
    label,
    count,
    dotClass,
    title,
    onExpand,
    dropId = null,
}) {
    // A collapsed status bar is a live drop zone (drop straight onto it); the
    // exception bar passes no dropId and stays inert. Transition rules are
    // enforced on drop.
    const { setNodeRef, isOver } = useDroppable({
        id: dropId ?? `nodrop:${label}`,
        data: { status: dropId },
        disabled: dropId == null,
    });

    return (
        <button
            type="button"
            ref={setNodeRef}
            title={title || label}
            onClick={onExpand}
            className={[
                'board-cell flex h-full w-full flex-col items-center gap-2 rounded-lg py-2',
                'bg-gray-50 dark:bg-gray-800/60 ring-1 ring-gray-200 dark:ring-gray-700',
                'hover:bg-gray-100 dark:hover:bg-gray-700/60 transition',
                isOver && dropId != null ? 'ring-2 ring-inset ring-indigo-400 dark:ring-indigo-500' : '',
            ].join(' ')}
        >
            <span className="text-gray-400 dark:text-gray-500 text-xs" aria-hidden>›</span>
            <span className={`flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white ${dotClass}`}>
                {count}
            </span>
            <span
                className="text-xs font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap"
                style={{ writingMode: 'vertical-rl' }}
            >
                {label}
            </span>
        </button>
    );
}
