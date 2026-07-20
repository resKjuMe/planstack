// Board API calls. Only the drag-and-drop status change is done via fetch (it
// returns JSON); the claim/release buttons stay plain HTML form POSTs that
// reload the page (see TaskCard), matching the pre-React behaviour.

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
