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
// `pill` = Hintergrund + Text fuer die Kopfzeilen-Pille, `cls` bleibt fuer reine
// Icon-Verwendungen erhalten.
const CI_GREEN = 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300';
const CI_RED = 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300';
const CI_AMBER = 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
const CI_GRAY = 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
const CI_META = {
    SUCCESS: { titleKey: 'ci_success', cls: 'text-green-600 dark:text-green-500', pill: CI_GREEN, paths: CI_CHECK },
    FAILURE: { titleKey: 'ci_failure', cls: 'text-rose-600 dark:text-rose-500', pill: CI_RED, paths: CI_CROSS },
    ERROR: { titleKey: 'ci_failure', cls: 'text-rose-600 dark:text-rose-500', pill: CI_RED, paths: CI_CROSS },
    PENDING: { titleKey: 'ci_pending', cls: 'text-amber-500', pill: CI_AMBER, paths: CI_CLOCK },
    EXPECTED: { titleKey: 'ci_pending', cls: 'text-amber-500', pill: CI_AMBER, paths: CI_CLOCK },
};
const CI_UNKNOWN = { titleKey: 'ci_unknown', cls: 'text-gray-400 dark:text-gray-500', pill: CI_GRAY, paths: CI_QUESTION };
const ciMeta = (status) => CI_META[status] ?? CI_UNKNOWN;

