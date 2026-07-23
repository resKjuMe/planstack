import { useCallback, useEffect, useSyncExternalStore } from 'react';

// Gecachter Loader für die Kalibrierungs-Daten (PR-basiert, serverseitig berechnet).
// Gleiche Mechanik wie der Tasks-Store: einmal je Projekt laden, über die Navigation
// cachen (0 Calls beim Tab-Wechsel) und bei einem entity-changed-Event des Projekts
// still neu laden. Eigener Slice, da die Daten nicht im Tasks-Store liegen.

const slices = new Map();

function getSlice(alias) {
    let s = slices.get(alias);
    if (!s) {
        s = { alias, data: null, status: 'idle', error: null, listeners: new Set(), snapshot: null, seq: 0 };
        slices.set(alias, s);
    }
    return s;
}

function rebuild(s) {
    s.snapshot = { data: s.data, status: s.status, error: s.error };
}

function notify(s) {
    rebuild(s);
    for (const l of s.listeners) l();
}

async function load(alias, { force = false } = {}) {
    if (!alias) return;
    const s = getSlice(alias);
    if (!force && (s.status === 'loading' || s.status === 'ready')) return;

    const token = ++s.seq;
    // Beim stillen Refresh (force auf bereits geladenem Slice) den Status auf
    // 'ready' lassen, damit die Ansicht nicht flackert.
    if (s.status !== 'ready') {
        s.status = 'loading';
        s.error = null;
        notify(s);
    }

    try {
        const res = await fetch(`/api/projects/${encodeURIComponent(alias)}/calibration`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const body = await res.json().catch(() => ({}));
        if (token !== s.seq) return; // veralteter Response
        if (!res.ok) throw new Error(body.message || `HTTP ${res.status}`);
        s.data = body;
        s.status = 'ready';
        s.error = null;
        notify(s);
    } catch (e) {
        if (token !== s.seq) return;
        s.status = 'error';
        s.error = e?.message || 'Ladefehler';
        notify(s);
    }
}

export function ensureCalibration(alias) {
    load(alias);
}

if (typeof window !== 'undefined') {
    window.addEventListener('planstack:entity-changed', (e) => {
        const d = e.detail;
        if (!d || !d.project_alias) return;
        const s = slices.get(d.project_alias);
        if (s && s.status === 'ready') load(d.project_alias, { force: true });
    });
}

export function useCalibration(alias) {
    useEffect(() => {
        ensureCalibration(alias);
    }, [alias]);

    const sub = useCallback((cb) => {
        const s = getSlice(alias);
        s.listeners.add(cb);
        return () => s.listeners.delete(cb);
    }, [alias]);
    const snap = useCallback(() => {
        const s = getSlice(alias);
        if (!s.snapshot) rebuild(s);
        return s.snapshot;
    }, [alias]);

    return useSyncExternalStore(sub, snap, snap);
}
