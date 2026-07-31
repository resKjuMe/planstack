import React, { useMemo, useState } from 'react';
import PageHead from '../components/PageHead.jsx';
import { useProjectData } from '../../data/useProjectData';
import { useTaskTimeline } from '../../timeline/useTaskTimeline.js';
import { deriveTimeline, flattenTree } from '../../timeline/derive.js';
import { interpolate, transChoice } from '../../summary/i18n.js';
import { BlockSkeleton, ChipsSkeleton } from '../components/Skeleton.jsx';

// Breite der Taskspalte (bleibt beim horizontalen Scrollen stehen) und
// Mindestbreite der Achse — 30 Spalten brauchen Platz, darunter wird gescrollt.
const NAME_COL = 'w-[21rem]';
const AXIS_MIN = 'min-w-[64rem]';

// Einrückung je Baumebene, ab der 8. Ebene gedeckelt: tiefe Ketten (die es gibt)
// würden der Taskspalte sonst den Platz für den Namen wegnehmen.
const indentOf = (depth) => 8 + Math.min(depth, 8) * 11;

function Chevron({ open }) {
    return (
        <svg
            className={'h-3.5 w-3.5 shrink-0 transition-transform ' + (open ? 'rotate-90' : '')}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M9 6l6 6l-6 6" />
        </svg>
    );
}

// Das Kalendergitter liegt EINMAL hinter allen Zeilen (nicht je Zeile): Wochenenden
// getönt, Tagesgrenzen als Haarlinie, „jetzt" als Linie. Dieselben Prozentwerte wie
// die Balken — so sitzt ein Balken exakt über seinem Tag.
function DayGrid({ days, nowLeft, nameCol }) {
    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 flex">
            <div className={nameCol + ' shrink-0'} />
            <div className="relative flex-1">
                {days.map((cell) => (
                    <div
                        key={cell.start}
                        style={{ left: cell.left + '%', width: cell.width + '%' }}
                        className={
                            'absolute inset-y-0 border-r border-gray-100 dark:border-gray-700/60 ' +
                            (cell.isWeekend ? 'bg-gray-100/80 dark:bg-gray-900/40' : '')
                        }
                    />
                ))}
                <div style={{ left: nowLeft + '%' }} className="absolute inset-y-0 w-px bg-indigo-400/70 dark:bg-indigo-400/60" />
            </div>
        </div>
    );
}

// Der Statusbalken eines Tasks: ein Stück je Aufenthalt (Claim → … → Freigabe), der
// Abschluss als Haken. Ohne Aufenthalte im Fenster bleibt eine gestrichelte Linie.
function TaskBar({ node }) {
    if (node.bars.length === 0 && !node.doneAt) {
        return <div className="absolute inset-x-0 top-1/2 border-t border-dashed border-gray-200 dark:border-gray-700" />;
    }

    const last = node.bars[node.bars.length - 1];

    return (
        <>
            {node.bars.map((bar, i) => (
                <div
                    key={bar.key + '-' + i}
                    title={bar.title}
                    style={{ left: bar.left + '%', width: bar.width + '%', minWidth: '3px' }}
                    className={
                        'absolute top-1/2 -translate-y-1/2 h-3 ' +
                        (bar.bar || 'bg-gray-300') +
                        (i === 0 && !bar.startsBefore ? ' rounded-l-full' : '') +
                        // Ein laufender Aufenthalt hat kein Ende — keine runde Kante,
                        // damit „läuft noch" ablesbar bleibt.
                        (bar === last && !bar.open ? ' rounded-r-full' : '') +
                        // Gebündelte Kurz-Aufenthalte: flachere Ecken als Hinweis,
                        // dass hier mehrere Wechsel zusammengefasst sind.
                        (bar.mixed ? ' rounded-sm opacity-80' : '')
                    }
                />
            ))}
            {node.doneAt && (
                <span
                    title={node.doneAt.label}
                    style={{ left: node.doneAt.left + '%' }}
                    className="absolute top-1/2 -translate-y-1/2 -ml-2 text-emerald-600 dark:text-emerald-400"
                >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d="M5 12l5 5l10 -10" />
                    </svg>
                </span>
            )}
        </>
    );
}

