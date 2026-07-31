import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { onEntityChanged, onReconnected } from '../data/liveRefresh';

// Gecachter Loader für den Status-VERLAUF eines Projekts (GET /api/projects/{alias}/
// timeline). Bewusst neben dem geteilten Tasks-Store: der Verlauf kommt aus dem
// Änderungsprotokoll und lässt sich nicht aus den Tasks ableiten — dafür wird er
// nur von dieser Unterseite gebraucht und deshalb erst beim Öffnen geladen.
//
// Refresh-Politik wie beim Changelog: sichtbar → entprellt nachladen, unsichtbar →
// nur als „stale" markieren und beim nächsten Öffnen frisch holen.

const DEBOUNCE_MS = 600;

const slices = new Map();

function getSlice(alias) {
    let s = slices.get(alias);
    if (!s) {
        s = {
            alias,
            data: null,
            status: 'idle', // idle | loading | ready | error
            error: null,
            stale: false,
            timer: null,
            listeners: new Set(),
            snapshot: null,
            seq: 0,
        };
        slices.set(alias, s);
    }
    return s;
}

function rebuild(s) {
    s.snapshot = { history: s.data, status: s.status, error: s.error };
}

function notify(s) {
    rebuild(s);
    for (const l of s.listeners) l();
}

async function load(alias, days) {
    if (!alias) return;
    const s = getSlice(alias);
    const token = ++s.seq;

    // Ein Nachladen darf die bereits gezeichnete Achse nicht durch einen Skeleton
    // ersetzen — nur der erste Ladevorgang zeigt „lädt".
    if (s.status !== 'ready') {
        s.status = 'loading';
        s.error = null;
        notify(s);
    }

    try {
        const res = await fetch(`/api/projects/${encodeURIComponent(alias)}/timeline?days=${days}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const body = await res.json().catch(() => ({}));
        if (token !== s.seq) return; // veraltet
        if (!res.ok) throw new Error(body.message || `HTTP ${res.status}`);

        s.data = body;
        s.status = 'ready';
        s.error = null;
        s.stale = false;
        notify(s);
    } catch (e) {
        if (token !== s.seq) return;
        s.status = s.status === 'ready' ? 'ready' : 'error';
        s.error = e?.message || 'Ladefehler';
        notify(s);
    }
}

export function ensureTimeline(alias, days) {
    const s = getSlice(alias);
    if (s.status === 'idle' || (s.stale && s.listeners.size > 0)) {
        s.stale = false;
        load(alias, days);
    }
}

function refreshSlice(s, days) {
    if (s.status === 'idle') return; // nie geladen → nichts zu aktualisieren
    if (s.listeners.size === 0) {
        s.stale = true;
        return;
    }
    if (s.timer) return;
    s.timer = setTimeout(() => {
        s.timer = null;
        load(s.alias, days);
    }, DEBOUNCE_MS);
}

// Jeder Statuswechsel verschiebt einen Balken — deshalb reagiert die Achse auf
// Task-Änderungen des eigenen Projekts.
onEntityChanged((d) => {
    if (!d || !d.project_alias || d.entity !== 'task') return;
    const s = slices.get(d.project_alias);
    if (s) refreshSlice(s, s.data?.days ?? 30);
});
onReconnected(() => {
    for (const s of slices.values()) refreshSlice(s, s.data?.days ?? 30);
});

/** Liefert { history, status, error } für `alias`. */
export function useTaskTimeline(alias, days = 30) {
    useEffect(() => {
        ensureTimeline(alias, days);
    }, [alias, days]);

    const sub = useCallback(
        (cb) => {
            const s = getSlice(alias);
            s.listeners.add(cb);
            return () => s.listeners.delete(cb);
        },
        [alias],
    );
    const snap = useCallback(() => {
        const s = getSlice(alias);
        if (!s.snapshot) rebuild(s);
        return s.snapshot;
    }, [alias]);

    return useSyncExternalStore(sub, snap, snap);
}
