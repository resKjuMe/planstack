// Per-status Tailwind classes for the board columns and cards. Kept as complete
// literal strings (not concatenated fragments) so Tailwind's content scanner —
// which now includes resources/js/**/*.{js,jsx} — can see and emit them. The
// palette mirrors app/Enums/TaskStatus.php (barClasses/badgeClasses) so the
// board stays visually consistent with the diagram and summary views.

const COLORS = {
    UNKNOWN:     { dot: 'bg-gray-400',     head: 'text-gray-600 dark:text-gray-300' },
    BLOCKED:     { dot: 'bg-rose-500',     head: 'text-rose-600 dark:text-rose-300' },
    CONCERNED:   { dot: 'bg-red-500',      head: 'text-red-600 dark:text-red-300' },
    PICKABLE:    { dot: 'bg-indigo-500',   head: 'text-indigo-600 dark:text-indigo-300' },
    CLAIMED:     { dot: 'bg-sky-500',      head: 'text-sky-600 dark:text-sky-300' },
    ANALYZING:   { dot: 'bg-blue-500',     head: 'text-blue-600 dark:text-blue-300' },
    IN_PROGRESS: { dot: 'bg-blue-700',     head: 'text-blue-700 dark:text-blue-300' },
    IN_REVIEW:   { dot: 'bg-purple-500',   head: 'text-purple-600 dark:text-purple-300' },
    COMPLETED:   { dot: 'bg-green-500',    head: 'text-green-600 dark:text-green-300' },
    MERGED:      { dot: 'bg-emerald-500',  head: 'text-emerald-600 dark:text-emerald-300' },
};

const FALLBACK = { dot: 'bg-gray-400', head: 'text-gray-600 dark:text-gray-300' };

export function statusColor(status) {
    return COLORS[status] ?? FALLBACK;
}