// Personen erscheinen auf der Karte als Initialen-Chip statt mit vollem Namen —
// der Platz reicht sonst nicht (Zuständiger, Reviewer und Session standen sich
// gegenseitig im Weg und wurden abgeschnitten). Der Name steht im title-Attribut.
// Erster + letzter Namensteil, wie serverseitig in User::initials().
function initialsOf(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();

    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

/**
 * Das Session-Label kommt vom Server in der Form „<INITIALEN> <label>"
 * (TrackClaimSession). Für die Karte wird es wieder getrennt: die Initialen als
 * Avatar, der Rest als Text — so bleibt in der schmalen Zeile Platz für das
 * eigentliche Label. Passt das Muster nicht, wird das Label unverändert gezeigt.
 */
function splitSessionLabel(label) {
    const match = /^([A-ZÄÖÜ]{1,3})\s+(.+)$/.exec(String(label ?? ''));

    return match ? { initials: match[1], text: match[2] } : { initials: null, text: label };
}

// Kleiner runder Initialen-Avatar; `title` trägt den vollen Namen.
function Avatar({ name, title, className = '' }) {
    return (
        <span
            title={title ?? name}
            className={
                'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[9px] font-semibold ' +
                className
            }
        >
            {initialsOf(name)}
        </span>
    );
}

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
    const activeState = claimSessionState(
        { label: task.activeSession, seenAt: task.activeSessionSeenAt, ttlMinutes: task.activeSessionTtlMinutes },
        now,
    );
    // Läuft überhaupt gerade eine Session an dem Task? Steuert die Fortschritts-
    // Anzeige: ein Balken ohne arbeitende Session ist ein Überbleibsel. Regulär räumt
    // der Server den Fortschritt beim Abschluss mit weg — das hier greift, wenn eine
    // Session hart abgebrochen ist und nur die TTL sie beendet hat.
    const sessionLive = Boolean(activeState && ! activeState.stale);

    // Label in Initialen + Text zerlegen: der Avatar zeigt, WER die Session fährt,
    // der Text WAS sie tut — nebeneinander in einer Zeile passt beides sonst nicht.
    const { initials: sessionInitials, text: sessionText } = splitSessionLabel(activeState?.label);

    // CI-Pille in der Kopfzeile — nur mit PR (dann liegen, sobald der Sync gelaufen
    // ist, CI-Status und offene Kommentare vor).
    const ci = task.prNumber ? ciMeta(task.ciStatus) : null;
    // Approved + CI grün, aber Merge-Konflikt: blockiert das Mergen und ist sonst
    // nirgends auf dem Board zu sehen — deshalb eine eigene Pille.
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
                {/* Kennung + PR-Nummer in einer Zeile: beide identifizieren den Task,
                    und der Titel darunter bekommt dadurch die volle Breite. */}
                <span className="flex min-w-0 items-baseline gap-1.5">
                    <a
                        href={task.url}
                        onPointerDown={stop}
                        className="truncate font-mono text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 hover:underline"
                    >
                        {task.name}
                    </a>
                    {task.prNumber && (
                        <>
                            <span className="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                            {/* Ohne github_repo am Projekt gibt es keine prUrl — dann
                                statt eines toten <a> ein erklaerender Text. */}
                            {task.prUrl ? (
                                <a
                                    href={task.prUrl}
                                    target="_blank"
                                    rel="noopener"
                                    onPointerDown={stop}
                                    className="shrink-0 font-mono text-xs text-gray-500 dark:text-gray-400 hover:underline"
                                >
                                    #{task.prNumber}
                                </a>
                            ) : (
                                <span className="shrink-0 font-mono text-xs text-gray-400 dark:text-gray-500" title={t('pr_no_repo')}>
                                    #{task.prNumber}
                                </span>
                            )}
                        </>
                    )}
                </span>
                <div className="flex shrink-0 items-center gap-1">
                    {/* CI als Pill statt als blosses Icon: mit Beschriftung ist ohne
                        Hover erkennbar, worauf sich das gruen/rot bezieht. */}
                    {ci && (
                        <span
                            className={`inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${ci.pill}`}
                            title={t(ci.titleKey)}
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-3 w-3"
                                aria-hidden="true"
                                dangerouslySetInnerHTML={{ __html: ci.paths }}
                            />
                            CI
                        </span>
                    )}
                    {/* Offene Review-Threads — nur wenn es welche gibt. */}
                    {(task.unresolvedThreads ?? 0) > 0 && (
                        <span
                            className="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                            title={t('unresolved_comments')}
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                            </svg>
                            {task.unresolvedThreads}
                        </span>
                    )}
                    {/* Merge-Konflikt trotz gruener CI und Approval — blockiert das
                        Mergen und ist sonst nirgends auf dem Board zu sehen. */}
                    {prConflict && (
                        <span
                            className="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-900/50 dark:text-rose-300"
                            title={t('pr_conflict')}
                        >
                            ⚠
                        </span>
                    )}
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

            <p className="mt-1 text-sm leading-snug text-gray-900 dark:text-gray-100 line-clamp-3">{task.summary}</p>

            {/* Session + Fortschritt in EINEM Block. Vorher standen Claimer, Label
                und Fortschritt nebeneinander in einer Zeile und schnitten sich
                gegenseitig ab; als eigener Kasten hat das Label Platz und der
                Füllstand liegt als Leiste am unteren Rand. Nur bei lebender
                Session — ein Balken ohne Arbeit ist ein Überbleibsel. */}
            {sessionLive && (
                <div
                    className="relative mt-2 overflow-hidden rounded-md bg-indigo-50 px-2 py-1.5 dark:bg-indigo-950/60"
                    title={activeState.seenAt ? new Date(activeState.seenAt).toLocaleString() : undefined}
                >
                    <div className="flex items-center justify-between gap-2">
                        <span className="flex min-w-0 items-center gap-1 text-[11px] font-medium text-indigo-900 dark:text-indigo-200">
                            <span aria-hidden="true">⚡</span>
                            <span className="truncate font-mono">{sessionText}</span>
                        </span>
                        <span className="flex shrink-0 items-center gap-1.5">
                            {task.progressPercent !== null && (
                                <span className="text-[11px] font-semibold text-indigo-900 dark:text-indigo-200">
                                    {task.progressPercent} %
                                </span>
                            )}
                            {sessionInitials && (
                                <span
                                    title={t('session_working')}
                                    className="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[9px] font-semibold text-white dark:bg-indigo-500"
                                >
                                    {sessionInitials}
                                </span>
                            )}
                        </span>
                    </div>
                    {task.progressDetail && (
                        <div className="mt-0.5 truncate text-[11px] text-indigo-800/80 dark:text-indigo-300/80">
                            {task.progressDetail}
                        </div>
                    )}
                    {/* Füllstand als Leiste am unteren Rand des Kastens: zeigt den
                        Fortschritt, ohne eine eigene Zeile zu kosten. */}
                    {task.progressPercent !== null && (
                        <span
                            aria-hidden="true"
                            className="absolute bottom-0 left-0 h-1 bg-indigo-500 dark:bg-indigo-400"
                            style={{ width: `${Math.min(100, Math.max(0, task.progressPercent))}%` }}
                        />
                    )}
                </div>
            )}

            {/* Meta-Zeile: Personen als Initialen-Chips (der volle Name steht im
                Tooltip — ausgeschrieben passten Zuständiger, Reviewer und Session
                nicht nebeneinander), dazu Stacked-Marker und Story Points. */}
            <div className="mt-2 flex items-center justify-between gap-2 text-xs text-gray-400 dark:text-gray-500">
                <span className="flex min-w-0 items-center gap-2">
                    {task.claimerName ? (
                        <Avatar
                            name={task.claimerName}
                            title={`${t('assignee')}: ${task.claimerName}`}
                            className="bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200"
                        />
                    ) : (
                        <span title={t('assignee')}>{t('unassigned')}</span>
                    )}

                    {/* Claim-Lease: welche Session den Task HÄLT (nicht: woran gerade
                        gearbeitet wird). Verwaist in Bernstein, damit ein toter
                        Worker erkennbar bleibt. */}
                    {session && (
                        <span
                            className={session.stale ? 'text-amber-600 dark:text-amber-500' : 'text-indigo-600 dark:text-indigo-400'}
                            title={[
                                session.stale ? t('session_stale') : t('session_active'),
                                session.label,
                                session.seenAt ? new Date(session.seenAt).toLocaleString() : null,
                            ].filter(Boolean).join(' · ')}
                        >
                            {session.stale ? '⚠' : '▶'}
                        </span>
                    )}

                    {/* Stacked: hängt an noch nicht gemergten Vorgängern. */}
                    {task.isStacked && (
                        <span
                            title={task.stackedOn?.length ? `${t('stacked')}: ${task.stackedOn.join(', ')}` : t('stacked')}
                            className="shrink-0"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                <path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.84z" />
                                <path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" />
                                <path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" />
                            </svg>
                        </span>
                    )}

                    {/* Reviewer (Auge) bzw. Approver (Haken) — dasselbe Feld
                        reviewed_by, das Icon unterscheidet den Zustand. */}
                    {(task.isInReview || task.isReviewable) && task.reviewerName && (
                        <span className="flex shrink-0 items-center gap-0.5" title={`${t('reviewer')}: ${task.reviewerName}`}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            {initialsOf(task.reviewerName)}
                        </span>
                    )}
                    {task.isApproved && task.reviewerName && (
                        <span className="flex shrink-0 items-center gap-0.5" title={`${t('approver')}: ${task.reviewerName}`}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                            {initialsOf(task.reviewerName)}
                        </span>
                    )}
                </span>
                {task.storyPoints ? <span className="shrink-0">{task.storyPoints} SP</span> : null}
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
