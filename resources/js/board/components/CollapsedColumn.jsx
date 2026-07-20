import React from 'react';

// A collapsed column rendered as a narrow (~38px) vertical bar: chevron, a
// rotated status label (writing-mode: vertical-rl) and the task count. Used
// both for a single collapsed status and for a grouped collapsed bar (several
// consecutive collapsed columns folded together). Clicking expands it.
//
// During a drag it is also a hover target: dragging over it (handled by the
// parent's ≥500ms timer via onDragEnter/onDragLeave) temporarily expands it so
// a card can be dropped into an otherwise-hidden empty status.
export default function CollapsedColumn({
    label,
    count,
    dotClass,
    title,
    isDragActive,
    onExpand,
    onDragEnter,
    onDragLeave,
}) {
    return (
        <button
            type="button"
            title={title || label}
            onClick={onExpand}
            onDragEnter={onDragEnter}
            onDragLeave={onDragLeave}
            onDragOver={(e) => {
                // Allow the drag to keep hovering (so the timer can fire) without
                // making the bar itself a drop target — the drop happens on the
                // expanded column once it opens.
                if (isDragActive) e.preventDefault();
            }}
            className={[
                'flex w-9 shrink-0 flex-col items-center gap-2 rounded-lg py-2',
                'bg-gray-50 dark:bg-gray-800/60 ring-1 ring-gray-200 dark:ring-gray-700',
                'hover:bg-gray-100 dark:hover:bg-gray-700/60 transition',
                isDragActive ? 'ring-2 ring-indigo-400 dark:ring-indigo-500' : '',
            ].join(' ')}
        >
            <span className="text-gray-400 dark:text-gray-500 text-xs" aria-hidden>›</span>
            <span className={`h-1.5 w-1.5 rounded-full ${dotClass}`} aria-hidden />
            <span
                className="text-xs font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap"
                style={{ writingMode: 'vertical-rl' }}
            >
                {label}
            </span>
            <span className="mt-auto rounded-full bg-gray-200 dark:bg-gray-700 px-1.5 text-[10px] font-semibold text-gray-600 dark:text-gray-300">
                {count}
            </span>
        </button>
    );
}