function Row({ row, strings, onToggle }) {
    const { node, depth, hasChildren, isCollapsed } = row;

    return (
        <div className="relative flex border-b border-gray-100 dark:border-gray-700/60 last:border-b-0 hover:bg-gray-50/70 dark:hover:bg-gray-700/20">
            <div
                className={NAME_COL + ' shrink-0 sticky left-0 z-10 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 py-1.5 pe-3'}
                style={{ paddingInlineStart: indentOf(depth) + 'px' }}
            >
                <div className="flex items-center gap-1.5 min-w-0">
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={() => onToggle(node.id)}
                            aria-expanded={!isCollapsed}
                            title={transChoice(strings.dependentsCount, node.descendants, { count: node.descendants })}
                            className="shrink-0 text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                        >
                            <Chevron open={!isCollapsed} />
                        </button>
                    ) : (
                        <span aria-hidden="true" className="w-3.5 shrink-0" />
                    )}

                    <a
                        href={node.url}
                        title={node.summary || node.name}
                        className="truncate font-mono text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {node.name}
                    </a>

                    <span className={'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium ' + (node.badge || '')}>
                        {node.statusLabel}
                    </span>

                    {hasChildren && isCollapsed && (
                        <span className="shrink-0 text-[10px] text-gray-400 dark:text-gray-500">+{node.descendants}</span>
                    )}
                </div>

                <div className="mt-0.5 flex items-center gap-1.5 ps-5 min-w-0">
                    <span className="truncate text-[11px] text-gray-500 dark:text-gray-400" title={node.summary || undefined}>
                        {node.summary}
                    </span>
                </div>

                {node.extraDeps.length > 0 && (
                    <div className="mt-0.5 flex flex-wrap items-center gap-1 ps-5">
                        <span className="text-[10px] text-gray-400 dark:text-gray-500">{strings.dependsOn}</span>
                        {node.extraDeps.map((dep) => (
                            <a
                                key={dep.id}
                                href={dep.url}
                                className="font-mono text-[10px] text-gray-500 hover:underline dark:text-gray-400"
                            >
                                {dep.name}
                            </a>
                        ))}
                    </div>
                )}
            </div>

            <div className="relative flex-1 min-h-[2.75rem]">
                <TaskBar node={node} />
            </div>
        </div>
    );
}

