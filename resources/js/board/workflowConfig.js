// Client-side accessor for the workflow definition. The canonical config lives
// server-side in app/Support/BoardWorkflow.php and is injected into the page as
// window.__PLANSTACK_BOARD__.workflow. This module only reads that payload and
// derives a few convenience lookups — it deliberately hard-codes nothing about
// columns, transitions, WIP limits or groups (task requirement: configurable,
// not baked into the component).

/**
 * Whether a status is one of the off-flow exception states (rendered in the
 * left-hand lane, never as a column).
 */
export function isException(workflow, status) {
    return workflow.exceptionStatuses.includes(status);
}

/**
 * Allowed drop targets when dragging a card that currently sits in `from`.
 * Same status is always allowed (a no-op reorder within a column).
 */
export function allowedTargets(workflow, from) {
    const list = workflow.transitions[from] ?? [];
    return new Set([from, ...list]);
}

export function canTransition(workflow, from, to) {
    if (from === to) return true;
    return (workflow.transitions[from] ?? []).includes(to);
}

/**
 * The collapse group (if any) whose statuses exactly match the columnOrder
 * slice starting at index `i`. Used to fold consecutive collapsed columns into
 * one bar. Returns null when no group starts there.
 */
export function groupStartingAt(workflow, i) {
    const order = workflow.columnOrder;
    for (const group of workflow.collapseGroups) {
        const slice = order.slice(i, i + group.statuses.length);
        if (
            slice.length === group.statuses.length &&
            slice.every((s, k) => s === group.statuses[k])
        ) {
            return group;
        }
    }
    return null;
}
