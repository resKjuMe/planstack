import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    DndContext,
    DragOverlay,
    MeasuringStrategy,
    PointerSensor,
    pointerWithin,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import BoardColumn from './BoardColumn';
import CollapsedColumn from './CollapsedColumn';
import GroupColumn from './GroupColumn';
import ExceptionLane from './ExceptionLane';
import QuickFilterBar from './QuickFilterBar';
import TaskCard, { TaskCardView } from './TaskCard';
import Toast from './Toast';
import { makeT } from '../i18n';
import { mapApiTask, moveTask } from '../api';
import { useProjectData } from '../../data/useProjectData';
import { patchTask } from '../../data/projectStore';
import { useBoardCollapseState } from '../useBoardCollapseState';
import { colorForToken } from '../statusColors';
import { allowedTargets, canTransition, groupStartingAt } from '../workflowConfig';

// Synthetic collapse key for the exception lane so its collapsed/expanded state
// persists through the same per-board localStorage mechanism as the columns.
const EXCEPTIONS_KEY = '__exceptions__';

export default function Board({ meta }) {
    const { workflow, currentUserId, endpoints, csrf } = meta;
    const t = useMemo(() => makeT(meta.strings), [meta.strings]);

    // Die Board-Tasks kommen aus dem geteilten Projekt-Store (resources/js/data),
    // der Tasks + Phasen einmalig lädt und über Socket-Events (entity-changed)
    // partiell nachlädt — so wird bei der Navigation Board↔Summary nicht neu
    // geladen. Die statischen Render-Metadaten (workflow, strings, endpoints,
    // csrf, roleKeys) bleiben Prop (meta). Der Store hält die rohen API-Tasks
    // (snake_case); hier werden sie auf die flache Board-Form gemappt.
    const { tasks: apiTasks, status: dataStatus, error: dataError } = useProjectData(meta.projectAlias);
    const tasks = useMemo(() => apiTasks.map((tk) => mapApiTask(tk, meta)), [apiTasks, meta]);
    const loadStatus = dataStatus === 'ready' ? 'ready' : dataStatus === 'error' ? 'error' : 'loading';
    const loadError = dataError;

    // Zuständigen-Liste für den Filter aus den (jetzt per API geladenen) Tasks
    // ableiten — früher server-seitig als data.assignees mitgeliefert.
    const assignees = useMemo(() => {
        const byId = new Map();
        for (const tk of tasks) {
            if (tk.claimerId != null && !byId.has(tk.claimerId)) {
                byId.set(tk.claimerId, { id: tk.claimerId, name: tk.claimerName });
            }
        }
        return [...byId.values()].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    }, [tasks]);
    const [dragging, setDragging] = useState(null); // { taskId, from }
    const [toast, setToast] = useState(null);
    const [showAllMerged, setShowAllMerged] = useState(false);
    // Persisted per board: show configured groups as individual status columns.
    const ungroupKey = `board:${meta.projectId}:ungrouped`;
    const [ungrouped, setUngrouped] = useState(() => {
        try {
            return localStorage.getItem(ungroupKey) === '1';
        } catch {
            return false;
        }
    });
    useEffect(() => {
        try {
            localStorage.setItem(ungroupKey, ungrouped ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [ungroupKey, ungrouped]);
    const [filters, setFilters] = useState({
        onlyMine: false,
        highlightBlocked: false,
        assignee: 'all',
        showStaleMerged: false,
    });

    // --- Filtering (assignee axis removes cards; highlightBlocked only dims) ---
    const matchesFilters = useCallback(
        (task) => {
            if (filters.onlyMine) return task.claimerId === currentUserId;
            if (filters.assignee === 'all') return true;
            if (filters.assignee === 'unassigned') return task.claimerId == null;
            return task.claimerId === Number(filters.assignee);
        },
        [filters.onlyMine, filters.assignee, currentUserId],
    );

    const staleMs = workflow.mergedStaleDays * 24 * 60 * 60 * 1000;
    const isStaleMerged = useCallback(
        (task) => task.mergedAt != null && Date.now() - new Date(task.mergedAt).getTime() > staleMs,
        [staleMs],
    );

    // Tasks belonging to a given flow column, after filtering. MERGED is sorted
    // newest-first and (unless the stale toggle is on) hides cards merged longer
    // than mergedStaleDays ago.
    const columnTasksFor = useCallback(
        (status) => {
            let list = tasks.filter((tk) => tk.displayStatus === status && matchesFilters(tk));
            if (status === 'MERGED') {
                list = [...list].sort(
                    (a, b) => new Date(b.mergedAt || 0).getTime() - new Date(a.mergedAt || 0).getTime(),
                );
                if (!filters.showStaleMerged) list = list.filter((tk) => !isStaleMerged(tk));
            }
            return list;
        },
        [tasks, matchesFilters, filters.showStaleMerged, isStaleMerged],
    );

    const exceptionTasks = useMemo(
        () =>
            tasks.filter(
                (tk) => workflow.exceptionStatuses.includes(tk.displayStatus) && matchesFilters(tk),
            ),
        [tasks, workflow.exceptionStatuses, matchesFilters],
    );

    // Count per column (drives WIP, header count and the collapse auto-default).
    // The exception lane gets a synthetic entry so it can collapse like a column.
    const countByStatus = useMemo(() => {
        const out = {};
        for (const status of workflow.columnOrder) out[status] = columnTasksFor(status).length;
        out[EXCEPTIONS_KEY] = exceptionTasks.length;
        return out;
    }, [workflow.columnOrder, columnTasksFor, exceptionTasks.length]);

    const staleCount = useMemo(
        () => tasks.filter((tk) => tk.displayStatus === 'MERGED' && matchesFilters(tk) && isStaleMerged(tk)).length,
        [tasks, matchesFilters, isStaleMerged],
    );

    // Keys expanded by default (from the workflow config); everything else
    // starts collapsed. The exception lane opts in via its own config flag.
    const defaultExpandedKeys = useMemo(() => {
        const s = new Set(workflow.defaultExpanded ?? []);
        if (workflow.exceptionsDefaultExpanded) s.add(EXCEPTIONS_KEY);
        return s;
    }, [workflow.defaultExpanded, workflow.exceptionsDefaultExpanded]);

    const collapse = useBoardCollapseState(meta.projectId, defaultExpandedKeys);

    // --- Live-Ereignisse via Pusher (app.js reicht die Nutzlast als DOM-Event
    // 'planstack:notification' weiter) ---
    // Für eine auf diesem Board vorhandene task_id:
    //  • status_changed=true  → Kachel in die Zielspalte verschieben + ROT
    //    hervorheben.
    //  • status_changed=false → nur BLAU hervorheben (kein Verschieben).
    // Hat das Fenster beim Eintreffen bereits Fokus, blendet das Highlight sofort
    // über 10 s aus. Ist das Fenster NICHT fokussiert, bleibt es statisch bestehen
    // („hold") und beginnt erst beim nächsten Fensterfokus bzw. bei Maus-/
    // Tastatur-Interaktion auszublenden — so verpasst man es nicht, wenn das
    // Ereignis eintrifft, während man gerade woanders ist.
    // highlightedIds: Map task_id → { variant: 'move'|'update', fading: bool }.
    const [highlightedIds, setHighlightedIds] = useState(() => new Map());
    // Refs, damit der Listener einmalig registriert wird und trotzdem stets die
    // aktuellen tasks/collapse/Highlights sieht (kein Re-Subscribe pro Render).
    const tasksRef = useRef(tasks);
    const collapseRef = useRef(collapse);
    const highlightsRef = useRef(highlightedIds);
    const highlightTimers = useRef(new Map());
    useEffect(() => { tasksRef.current = tasks; }, [tasks]);
    useEffect(() => { collapseRef.current = collapse; }, [collapse]);
    useEffect(() => { highlightsRef.current = highlightedIds; }, [highlightedIds]);

    useEffect(() => {
        const knownStatus = (s) =>
            workflow.columnOrder.includes(s) || workflow.exceptionStatuses.includes(s);

        // Entfern-Timer (10 s) einmalig setzen.
        const scheduleRemoval = (taskId) => {
            if (highlightTimers.current.has(taskId)) return;
            const timer = setTimeout(() => {
                setHighlightedIds((prev) => {
                    const n = new Map(prev);
                    n.delete(taskId);
                    return n;
                });
                highlightTimers.current.delete(taskId);
            }, 10000);
            highlightTimers.current.set(taskId, timer);
        };

        // Highlight setzen. Bei Fensterfokus sofort ausblenden (fading=true +
        // Timer); ohne Fokus statisch „hold" (fading=false) bis Fokus/Interaktion.
        const apply = (taskId, variant) => {
            const running = highlightTimers.current.get(taskId);
            if (running) { clearTimeout(running); highlightTimers.current.delete(taskId); }
            const focused = document.hasFocus();
            setHighlightedIds((prev) => new Map(prev).set(taskId, { variant, fading: focused }));
            if (focused) scheduleRemoval(taskId);
        };

        // Alle „hold"-Highlights ins Ausblenden überführen (10 s), ausgelöst durch
        // Fokus/Interaktion. No-op, wenn nichts wartet — hält häufige
        // pointermove-Events billig (kein Re-Render).
        const startFade = () => {
            let any = false;
            for (const v of highlightsRef.current.values()) { if (! v.fading) { any = true; break; } }
            if (! any) return;

            setHighlightedIds((prev) => {
                const n = new Map();
                for (const [id, v] of prev) n.set(id, v.fading ? v : { ...v, fading: true });
                return n;
            });

            for (const [id, v] of highlightsRef.current) {
                if (! v.fading) scheduleRemoval(id);
            }
        };

        // Dieses task-event (Header-Glocke) dient nur noch der ANIMATION: Die
        // eigentliche Statusänderung fließt über den geteilten Store (das parallel
        // gesendete entity-changed-Event lädt die Aufgabe partiell nach und der
        // Store schiebt die Karte in die Zielspalte). Hier wird nur hervorgehoben
        // und die Zielspalte offen gehalten.
        const onNotification = (e) => {
            const d = e.detail;
            if (! d || d.task_id == null) return;
            if (! tasksRef.current.some((tk) => tk.id === d.task_id)) return; // nicht auf diesem Board

            if (d.status_changed === true) {
                if (! d.status || ! knownStatus(d.status)) return; // unbekannte Spalte → kein „Verschieben"-Highlight
                collapseRef.current.setCollapsed(d.status, false); // Zielspalte sichtbar halten
                apply(d.task_id, 'move'); // rot
            } else if (d.status_changed === false) {
                apply(d.task_id, 'update'); // blau, ohne Verschieben
            }
        };

        window.addEventListener('planstack:notification', onNotification);
        // Fokus/Interaktion → wartende Highlights ausblenden.
        window.addEventListener('focus', startFade);
        window.addEventListener('pointerdown', startFade);
        window.addEventListener('pointermove', startFade);
        window.addEventListener('keydown', startFade);

        const timers = highlightTimers.current;
        return () => {
            window.removeEventListener('planstack:notification', onNotification);
            window.removeEventListener('focus', startFade);
            window.removeEventListener('pointerdown', startFade);
            window.removeEventListener('pointermove', startFade);
            window.removeEventListener('keydown', startFade);
            for (const timer of timers.values()) clearTimeout(timer);
            timers.clear();
        };
    }, [workflow.columnOrder, workflow.exceptionStatuses]);

    // --- Drag-and-drop (@dnd-kit) ---
    // The dragged card is rendered as a decoupled DragOverlay, so the board may
    // freely re-layout during the drag (groups split into their status columns)
    // without aborting the drag — the failure mode of native HTML5 DnD here.
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    );

    const performMove = useCallback(
        async (taskId, from, targetStatus) => {
            if (from === targetStatus) return;
            if (! canTransition(workflow, from, targetStatus)) {
                setToast(t('move_forbidden', { from, to: targetStatus }));
                return;
            }

            // Optimistisch im Store patchen (display_status ist der Board-Schlüssel);
            // prev = Task-Zustand VOR dem Patch, für den Rollback.
            const prev = patchTask(meta.projectAlias, taskId, { display_status: targetStatus });

            const res = await moveTask({ endpoints, csrf }, taskId, targetStatus);
            if (res.ok) {
                // Bestätigung fließt über entity-changed (partielles Nachladen) zurück;
                // der optimistische Wert entspricht bereits dem Ziel.
                collapse.setCollapsed(targetStatus, false); // keep the now-populated target visible
            } else if (prev) {
                patchTask(meta.projectAlias, taskId, { display_status: prev.display_status }); // snap back
                setToast(t('move_error', { message: res.message }));
            } else {
                setToast(t('move_error', { message: res.message }));
            }
        },
        [meta.projectAlias, workflow, endpoints, csrf, t, collapse],
    );

    // Last droppable the pointer was over during the drag — used as a fallback
    // when the pointer is released just inside the gap between two (adjacent,
    // same-group) columns, where pointerWithin would otherwise resolve to null.
    const lastOverStatus = useRef(null);

    const onDragStart = useCallback((event) => {
        lastOverStatus.current = null;
        setDragging({ taskId: event.active.id, from: event.active.data.current?.from });
    }, []);

    const onDragOver = useCallback((event) => {
        const status = event.over?.data.current?.status;
        if (status) {
            lastOverStatus.current = status;
        }
    }, []);

    const onDragEnd = useCallback(
        (event) => {
            setDragging(null);
            const taskId = event.active?.id;
            const from = event.active?.data.current?.from;
            const target = event.over?.data.current?.status ?? lastOverStatus.current;
            lastOverStatus.current = null;
            if (taskId != null && from != null && target) {
                performMove(taskId, from, target);
            }
        },
        [performMove],
    );

    const highlightClassFor = (entry) => {
        if (! entry) return '';
        if (entry.variant === 'update') return entry.fading ? 'ps-highlight-blue' : 'ps-hold-blue';
        return entry.fading ? 'ps-highlight' : 'ps-hold';
    };

    const renderCard = useCallback(
        (task) => (
            // Key includes displayStatus so a card force-remounts when its status
            // changes (e.g. after a drop) — guarantees the split button recomputes
            // its next/rest targets instead of showing the pre-move status.
            <TaskCard
                key={`${task.id}:${task.displayStatus}`}
                task={task}
                t={t}
                csrf={csrf}
                endpoints={endpoints}
                dimmed={filters.highlightBlocked && ! task.isBlocked}
                highlightClass={highlightClassFor(highlightedIds.get(task.id))}
                transitions={workflow.transitions}
                labels={workflow.labels}
                columnOrder={workflow.columnOrder}
                exceptionStatuses={workflow.exceptionStatuses}
                onMove={performMove}
            />
        ),
        [t, csrf, endpoints, filters.highlightBlocked, highlightedIds, workflow.transitions, workflow.labels, workflow.columnOrder, workflow.exceptionStatuses, performMove],
    );

    const draggingTask = dragging ? tasks.find((tk) => tk.id === dragging.taskId) : null;

    // Grid cells with their track width. Collapsed bars keep a fixed narrow
    // track; expanded columns share the rest (1fr). Transitioning the grid
    // template animates collapse/expand.
    const COLLAPSED_TRACK = '2.25rem'; // = w-9
    // Readable minimum so columns don't shrink until content is clipped; the
    // board scrolls horizontally (overflow-x-auto) when they no longer fit.
    const EXPANDED_TRACK = 'minmax(16rem, 1fr)';
    const cells = [];

    // Configured groups render as ONE column (GroupColumn) with per-status drop
    // sections inside; the "ungroup" toggle shows the member statuses as separate
    // columns instead. The layout does NOT change on drag (drop zones stay
    // mounted/measured), which is what keeps @dnd-kit drops reliable.

    if (exceptionTasks.length > 0) {
        const exCollapsed = collapse.isCollapsed(EXCEPTIONS_KEY);
        cells.push({
            track: exCollapsed ? COLLAPSED_TRACK : EXPANDED_TRACK,
            node: exCollapsed ? (
                <CollapsedColumn
                    key="exceptions"
                    label={t('exceptions_lane')}
                    count={exceptionTasks.length}
                    dotClass="bg-rose-500"
                    isDragActive={false}
                    onExpand={() => collapse.setCollapsed(EXCEPTIONS_KEY, false)}
                />
            ) : (
                <ExceptionLane
                    key="exceptions"
                    t={t}
                    tasks={exceptionTasks}
                    renderCard={renderCard}
                    onCollapse={() => collapse.setCollapsed(EXCEPTIONS_KEY, true)}
                />
            ),
        });
    }

    for (let i = 0; i < workflow.columnOrder.length; ) {
        const group = groupStartingAt(workflow, i);

        // A group is ALWAYS one column (GroupColumn) with per-status drop sections
        // inside — unless the user ungrouped it. The board layout never changes on
        // drag, so the drop zones stay mounted and correctly measured.
        if (group && ! ungrouped) {
            const members = group.statuses.map((s) => {
                const c = colorForToken(workflow.colors?.[s]);
                return {
                    status: s,
                    label: workflow.labels[s] ?? s,
                    dotClass: c.dot,
                    head: c.head,
                    icon: workflow.icons?.[s] ?? null,
                    count: countByStatus[s] ?? 0,
                    allowed: dragging ? allowedTargets(workflow, dragging.from).has(s) : false,
                    cards: columnTasksFor(s).map((task) => renderCard(task)),
                };
            });
            // Group header uses the middle member's colour + icon (per user choice).
            const mid = members[Math.floor(members.length / 2)];
            cells.push({
                track: EXPANDED_TRACK,
                node: (
                    <GroupColumn
                        key={`group:${group.key}`}
                        group={group}
                        members={members}
                        dotClass={mid?.dotClass ?? 'bg-gray-400'}
                        headClass={mid?.head ?? ''}
                        icon={mid?.icon ?? null}
                        dragActive={!! dragging}
                        t={t}
                    />
                ),
            });
            i += group.statuses.length;
            continue;
        }

        const status = workflow.columnOrder[i];
        i += 1;
        const label = workflow.labels[status] ?? status;
        const color = colorForToken(workflow.colors?.[status]);
        const count = countByStatus[status] ?? 0;

        if (collapse.isCollapsed(status)) {
            cells.push({
                track: COLLAPSED_TRACK,
                node: (
                    <CollapsedColumn
                        key={status}
                        label={label}
                        count={count}
                        dotClass={color.dot}
                        onExpand={() => collapse.setCollapsed(status, false)}
                        dropId={status}
                    />
                ),
            });
            continue;
        }

        const colTasks = columnTasksFor(status);
        const isMerged = status === 'MERGED';
        const cap = workflow.mergedInitialLimit;
        const visible = isMerged && !showAllMerged ? colTasks.slice(0, cap) : colTasks;
        const dropAllowed = dragging ? allowedTargets(workflow, dragging.from).has(status) : false;

        const footer =
            isMerged && colTasks.length > cap ? (
                <button
                    type="button"
                    onClick={() => setShowAllMerged((v) => !v)}
                    className="w-full rounded bg-gray-100 dark:bg-gray-700/60 px-2 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                >
                    {showAllMerged ? t('show_fewer') : t('show_all_merged', { count: colTasks.length })}
                </button>
            ) : null;

        cells.push({
            track: EXPANDED_TRACK,
            node: (
                <BoardColumn
                    key={status}
                    status={status}
                    label={label}
                    dotClass={color.dot}
                    headClass={color.head}
                    icon={workflow.icons?.[status] ?? null}
                    count={count}
                    wipLimit={workflow.wipLimits[status] ?? null}
                    isDragActive={!!dragging}
                    isDropAllowed={dropAllowed}
                    t={t}
                    onCollapse={() => collapse.setCollapsed(status, true)}
                    footer={footer}
                >
                    {visible.map((task) => renderCard(task))}
                </BoardColumn>
            ),
        });
    }

    return (
        <div>
            <div className="mb-4">
                <QuickFilterBar
                    t={t}
                    filters={filters}
                    setFilters={setFilters}
                    assignees={assignees}
                    currentUserId={currentUserId}
                    staleDays={workflow.mergedStaleDays}
                    staleCount={staleCount}
                    hasGroups={(workflow.collapseGroups?.length ?? 0) > 0}
                    ungrouped={ungrouped}
                    onToggleUngrouped={() => setUngrouped((v) => !v)}
                />
            </div>

            {loadStatus === 'loading' && (
                <div className="py-16 text-center text-sm text-gray-400 dark:text-gray-500">{t('loading')}</div>
            )}

            {loadStatus === 'error' && (
                <div className="py-16 text-center text-sm text-rose-600 dark:text-rose-400">
                    {t('load_error', { message: loadError })}
                </div>
            )}

            {/* CSS grid: collapsed bars = fixed narrow track, expanded columns
                share the rest (1fr); the fixed row (1fr) + min-height makes every
                column fill the full board height. Collapse/expand is animated per
                cell (.board-cell) on mount — cheaper than morphing track widths. */}
            {loadStatus === 'ready' && (
                <DndContext
                    sensors={sensors}
                    collisionDetection={pointerWithin}
                    measuring={{ droppable: { strategy: MeasuringStrategy.Always } }}
                    onDragStart={onDragStart}
                    onDragOver={onDragOver}
                    onDragEnd={onDragEnd}
                    onDragCancel={() => { lastOverStatus.current = null; setDragging(null); }}
                >
                    <div
                        className="grid gap-3 pb-4 min-h-[65vh] overflow-x-auto"
                        style={{
                            gridTemplateColumns: cells.map((c) => c.track).join(' '),
                            gridTemplateRows: '1fr',
                        }}
                    >
                        {cells.map((c) => c.node)}
                    </div>

                    <DragOverlay>
                        {draggingTask ? (
                            <TaskCardView task={draggingTask} t={t} csrf={csrf} endpoints={endpoints} overlay />
                        ) : null}
                    </DragOverlay>
                </DndContext>
            )}

            <Toast message={toast} onDismiss={() => setToast(null)} />
        </div>
    );
}
