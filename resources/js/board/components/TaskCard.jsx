import React, { useState } from 'react';
import { useDraggable } from '@dnd-kit/core';
import CopyMenu from './CopyMenu';
import { useNow } from '../useNow';
import { claimSessionState } from '../claimSession';

// CI-Rollup des PR (task.ciStatus, aus planstack:sync-pr-status) → Icon + Farbe +
// Titel-Key. SUCCESS=Haken (grün), FAILURE/ERROR=Kreuz (rot), PENDING/EXPECTED=Uhr
// (gelb), sonst/null=Fragezeichen (grau, „unbekannt").
const CI_CHECK = '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>';
const CI_CROSS = '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>';
const CI_CLOCK = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
const CI_QUESTION = '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.82 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>';
const CI_META = {
    SUCCESS: { titleKey: 'ci_success', cls: 'text-green-600 dark:text-green-500', paths: CI_CHECK },
    FAILURE: { titleKey: 'ci_failure', cls: 'text-rose-600 dark:text-rose-500', paths: CI_CROSS },
    ERROR: { titleKey: 'ci_failure', cls: 'text-rose-600 dark:text-rose-500', paths: CI_CROSS },
    PENDING: { titleKey: 'ci_pending', cls: 'text-amber-500', paths: CI_CLOCK },
    EXPECTED: { titleKey: 'ci_pending', cls: 'text-amber-500', paths: CI_CLOCK },
};
const CI_UNKNOWN = { titleKey: 'ci_unknown', cls: 'text-gray-400 dark:text-gray-500', paths: CI_QUESTION };
const ciMeta = (status) => CI_META[status] ?? CI_UNKNOWN;

