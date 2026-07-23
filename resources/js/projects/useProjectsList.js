import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { fetchProjectsOverview } from '../data/projectApi';
import { ensureStatusConfig } from '../data/projectStore';

// Gecachter Loader für die Projektübersicht: lädt die kompakte Aggregat-Übersicht
// (GET /api/projects/overview) + die org-weite Status-Konfiguration (einmalig,
// geteilt mit den Unterseiten). Die Karten leitet die View clientseitig aus den
// Aggregaten ab. Bei jeder Entity-Änderung (Project insert/update/delete sowie
// Task-/Phasen-Änderungen) wird entprellt neu geladen. Eine org-weite Liste → ein
// Slice ohne Key.

const slice = {
    projects: [],
    statusConfig: null,
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
        statusConfig: slice.statusConfig,
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
        const [overview, statusConfig] = await Promise.all([
            fetchProjectsOverview(),
            ensureStatusConfig(),
        ]);
        if (token !== slice.seq) return; // veraltet
        slice.projects = overview.projects ?? [];
        slice.statusConfig = statusConfig;
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
// nachladen. Nur, wenn die Liste schon einmal geladen wurde.
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
