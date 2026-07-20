import React, { useCallback, useMemo, useRef, useState } from 'react';
import BoardColumn from './BoardColumn';
import CollapsedColumn from './CollapsedColumn';
import ExceptionLane from './ExceptionLane';
import QuickFilterBar from './QuickFilterBar';
import TaskCard from './TaskCard';
import Toast from './Toast';
import { makeT } from '../i18n';
import { moveTask } from '../api';
import { useBoardCollapseState } from '../useBoardCollapseState';
import { statusColor } from '../statusColors';
import { allowedTargets, canTransition, groupStartingAt } from '../workflowConfig';

const HOVER_EXPAND_MS = 500;

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
    const [filters, setFilters] = useState({
        onlyMine: false,
        highlightBlocked: false,
        assignee: 'all',
        showStaleMerged: false,
    });

    const hoverTimer = useRef(null);

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

    // --- Drag-and-drop ---
    const endDrag = useCallback(() => {
        if (hoverTimer.current) {
            clearTimeout(hoverTimer.current);
            hoverTimer.current = null;
        }
        collapse.clearTempExpand();
        setDragging(null);
    }, [collapse]);

    const handleDrop = useCallback(
        async (targetStatus) => {
            const drag = dragging;
            const before = tasks;
            endDrag();
            if (!drag) return;

            const { taskId, from } = drag;
            if (from === targetStatus) return;
            if (!canTransition(workflow, from, targetStatus)) return; // not a drop target anyway

            // Optimistic move; reconcile with the server's authoritative result.
            setTasks((prev) =>
                prev.map((tk) =>
                    tk.id === taskId ? { ...tk, status: targetStatus, displayStatus: targetStatus } : tk,
                ),
            );

            const res = await moveTask({ endpoints, csrf }, taskId, targetStatus);
            if (res.ok) {
                setTasks((prev) => prev.map((tk) => (tk.id === taskId ? res.task : tk)));
                // Keep the target visible after a drop — even if it collapses by
                // default (it is now populated).
                collapse.setCollapsed(targetStatus, false);
            } else {
                setTasks(before); // snap back
                setToast(t('move_error', { message: res.message }));
            }
        },
        [dragging, tasks, endDrag, workflow, endpoints, csrf, t, collapse],
    );

    const collapsedDragEnter = useCallback(
        (statuses) => {
            if (!dragging) return;
            if (hoverTimer.current) clearTimeout(hoverTimer.current);
            hoverTimer.current = setTimeout(() => {
                hoverTimer.current = null;
                statuses.forEach((s) => collapse.setTempExpand(s, true));
            }, HOVER_EXPAND_MS);
        },
        [dragging, collapse],
    );

    const collapsedDragLeave = useCallback(() => {
        // Only cancel a still-pending expand; once expanded, the now-visible
        // column takes over hovering and we keep it open for the rest of the drag.
        if (hoverTimer.current) {
            clearTimeout(hoverTimer.current);
            hoverTimer.current = null;
        }
    }, []);

    const renderCard = useCallback(
        (task) => (
            <TaskCard
                key={task.id}
                task={task}
                t={t}
                csrf={csrf}
                endpoints={endpoints}
                dimmed={filters.highlightBlocked && !task.isBlocked}
                isDragging={dragging?.taskId === task.id}
                onDragStart={(tk) => setDragging({ taskId: tk.id, from: tk.displayStatus })}
                onDragEnd={endDrag}
            />
        ),
        [t, csrf, endpoints, filters.highlightBlocked, dragging, endDrag],
    );

    // --- Build the column row, folding consecutive collapsed columns into the
    //     configured groups (Backlog / In progress). ---
    const items = [];
    for (let i = 0; i < workflow.columnOrder.length; ) {
        const status = workflow.columnOrder[i];
        const group = groupStartingAt(workflow, i);
        if (group && group.statuses.every((s) => collapse.isCollapsed(s))) {
            items.push({ kind: 'group', group });
            i += group.statuses.length;
            continue;
        }
        items.push({ kind: 'column', status, collapsed: collapse.isCollapsed(status) });
        i += 1;
    }

    // Turn the row into grid cells: each carries its track width. Collapsed bars
    // keep a fixed narrow track; expanded columns share the rest (1fr). The board
    // is a CSS grid whose grid-template-columns transitions, so resizing a single
    // track animates the collapse/expand.
    const COLLAPSED_TRACK = '2.25rem'; // = w-9
    const EXPANDED_TRACK = 'minmax(0, 1fr)';
    const cells = [];

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

    for (const item of items) {
        if (item.kind === 'group') {
            const count = item.group.statuses.reduce((sum, s) => sum + (countByStatus[s] ?? 0), 0);
            cells.push({
                track: COLLAPSED_TRACK,
                node: (
                    <CollapsedColumn
                        key={`group:${item.group.key}`}
                        label={item.group.label}
                        count={count}
                        dotClass="bg-gray-400"
                        isDragActive={!!dragging}
                        onExpand={() => collapse.expandMany(item.group.statuses)}
                        onDragEnter={() => collapsedDragEnter(item.group.statuses)}
                        onDragLeave={collapsedDragLeave}
                    />
                ),
            });
            continue;
        }

        const { status } = item;
        const label = workflow.labels[status] ?? status;
        const color = statusColor(status);
        const count = countByStatus[status] ?? 0;

        if (item.collapsed) {
            cells.push({
                track: COLLAPSED_TRACK,
                node: (
                    <CollapsedColumn
                        key={status}
                        label={label}
                        count={count}
                        dotClass={color.dot}
                        isDragActive={!!dragging}
                        onExpand={() => collapse.setCollapsed(status, false)}
                        onDragEnter={() => collapsedDragEnter([status])}
                        onDragLeave={collapsedDragLeave}
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
                    onDrop={handleDrop}
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
                />
            </div>

            {/* CSS grid: collapsed bars = fixed narrow track, expanded columns
                share the rest (1fr). Transitioning grid-template-columns animates
                a column's collapse/expand; the fixed row (1fr) + min-height makes
                every column fill the full board height. */}
            <div
                className="grid gap-3 pb-4 min-h-[65vh] transition-[grid-template-columns] duration-300 ease-in-out"
                style={{
                    gridTemplateColumns: cells.map((c) => c.track).join(' '),
                    gridTemplateRows: '1fr',
                }}
            >
                {cells.map((c) => c.node)}
            </div>

            <Toast message={toast} onDismiss={() => setToast(null)} />
        </div>
    );
}
