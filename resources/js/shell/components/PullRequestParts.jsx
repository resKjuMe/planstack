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

// Gestapelter PR (Ziel ist nicht main); dasselbe Icon wie der Stacked-Marker im Board.
export function StackedIcon({ pr, strings, className = 'h-3.5 w-3.5' }) {
    if (pr.targetBranch === 'main') return null;
    return (
        <span title={strings.stacked_on.replace(':branch', pr.targetBranch)} className="inline-flex shrink-0 text-indigo-500 dark:text-indigo-400 align-[-0.125em] mr-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
                <path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.84z" />
                <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" />
                <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" />
            </svg>
        </span>
    );
}

// „vor 3 min“; ab einer Woche das Datum.
export function formatRelative(iso, now, strings) {
    if (!iso) return '—';
    const minutes = Math.max(0, Math.floor((now - new Date(iso)) / 60000));
    if (minutes < 1) return strings.just_now;
    if (minutes < 60) return strings.ago_minutes.replace(':n', minutes);
    if (minutes < 24 * 60) return strings.ago_hours.replace(':n', Math.floor(minutes / 60));
    if (minutes < 7 * 24 * 60) return strings.ago_days.replace(':n', Math.floor(minutes / (24 * 60)));
    return formatDate(iso);
}

const COLUMN_ICONS = {
    // Waage: Empfehlung des Reviews
    decision: <><path d="M12 3v18M7 21h10M5 7h14" /><path d="M5 7l-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z" /></>,
    // Käfer: Befunde (kritisch / wichtig / Hinweis)
    findings: <><rect x="8" y="6" width="8" height="14" rx="4" /><path d="M9 3l1.5 3M15 3l-1.5 3M8 11H4M20 11h-4M8 16H4.5M19.5 16H16M12 11v9" /></>,
    // Flamme: Risiko der Änderung
    criticality: <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z" />,
    // Funken: KI-Prüfbarkeit
    aiReviewability: <><path d="M9.94 15.5a2 2 0 0 0-1.44-1.44l-6.13-1.58a.5.5 0 0 1 0-.96l6.13-1.58a2 2 0 0 0 1.44-1.44l1.58-6.13a.5.5 0 0 1 .96 0l1.58 6.13a2 2 0 0 0 1.44 1.44l6.13 1.58a.5.5 0 0 1 0 .96l-6.13 1.58a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z" /><path d="M20 3v4M22 5h-4" /></>,
    // Person: menschliches Review nötig
    need: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
    // Uhr: Zeitpunkt des Reviews
    reviewedAt: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
};

export function ColumnIcon({ name }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="inline h-4 w-4" aria-hidden="true">
            {COLUMN_ICONS[name]}
        </svg>
    );
}

// Kompakt für die Liste: Haken bzw. Kreuz, der Text im Tooltip.
export function DecisionIcon({ decision, strings }) {
    const approve = decision === 'APPROVE';
    return (
        <span
            title={strings[`decision_${decision}`] ?? decision}
            className={`inline-flex h-5 w-5 items-center justify-center rounded-full ${approve
                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'}`}
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                {approve ? <path d="M5 12l5 5l10 -10" /> : <path d="M18 6L6 18M6 6l12 12" />}
            </svg>
        </span>
    );
}

// Startet über den lokalen claudetask:-Handler eine PowerShell mit Claude und
// ps-review für diesen PR (Einrichtung: /claudetask/setup).
export function reviewCommandHref(pr) {
    return `claudetask:${encodeURIComponent(`/ps-review ${pr.githubUrl}`)}`;
}

// Startknopf für ps-review: ↻ wenn seit dem letzten Review gepusht wurde, sonst ▶.
export function ReviewStartLink({ pr, strings }) {
    const stale = pr.review?.stale;
    return (
        <a
            href={reviewCommandHref(pr)}
            title={stale ? strings.rereview : strings.start_review}
            className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded ${stale
                ? 'text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300'
                : 'text-gray-300 hover:text-indigo-600 dark:text-gray-600 dark:hover:text-indigo-400'}`}
        >
            {stale ? '↻' : (
                <svg viewBox="0 0 24 24" fill="currentColor" className="h-3 w-3" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.87l12-7.5a1 1 0 0 0 0-1.74l-12-7.5A1 1 0 0 0 7 4.5z" /></svg>
            )}
        </a>
    );
}

export function PoBadge({ count, strings }) {
    if (!count) return null;
    return (
        <span
            title={strings.po_decisions.replace(':count', count)}
            className="inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[11px] font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300"
        >
            PO{count > 1 ? ` ${count}` : ''}
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

// Kompakt für die Liste: nur der pulsierende Punkt, der Text im Tooltip.
export function RunningDot({ running, strings }) {
    const text = strings.review_running.replace(':time', formatDate(running.startedAt)) + (running.reviewer ? ` · ${running.reviewer}` : '');
    return (
        <span title={text} className="relative inline-flex h-2 w-2 shrink-0">
            <span className="absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75 animate-ping" />
            <span className="relative inline-flex h-2 w-2 rounded-full bg-sky-500" />
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
        <span className="inline-flex items-center gap-1 whitespace-nowrap" title={`${reviewersTitle(approvals, strings)}\n\n${strings.approvals_hint}`}>
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
        <div className="flex items-center gap-2" title={title ?? undefined}>
            <div className="h-1.5 w-12 rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
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