// Presentational card (also used for the drag overlay). No drag wiring here.
export function TaskCardView({
    task,
    t,
    csrf,
    endpoints,
    dimmed,
    dragging,
    overlay,
    highlightClass,
    listeners,
    attributes,
    setNodeRef,
    next = null,
    rest = [],
    labels = {},
    onMove,
    projectAlias = null,
}) {
    const [open, setOpen] = useState(false);
    const stop = (e) => e.stopPropagation();

    // Taktet nur die Zeit — der Session-Zustand kippt dadurch von selbst auf
    // „verwaist", ohne dass ein Server-Event nötig ist.
    const now = useNow();
    const session = claimSessionState(
        { label: task.claimSession, seenAt: task.claimSeenAt, ttlMinutes: task.claimTtlMinutes },
        now,
    );

    // Welche Session gerade an dem Task arbeitet — auch wenn sie ihn nicht hält:
    // `fix` claimt nie, `review` reserviert nur reviewed_by. Ohne das bliebe eine
    // laufende Bearbeitung auf der Karte unsichtbar.
    //
    // Zwei Einschränkungen mit Absicht: Ist es dieselbe Session wie das
    // Claim-Lease, zeigt das Badge oben schon alles (kein zweites daneben). Und
    // eine verwaiste aktive Session wird gar nicht gezeigt — anders als ein Claim
    // bleibt hier nichts belegt, „vor drei Tagen mal angefasst" wäre nur Rauschen.
    const activeState = claimSessionState(
        { label: task.activeSession, seenAt: task.activeSessionSeenAt, ttlMinutes: task.claimTtlMinutes },
        now,
    );
    const active = activeState && ! activeState.stale && activeState.label !== session?.label
        ? activeState
        : null;

    // PR-Zustandszeile nur zeigen, wenn ein PR existiert (dann liegen — sobald der
    // Sync gelaufen ist — CI-Status und offene Kommentare vor).
    const ci = task.prNumber ? ciMeta(task.ciStatus) : null;
    // Approved + CI grün, aber Merge-Konflikt → rotes „!" rechtsbündig in der PR-Zeile.
    const prConflict = task.isApproved && task.ciStatus === 'SUCCESS' && task.mergeable === 'CONFLICTING';

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
                highlightClass || '',
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
                    {/* Stacked: the task depends on parents that aren't merged yet.
                        Right-aligned marker in the title row (layers icon). */}
                    {task.isStacked && (
                        <span
                            title={task.stackedOn?.length ? `${t('stacked')}: ${task.stackedOn.join(', ')}` : t('stacked')}
                            className="shrink-0 text-gray-400 dark:text-gray-500"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-4 w-4"
                                aria-hidden="true"
                            >
                                <path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.84z" />
                                <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" />
                                <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" />
                            </svg>
                        </span>
                    )}
                    {/* Kopier-Menü (Task-Name, Projekt + Task-Name, URL, die drei
                        /planstack-Kommandos). Im Drag-Overlay weggelassen — dort
                        ist die Karte nicht bedienbar. */}
                    {! overlay && (
                        <CopyMenu
                            task={task}
                            t={t}
                            projectAlias={projectAlias}
                            setupUrl={endpoints?.claudetaskSetup ?? null}
                        />
                    )}
                </div>
            </div>

            <p className="mt-1 text-sm text-gray-700 dark:text-gray-300 line-clamp-3">{task.summary}</p>

            {/* PR-Zeile (über Claimer/Worker): CI-Status vor der PR-Nummer, dahinter
                — nur wenn > 0 — die ungelösten Review-Kommentare. Daten aus dem
                minütlichen planstack:sync-pr-status. */}
            {task.prNumber && (
                <div className="mt-2 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                    {/* CI-Icon in Icon-Spalte (wie Personen-Icon) → PR-Nummer bündig
                        mit Autor/Reviewer-Name. */}
                    {ci && (
                        <span className={`flex items-center ${ci.cls}`} title={t(ci.titleKey)}>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-3.5 w-3.5 shrink-0"
                                aria-hidden="true"
                                dangerouslySetInnerHTML={{ __html: ci.paths }}
                            />
                        </span>
                    )}
                    {/* Ohne github_repo am Projekt gibt es keine prUrl — dann statt
                        eines toten <a> ein erklärender Text (sonst sieht die Nummer
                        klickbar aus, tut aber nichts). */}
                    {task.prUrl ? (
                        <a
                            href={task.prUrl}
                            target="_blank"
                            rel="noopener"
                            onPointerDown={stop}
                            className="hover:underline"
                        >
                            #{task.prNumber}
                        </a>
                    ) : (
                        <span title={t('pr_no_repo')}>#{task.prNumber}</span>
                    )}
                    {(task.unresolvedThreads ?? 0) > 0 && (
                        <span className="ml-2 flex items-center gap-0.5" title={t('unresolved_comments')}>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-3.5 w-3.5"
                                aria-hidden="true"
                            >
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                            </svg>
                            {task.unresolvedThreads}
                        </span>
                    )}
                    {prConflict && (
                        <span className="ml-auto font-bold text-rose-600 dark:text-rose-500" title={t('pr_conflict')}>!</span>
                    )}
                </div>
            )}

            <div className="mt-1 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                <span className="flex items-center gap-1 truncate" title={t('assignee')}>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-3.5 w-3.5 shrink-0"
                        aria-hidden="true"
                    >
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    <span className="truncate">{task.claimerName ?? t('unassigned')}</span>
                    {/* Welche Worker-Session hinter dem Claim steckt. Nötig, weil
                        mehrere parallele Sessions unter demselben Nutzer laufen
                        und im Namen allein nicht unterscheidbar wären. */}
                    {session && (
                        <span
                            className={[
                                'inline-flex max-w-[9rem] shrink items-center gap-1 rounded px-1 py-px text-[10px] font-medium',
                                session.stale
                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-500'
                                    : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
                            ].join(' ')}
                            title={[
                                session.stale ? t('session_stale') : t('session_active'),
                                session.seenAt ? new Date(session.seenAt).toLocaleString() : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        >
                            <span aria-hidden="true">{session.stale ? '⚠' : '▶'}</span>
                            <span className="truncate">{session.label}</span>
                        </span>
                    )}
                    {/* Eine fremde Session, die gerade daran arbeitet (fix/review) —
                        grün abgesetzt vom Claim-Lease, damit „hält den Task" und
                        „arbeitet jetzt daran" unterscheidbar bleiben. */}
                    {active && (
                        <span
                            className="inline-flex max-w-[9rem] shrink items-center gap-1 rounded bg-emerald-50 px-1 py-px text-[10px] font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400"
                            title={[
                                t('session_working'),
                                active.seenAt ? new Date(active.seenAt).toLocaleString() : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        >
                            <span aria-hidden="true">⚡</span>
                            <span className="truncate">{active.label}</span>
                        </span>
                    )}
                </span>
                <span className="flex items-center gap-2 shrink-0">
                    {task.storyPoints ? <span>{task.storyPoints} SP</span> : null}
                </span>
            </div>

            {/* Zuletzt gemeldeter Fortschritt innerhalb der laufenden Phase (Event
                mit detail/progress). Macht sichtbar, wie weit ein Worker ist —
                dauerhaft und für alle, nicht nur in dessen lokaler Statuszeile.

                Der Füllstand liegt als Band HINTER dem Text, nicht als eigener
                Balken darüber: so kostet er keine zusätzliche Zeile auf der Karte
                und Text plus Zahl bleiben auf einer Höhe lesbar. Ohne gerechnete
                Prozentzahl bleibt das Band leer und nur der Text steht da —
                geschätzt wird nicht. */}
            {(task.progressDetail || task.progressPercent !== null) && (
                <div
                    className="relative mt-1.5 overflow-hidden rounded bg-indigo-50 dark:bg-indigo-950/60"
                    title={task.progressAt ? new Date(task.progressAt).toLocaleString() : undefined}
                >
                    {task.progressPercent !== null && (
                        <span
                            aria-hidden="true"
                            className="absolute inset-y-0 left-0 bg-indigo-200/70 dark:bg-indigo-800/70"
                            style={{ width: `${Math.min(100, Math.max(0, task.progressPercent))}%` }}
                        />
                    )}
                    <div className="relative flex items-baseline justify-between gap-2 px-1.5 py-1">
                        <span className="truncate text-[11px] text-indigo-900 dark:text-indigo-200">
                            {task.progressDetail}
                        </span>
                        {task.progressPercent !== null && (
                            <span className="shrink-0 text-[11px] font-semibold text-indigo-900 dark:text-indigo-200">
                                {task.progressPercent} %
                            </span>
                        )}
                    </div>
                </div>
            )}

            {/* In the review columns, show the reviewer beneath the assignee so
                it's clear who is reviewing (distinct from who worked the task).
                Includes the REVIEWBAR pool: review-next/review-claim reserve a
                task there via reviewed_by without moving it, so the reservation
                must be visible before the task reaches "in Review". */}
            {(task.isInReview || task.isReviewable) && task.reviewerName && (
                <div className="mt-1 flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500" title={t('reviewer')}>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-3.5 w-3.5 shrink-0"
                        aria-hidden="true"
                    >
                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <span className="truncate">{task.reviewerName}</span>
                </div>
            )}

            {/* In the "Approved" column, show the approver beneath the assignee.
                The approver is the reviewer who signed off (reviewed_by). */}
            {task.isApproved && task.reviewerName && (
                <div className="mt-1 flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500" title={t('approver')}>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-3.5 w-3.5 shrink-0"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                    <span className="truncate">{task.reviewerName}</span>
                </div>
            )}

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
export default function TaskCard({ task, t, csrf, endpoints, dimmed, highlightClass, transitions = {}, labels = {}, columnOrder = [], exceptionStatuses = [], onMove, projectAlias = null }) {
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
            highlightClass={highlightClass}
            setNodeRef={setNodeRef}
            listeners={listeners}
            attributes={attributes}
            next={next}
            rest={rest}
            labels={labels}
            onMove={onMove}
            projectAlias={projectAlias}
        />
    );
}
