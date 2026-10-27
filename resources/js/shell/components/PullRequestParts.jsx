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

const GROUP_SHORT = { Frontend: 'FE', Backend: 'BE' };

const okTone = 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
const openTone = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';

function reviewersTitle(approvals, strings) {
    const by = (state) => approvals.reviewers.filter((r) => r.state === state).map((r) => r.name);
    return [
        by('APPROVED').length ? `${strings.approved_by}: ${by('APPROVED').join(', ')}` : null,
        by('CHANGES_REQUESTED').length ? `${strings.changes_requested_by}: ${by('CHANGES_REQUESTED').join(', ')}` : null,
    ].filter(Boolean).join('\n') || strings.no_approvals;
}

// Kompakt für die Liste: „1/1“, je Gruppe „BE 1/2“, offene Änderungswünsche „✗ 1“.
export function ApprovalChips({ approvals, strings }) {
    const chip = 'rounded px-1.5 py-0.5 font-mono text-[11px]';
    return (
        <span className="inline-flex flex-wrap items-center gap-1" title={`${reviewersTitle(approvals, strings)}\n\n${strings.approvals_hint}`}>
            <span className={`${chip} ${approvals.approved >= approvals.required ? okTone : openTone}`}>
                {approvals.approved >= approvals.required ? '✓ ' : ''}{approvals.approved}/{approvals.required}
            </span>
            {(approvals.groups ?? []).map((g) => (
                <span key={g.label} className={`${chip} ${g.approved >= g.required ? okTone : openTone}`}>
                    {GROUP_SHORT[g.label] ?? g.label} {g.approved}/{g.required}
                </span>
            ))}
            {approvals.groups === null && <span className={`${chip} text-gray-400`} title={strings.approvals_unknown}>?</span>}
            {approvals.changesRequested > 0 && (
                <span className={`${chip} bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300`}>✗ {approvals.changesRequested}</span>
            )}
        </span>
    );
}

export function ApprovalPanel({ approvals, strings }) {
    const row = (label, approved, required) => (
        <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600 dark:text-gray-300">{label}</span>
            <span className={`rounded px-1.5 py-0.5 font-mono text-xs ${approved >= required ? okTone : openTone}`}>{approved} / {required}</span>
        </div>
    );
    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-5 space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100" title={strings.approvals_hint}>{strings.approvals}</h3>
                <span className={`rounded px-2 py-0.5 text-xs font-medium ${approvals.fulfilled ? okTone : openTone}`}>
                    {approvals.fulfilled ? strings.approvals_fulfilled : strings.approvals_missing}
                </span>
            </div>
            <div className="space-y-1.5">
                {row(strings.approvals_general, approvals.approved, approvals.required)}
                {(approvals.groups ?? []).map((g) => (
                    <React.Fragment key={g.label}>{row(`${g.label}${g.team ? ` (${g.team})` : ''}`, g.approved, g.required)}</React.Fragment>
                ))}
                {approvals.groups === null && <p className="text-xs text-gray-400">{strings.approvals_unknown}</p>}
                {approvals.groups?.length === 0 && <p className="text-xs text-gray-400">{strings.no_critical_files}</p>}
            </div>
            {approvals.reviewers.length === 0 ? (
                <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_approvals}</p>
            ) : (
                <ul className="space-y-1 border-t border-gray-100 dark:border-gray-700 pt-3">
                    {approvals.reviewers.map((r) => (
                        <li key={r.name} className="flex items-center justify-between gap-2 text-sm">
                            <span className="flex items-center gap-1.5 text-gray-900 dark:text-gray-100">
                                <span className={r.state === 'APPROVED' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}>
                                    {r.state === 'APPROVED' ? '✓' : '✗'}
                                </span>
                                {r.name}
                            </span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {r.outdated ? `${strings.approval_outdated} · ` : ''}{formatDate(r.submittedAt)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
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
