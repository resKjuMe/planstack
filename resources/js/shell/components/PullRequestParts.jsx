import React from 'react';

// Gemeinsame Bausteine der Pull-Request-Seiten (Liste + Detail).

export function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
}

export function DecisionBadge({ decision, strings }) {
    const approve = decision === 'APPROVE';
    return (
        <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${approve
            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
            : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'}`}
        >
            {strings[`decision_${decision}`] ?? decision}
        </span>
    );
}

export function StateBadge({ state, strings }) {
    const tone = {
        OPEN: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        MERGED: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
        CLOSED: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }[state];
    return (
        <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium ${tone}`}>
            {strings[`state_${state.toLowerCase()}`] ?? state}
        </span>
    );
}

export function RunningBadge({ running, strings }) {
    return (
        <span className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
            <span className="h-1.5 w-1.5 rounded-full bg-sky-500 animate-pulse" />
            {strings.review_running.replace(':time', formatDate(running.startedAt))}
            {running.reviewer ? ` · ${running.reviewer}` : ''}
        </span>
    );
}

export function formatDuration(startIso, endIso) {
    if (!startIso || !endIso) return null;
    const minutes = Math.max(0, Math.round((new Date(endIso) - new Date(startIso)) / 60000));
    return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
}

export const LEVEL_TONE = {
    CRITICAL: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    IMPORTANT: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    HINT: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

export function FindingCounts({ review }) {
    return (
        <span className="inline-flex gap-1 font-mono text-xs">
            <span className={`rounded px-1.5 py-0.5 ${review.critical ? LEVEL_TONE.CRITICAL : 'text-gray-400'}`}>{review.critical}</span>
            <span className={`rounded px-1.5 py-0.5 ${review.important ? LEVEL_TONE.IMPORTANT : 'text-gray-400'}`}>{review.important}</span>
            <span className={`rounded px-1.5 py-0.5 ${review.hint ? LEVEL_TONE.HINT : 'text-gray-400'}`}>{review.hint}</span>
        </span>
    );
}

// risk: hoch = schlecht (rot), ai: niedrig = schlecht (rot).
export function ScoreBar({ value, title, tone }) {
    const bad = tone === 'risk' ? value : 100 - value;
    const color = bad >= 67 ? 'bg-red-500' : bad >= 34 ? 'bg-amber-500' : 'bg-green-500';
    return (
        <div className="flex items-center gap-2 min-w-[6.5rem]" title={title ?? undefined}>
            <div className="h-1.5 w-16 rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <div className={`h-full ${color}`} style={{ width: `${value}%` }} />
            </div>
            <span className="font-mono text-xs text-gray-700 dark:text-gray-300">{value}</span>
        </div>
    );
}

export function Markdown({ html }) {
    if (!html) return null;
    return <div className="md-content" dangerouslySetInnerHTML={{ __html: html }} />;
}