// Zeitachse als Teilansicht des ProjectWorkspace: alle Tasks als Abhängigkeitsbaum,
// je Task ein Statusbalken über die letzten 30 Tage. Baum aus dem geteilten Store,
// Balken aus dem Verlaufs-Endpunkt; beides live über entity-changed.
export default function TimelineView({ project, strings }) {
    const { tasks, phases, statusConfig, status, error } = useProjectData(project.alias);
    const { history, status: historyStatus, error: historyError } = useTaskTimeline(project.alias, 30);

    const locale = (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de';

    const data = useMemo(() => {
        if (status !== 'ready' || !statusConfig || historyStatus !== 'ready' || !history) return null;
        return deriveTimeline({
            tasks,
            phases,
            statusConfig,
            history,
            taskUrlTemplate: project.taskUrlTemplate,
            locale,
            strings,
            interpolate,
            transChoice,
        });
    }, [tasks, phases, statusConfig, status, history, historyStatus, project.taskUrlTemplate, locale, strings]);

    const [collapsed, setCollapsed] = useState(() => new Set());
    const [activeOnly, setActiveOnly] = useState(false);

    const toggle = (id) =>
        setCollapsed((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });

    // Ids aller Knoten mit Nachfolgern — Grundlage von „alle ein-/ausklappen".
    const parentIds = useMemo(() => {
        const ids = [];
        const walk = (node) => {
            if (node.children.length > 0) ids.push(node.id);
            node.children.forEach(walk);
        };
        (data?.roots || []).forEach(walk);
        return ids;
    }, [data]);

    const rows = useMemo(
        () => (data ? flattenTree(data.roots, collapsed, activeOnly) : []),
        [data, collapsed, activeOnly],
    );

    const loading = status === 'loading' || status === 'idle' || historyStatus === 'loading' || historyStatus === 'idle';
    const failed = status === 'error' || historyStatus === 'error';

    return (
        <div className="space-y-4">
            <PageHead
                title={strings.title}
                toggleLabel={strings.showHideExplanation}
                bullets={strings.helpBullets}
                meta={
                    data && (
                        <span className="text-xs text-gray-400 dark:text-gray-500">
                            {interpolate(strings.windowDays, { days: data.windowDays })}
                        </span>
                    )
                }
            />

            {loading && !data && (
                <>
                    <ChipsSkeleton count={2} />
                    <BlockSkeleton className="h-96" />
                </>
            )}
            {failed && !data && (
                <p className="text-sm text-red-600 dark:text-red-400">{error || historyError || strings.loadError}</p>
            )}

            {data && (
                <>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="inline-flex items-center gap-1 rounded-full bg-gray-100 p-1 dark:bg-gray-700">
                            {[
                                { key: 'all', label: strings.all, count: data.totalCount },
                                { key: 'active', label: strings.activeOnly, count: data.activeCount },
                            ].map((pill) => {
                                const active = (pill.key === 'active') === activeOnly;
                                return (
                                    <button
                                        key={pill.key}
                                        type="button"
                                        onClick={() => setActiveOnly(pill.key === 'active')}
                                        className={
                                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium transition-colors ' +
                                            (active
                                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
                                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                                        }
                                    >
                                        {pill.label}
                                        <span
                                            className={
                                                'inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[11px] font-medium ' +
                                                (active
                                                    ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300'
                                                    : 'bg-white text-gray-400 dark:bg-gray-700 dark:text-gray-400')
                                            }
                                        >
                                            {pill.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {parentIds.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setCollapsed(collapsed.size > 0 ? new Set() : new Set(parentIds))}
                                className="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                {collapsed.size > 0 ? strings.expandAll : strings.collapseAll}
                            </button>
                        )}

                        {data.legend.length > 0 && (
                            <div className="ms-auto flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span className="text-xs text-gray-400 dark:text-gray-500">{strings.legend}</span>
                                {data.legend.map((item) => (
                                    <span key={item.key} className="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span className={'h-2 w-4 rounded-full ' + (item.bar || 'bg-gray-300')} />
                                        {item.label}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Eigener Scroll-Bereich statt der Seite: nur so bleiben die
                        Kalender-Kopfzeile (sticky top) und die Taskspalte (sticky
                        left) bei 80 Zeilen sichtbar. */}
                    <div className="max-h-[75vh] overflow-auto rounded-lg bg-white ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <div className={AXIS_MIN}>
                            <div className="sticky top-0 z-30 flex border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                                <div className={NAME_COL + ' shrink-0 sticky left-0 z-40 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-400 dark:text-gray-500'}>
                                    {strings.task}
                                </div>
                                <div className="relative flex-1 h-9">
                                    {data.days.map((cell) => (
                                        <div
                                            key={cell.start}
                                            title={cell.fullLabel}
                                            style={{ left: cell.left + '%', width: cell.width + '%' }}
                                            className={
                                                'absolute inset-y-0 flex flex-col items-center justify-center border-r border-gray-100 text-[10px] leading-none dark:border-gray-700/60 ' +
                                                (cell.isWeekend ? 'bg-gray-100/80 dark:bg-gray-900/40 ' : '') +
                                                (cell.isToday
                                                    ? 'font-semibold text-indigo-600 dark:text-indigo-400'
                                                    : 'text-gray-400 dark:text-gray-500')
                                            }
                                        >
                                            {cell.monthLabel && <span className="mb-0.5 text-[9px] uppercase">{cell.monthLabel}</span>}
                                            <span>{cell.dayLabel}</span>
                                        </div>
                                    ))}
                                    <div
                                        style={{ left: data.nowLeft + '%' }}
                                        className="absolute inset-y-0 w-px bg-indigo-400/70 dark:bg-indigo-400/60"
                                        title={strings.today}
                                    />
                                </div>
                            </div>

                            {rows.length === 0 && (
                                <p className="px-4 py-8 text-sm text-gray-400 dark:text-gray-500">
                                    {data.totalCount === 0 ? strings.noTasks : strings.noActivity}
                                </p>
                            )}

                            {rows.length > 0 && (
                                <div className="relative">
                                    <DayGrid days={data.days} nowLeft={data.nowLeft} nameCol={NAME_COL} />
                                    {rows.map((row) => (
                                        <Row key={row.node.id} row={row} strings={strings} onToggle={toggle} />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
