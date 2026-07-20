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
import { moveTask } from '../api';
import { useBoardCollapseState } from '../useBoardCollapseState';
import { colorForToken } from '../statusColors';
import { allowedTargets, canTransition, groupStartingAt } from '../workflowConfig';

// Synthetic collapse key for the exception lane so its collapsed/expanded state
// persists through the same per-board localStorage mechanism as the columns.
const EXCEPTIONS_KEY = '__exceptions__';

export default function Board({ data }) {
    const { workflow, currentUserId, endpoints, csrf } = data;
    const t = useMemo(() => makeT(data.strings), [data.strings]);

    const [tasks, setTasks] = useState(data.tasks);
    const [dragging, setDragging] = useState(null); // { taskId, from }
    const [toast, setToast] = useState(null);
    const [showAllMerged, setShowAllMerged] = useState(false);
    // Persisted per board: show configured groups as individual status columns.
    const ungroupKey = `board:${data.projectId}:ungrouped`;
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

    const collapse = useBoardCollapseState(data.projectId, defaultExpandedKeys);

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

            const before = tasks;
            setTasks((prev) =>
                prev.map((tk) =>
                    tk.id === taskId ? { ...tk, status: targetStatus, displayStatus: targetStatus } : tk,
                ),
            );

            const res = await moveTask({ endpoints, csrf }, taskId, targetStatus);
            if (res.ok) {
                setTasks((prev) => prev.map((tk) => (tk.id === taskId ? res.task : tk)));
                collapse.setCollapsed(targetStatus, false); // keep the now-populated target visible
            } else {
                setTasks(before); // snap back
                setToast(t('move_error', { message: res.message }));
            }
        },
        [tasks, workflow, endpoints, csrf, t, collapse],
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

    const renderCard = useCallback(
        (task) => (
            <TaskCard
                key={task.id}
                task={task}
                t={t}
                csrf={csrf}
                endpoints={endpoints}
                dimmed={filters.highlightBlocked && ! task.isBlocked}
            />
        ),
        [t, csrf, endpoints, filters.highlightBlocked],
    );

    const draggingTask = dragging ? tasks.find((tk) => tk.id === dragging.taskId) : null;

    // Grid cells with their track width. Collapsed bars keep a fixed narrow
    // track; expanded columns share the rest (1fr). Transitioning the grid
    // template animates collapse/expand.
    const COLLAPSED_TRACK = '2.25rem'; // = w-9
    const EXPANDED_TRACK = 'minmax(0, 1fr)';
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
            const members = group.statuses.map((s) => ({
                status: s,
                label: workflow.labels[s] ?? s,
                dotClass: colorForToken(workflow.colors?.[s]).dot,
                count: countByStatus[s] ?? 0,
                allowed: dragging ? allowedTargets(workflow, dragging.from).has(s) : false,
                cards: columnTasksFor(s).map((task) => renderCard(task)),
            }));
            cells.push({
                track: EXPANDED_TRACK,
                node: (
                    <GroupColumn
                        key={`group:${group.key}`}
                        group={group}
                        members={members}
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
                    assignees={data.assignees}
                    currentUserId={currentUserId}
                    staleDays={workflow.mergedStaleDays}
                    staleCount={staleCount}
                    hasGroups={(workflow.collapseGroups?.length ?? 0) > 0}
                    ungrouped={ungrouped}
                    onToggleUngrouped={() => setUngrouped((v) => !v)}
                />
            </div>

            {/* CSS grid: collapsed bars = fixed narrow track, expanded columns
                share the rest (1fr); the fixed row (1fr) + min-height makes every
                column fill the full board height. Collapse/expand is animated per
                cell (.board-cell) on mount — cheaper than morphing track widths. */}
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
                    className="grid gap-3 pb-4 min-h-[65vh]"
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

            <Toast message={toast} onDismiss={() => setToast(null)} />
        </div>
    );
}
