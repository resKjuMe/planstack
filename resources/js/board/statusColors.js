// Finite color-token palette for board statuses. Organizations pick a token per
// status (server-side); the board maps the token to Tailwind classes here. Kept
// as complete literal strings (not concatenated) so Tailwind's content scanner —
// which includes resources/js/**/*.{js,jsx} — emits them. Extend this map (and
// the PHP palette used by Blade) together when adding a token.

const TOKENS = {
    gray:    { dot: 'bg-gray-400',    head: 'text-gray-600 dark:text-gray-300' },
    indigo:  { dot: 'bg-indigo-500',  head: 'text-indigo-600 dark:text-indigo-300' },
    sky:     { dot: 'bg-sky-500',     head: 'text-sky-600 dark:text-sky-300' },
    blue:    { dot: 'bg-blue-500',    head: 'text-blue-600 dark:text-blue-300' },
    navy:    { dot: 'bg-blue-700',    head: 'text-blue-700 dark:text-blue-300' },
    purple:  { dot: 'bg-purple-500',  head: 'text-purple-600 dark:text-purple-300' },
    green:   { dot: 'bg-green-500',   head: 'text-green-600 dark:text-green-300' },
    emerald: { dot: 'bg-emerald-500', head: 'text-emerald-600 dark:text-emerald-300' },
    rose:    { dot: 'bg-rose-500',    head: 'text-rose-600 dark:text-rose-300' },
    red:     { dot: 'bg-red-500',     head: 'text-red-600 dark:text-red-300' },
    orange:  { dot: 'bg-orange-500',  head: 'text-orange-600 dark:text-orange-300' },
    amber:   { dot: 'bg-amber-500',   head: 'text-amber-600 dark:text-amber-300' },
    teal:    { dot: 'bg-teal-500',    head: 'text-teal-600 dark:text-teal-300' },
    slate:   { dot: 'bg-slate-500',   head: 'text-slate-600 dark:text-slate-300' },
};

const FALLBACK = { dot: 'bg-gray-400', head: 'text-gray-600 dark:text-gray-300' };

/**
 * Classes for a color token (e.g. "indigo"). Unknown tokens fall back to gray.
 */
export function colorForToken(token) {
    return TOKENS[token] ?? FALLBACK;
}
