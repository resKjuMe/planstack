import { useCallback, useEffect, useMemo, useState } from 'react';

// Persisted, per-user-per-board collapse state.
//
// Backend user settings do not exist yet, so we persist to localStorage under
// board:<projectId>:collapsed (the fallback the task explicitly allows). The
// stored object holds ONLY manual overrides ({ [status]: true|false }); the
// automatic default (empty column → collapsed, filled → expanded) is applied
// for any status without an override. A manual choice therefore survives a
// reload and beats the automatic default until the user changes it back.

function storageKey(projectId) {
    return `board:${projectId}:collapsed`;
}

function load(projectId) {
    try {
        const raw = localStorage.getItem(storageKey(projectId));
        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

/**
 * @param projectId          board identity for the storage key
 * @param defaultExpandedKeys Set of keys expanded by default; every other key
 *                            starts collapsed (regardless of card count). A
 *                            manual choice overrides this and is persisted.
 */
export function useBoardCollapseState(projectId, defaultExpandedKeys) {
    // Manual overrides only. { [status]: boolean } where true = collapsed.
    const [overrides, setOverrides] = useState(() => load(projectId));

    // Transient during a drag: statuses force-expanded by a ≥500ms hover so a
    // card can be dropped into an otherwise-collapsed (empty) column. Not
    // persisted — cleared when the drag ends.
    const [tempExpanded, setTempExpanded] = useState(() => new Set());

    useEffect(() => {
        try {
            localStorage.setItem(storageKey(projectId), JSON.stringify(overrides));
        } catch {
            /* ignore quota / privacy-mode errors */
        }
    }, [projectId, overrides]);

    const isCollapsed = useCallback(
        (status) => {
            if (tempExpanded.has(status)) return false;
            if (Object.prototype.hasOwnProperty.call(overrides, status)) {
                return overrides[status];
            }
            // Default: collapsed unless the key is in the default-expanded set.
            return !defaultExpandedKeys.has(status);
        },
        [overrides, tempExpanded, defaultExpandedKeys],
    );

    const setCollapsed = useCallback((status, collapsed) => {
        setOverrides((prev) => ({ ...prev, [status]: collapsed }));
    }, []);

    const toggle = useCallback(
        (status) => setCollapsed(status, !isCollapsed(status)),
        [isCollapsed, setCollapsed],
    );

    const expandMany = useCallback((statuses) => {
        setOverrides((prev) => {
            const next = { ...prev };
            for (const s of statuses) next[s] = false;
            return next;
        });
    }, []);

    const setTempExpand = useCallback((status, on) => {
        setTempExpanded((prev) => {
            const next = new Set(prev);
            if (on) next.add(status);
            else next.delete(status);
            return next;
        });
    }, []);

    const clearTempExpand = useCallback(() => setTempExpanded(new Set()), []);

    return useMemo(
        () => ({ isCollapsed, setCollapsed, toggle, expandMany, setTempExpand, clearTempExpand }),
        [isCollapsed, setCollapsed, toggle, expandMany, setTempExpand, clearTempExpand],
    );
}
