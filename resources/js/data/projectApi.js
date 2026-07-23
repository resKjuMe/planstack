// REST-Zugriffe für den geteilten Projekt-Store. Alle same-origin (Session-Cookie,
// Sanctum-stateful) — kein Bearer-Token. `?fields=full` erzwingt den vollen
// Task-Feldumfang unabhängig vom token-sparenden Projekt-Knopf `task.fields`.

const JSON_HEADERS = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

async function getJson(url) {
    const res = await fetch(url, { headers: JSON_HEADERS, credentials: 'same-origin' });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(body.message || `HTTP ${res.status}`);
    }
    return body;
}

/** Alle (dekorierten) Tasks eines Projekts. Die API kapselt Resources in `data`. */
export async function fetchProjectTasks(alias) {
    const body = await getJson(`/api/projects/${encodeURIComponent(alias)}?fields=full`);
    return body.data?.tasks ?? [];
}

/** Ein einzelner Task (für das partielle Nachladen nach einem entity-changed-Event). */
export async function fetchTask(alias, id) {
    const body = await getJson(
        `/api/projects/${encodeURIComponent(alias)}/tasks/${encodeURIComponent(id)}?fields=full`,
    );
    return body.data ?? null;
}

/** Phasen des Projekts (id, name, position), nach Position geordnet. */
export async function fetchPhases(alias) {
    const body = await getJson(`/api/projects/${encodeURIComponent(alias)}/phases`);
    return body.data ?? [];
}

/**
 * ORG-weite Status-Konfiguration ({ statuses, roleKey }) — einmal laden, über alle
 * Projekte/Unterseiten wiederverwenden. Kein Resource-Wrapper (Objekt direkt).
 */
export async function fetchStatusConfig() {
    return getJson('/api/status-config');
}

/** Alle zugänglichen Projekte (org-weit) — schlanke ProjectResource-Collection. */
export async function fetchProjects() {
    const body = await getJson('/api/projects');
    return body.data ?? [];
}

/** Alle Tasks der zugänglichen Projekte (org-weit), voller Feldumfang. */
export async function fetchAllTasks() {
    const body = await getJson('/api/tasks?fields=full');
    return body.data ?? [];
}
