import { useCallback, useEffect, useSyncExternalStore } from 'react';

// Gecachter Loader für die Projektübersicht: lädt die Karten einmalig über
// GET /api/projects?view=cards und aktualisiert sich live, wenn irgendein Projekt
// (oder ein Task/eine Phase darin) sich ändert — die entity-changed-Events lösen
// einen entprellten Refetch aus (neue/gelöschte Projekte, Fortschritt, Segmente).
// Eine einzige org-weite Liste → ein Slice ohne Alias-Key.

const slice = {
    projects: [],
    summaryLine: '',
    status: 'idle', // idle | loading | ready | error
    error: null,
    listeners: new Set(),
    snapshot: null,
    seq: 0,
    timer: null,
};

function rebuild() {
    slice.snapshot = {
        projects: slice.projects,
        summaryLine: slice.summaryLine,
        status: slice.status,
        error: slice.error,
    };
}

function notify() {
    rebuild();
    for (const l of slice.listeners) l();
}

async function load() {
    const token = ++slice.seq;
    if (slice.status !== 'ready') {
        slice.status = 'loading';
        slice.error = null;
        notify();
    }

    try {
        const res = await fetch('/api/projects?view=cards', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const body = await res.json().catch(() => ({}));
        if (token !== slice.seq) return; // veraltet
        if (!res.ok) throw new Error(body.message || `HTTP ${res.status}`);
        slice.projects = body.projects ?? [];
        slice.summaryLine = body.summaryLine ?? '';
        slice.status = 'ready';
        slice.error = null;
        notify();
    } catch (e) {
        if (token !== slice.seq) return;
        slice.status = slice.status === 'ready' ? 'ready' : 'error';
        slice.error = e?.message || 'Ladefehler';
        notify();
    }
}

export function ensureProjectsList() {
    if (slice.status === 'idle') load();
}

// entity-changed feuert je Änderung (auch in Bursts) — entprellt sammeln und still
// nachladen. Nur, wenn die Liste schon einmal geladen wurde (sonst kein Bedarf).
if (typeof window !== 'undefined') {
    window.addEventListener('planstack:entity-changed', () => {
        if (slice.status === 'idle') return;
        if (slice.timer) return;
        slice.timer = setTimeout(() => {
            slice.timer = null;
            load();
        }, 400);
    });
}

export function useProjectsList() {
    useEffect(() => {
        ensureProjectsList();
    }, []);

    const sub = useCallback((cb) => {
        slice.listeners.add(cb);
        return () => slice.listeners.delete(cb);
    }, []);
    const snap = useCallback(() => {
        if (!slice.snapshot) rebuild();
        return slice.snapshot;
    }, []);

    return useSyncExternalStore(sub, snap, snap);
}
