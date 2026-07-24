// Board API calls. The initial task list is READ from the REST API
// (GET /api/projects/{alias}); the drag-and-drop status change is still a
// same-origin fetch against the web move endpoint (it returns JSON). The
// claim/release buttons stay plain HTML form POSTs that reload the page (see
// TaskCard), matching the pre-React behaviour.

/**
 * Read the board's tasks over the REST API. Same-origin, so the browser's
 * session cookie authenticates it (Sanctum stateful) — no bearer token.
 * `?fields=full` forces the full task field set regardless of the project's
 * token-saving `task.fields` knob. Resolves to { ok, tasks } or { ok:false,
 * message }. The API wraps a resource in `data`, hence data.data.tasks.
 */
export async function fetchBoardTasks(projectAlias) {
    try {
        const res = await fetch(`/api/projects/${encodeURIComponent(projectAlias)}?fields=full`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const body = await res.json().catch(() => ({}));

        if (!res.ok) {
            return { ok: false, message: body.message || `HTTP ${res.status}` };
        }
        return { ok: true, tasks: body.data?.tasks ?? [] };
    } catch (e) {
        return { ok: false, message: e?.message || 'Network error' };
    }
}

/**
 * Map an API task (TaskResource, fields=full, snake_case) to the flat shape the
 * React board/TaskCard consume. isBlocked/isConcerned are derived from the
 * task's display status and the org's role→key map (meta.roleKeys); the card
 * link is built from the templated task endpoint (the API shape omits it).
 */
export function mapApiTask(apiTask, meta) {
    const roleKeys = meta.roleKeys ?? {};
    const displayStatus = apiTask.display_status;
    const url = meta.endpoints?.task
        ? meta.endpoints.task.replace('__TASK__', String(apiTask.id))
        : '#';

    // "Stacked": the task depends on one or more parent tasks that aren't done
    // yet, so its branch/PR sits on top of unfinished work. A parent counts as
    // resolved once it's MERGED or COMPLETED; both are matched by role (fallback
    // to the canonical key).
    const doneKeys = [roleKeys.MERGED ?? 'MERGED', roleKeys.COMPLETED ?? 'COMPLETED'];
    const prerequisites = Array.isArray(apiTask.prerequisites) ? apiTask.prerequisites : [];
    const unmergedParents = prerequisites.filter((p) => ! doneKeys.includes(p.status));

    return {
        id: apiTask.id,
        name: apiTask.name,
        summary: apiTask.summary,
        displayStatus,
        claimerId: apiTask.claimed_by_id ?? null,
        claimerName: apiTask.claimed_by ?? null,
        reviewerId: apiTask.reviewed_by ?? null,
        reviewerName: apiTask.reviewed_by_name ?? null,
        storyPoints: Number(apiTask.effort?.story_points ?? 0),
        prNumber: apiTask.pr_number ?? null,
        prUrl: apiTask.pr_url ?? null,
        // Von GitHub gepollter PR-Zustand (nur gesetzt, wenn ein PR existiert und
        // der Sync gelaufen ist): CI-Rollup + Anzahl unresolved Review-Threads.
        ciStatus: apiTask.pr_ci_status ?? null,
        unresolvedThreads: apiTask.pr_unresolved_threads ?? null,
        mergedAt: apiTask.merged_at ?? null,
        url,
        isBlocked: displayStatus === roleKeys.BLOCKED,
        isConcerned: displayStatus === roleKeys.CONCERNED,
        isInReview: displayStatus === roleKeys.IN_REVIEW,
        // APPROVED is a custom (role-less) status, so it's matched by its key —
        // same way MERGED is referenced directly in the board.
        isApproved: displayStatus === 'APPROVED',
        // Stacked on not-yet-merged parents (see above); the names feed the tooltip.
        isStacked: unmergedParents.length > 0,
        stackedOn: unmergedParents.map((p) => p.name),
        concernSummary: apiTask.concern?.summary || apiTask.concern?.blocker || null,
    };
}

/**
 * POST a status change for a task. Resolves to { ok, task } on success or
 * { ok: false, message } on a rejected/failed transition (HTTP 422 or other).
 */
export async function moveTask({ endpoints, csrf }, taskId, status) {
    const url = endpoints.move.replace('__TASK__', String(taskId));

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            return { ok: false, message: data.message || `HTTP ${res.status}` };
        }
        return { ok: true, task: data.task };
    } catch (e) {
        return { ok: false, message: e?.message || 'Network error' };
    }
}
