import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { onEntityChanged, onReconnected } from './liveRefresh';

// Fabrik für einen gecachten Einzel-Wert-Store mit Live-Refresh. Kapselt das sonst
// mehrfach wiederholte Muster: laden + cachen + useSyncExternalStore + Refresh bei
// entity-changed/Reconnect. Refresh-Politik: ist die Ressource sichtbar (≥1
// Subscriber), wird entprellt nachgeladen; ist sie es nicht, wird sie nur als
// „stale" markiert und beim nächsten Mount neu geladen (kein Hintergrund-Refetch).
//
// Für einfache Ressourcen (z. B. Projektübersicht). Komplexere Stores (normalisiert/
// paginiert) hängen direkt am liveRefresh-Bus.
export function createCachedResource({ load, shouldRefresh = null, debounceMs = 400 }) {
    const s = {
        data: null,
        status: 'idle', // idle | loading | ready | error
        error: null,
        listeners: new Set(),
        snapshot: null,
        seq: 0,
        stale: false,
        timer: null,
    };

    function rebuild() {
        s.snapshot = { data: s.data, status: s.status, error: s.error };
    }
    function notify() {
        rebuild();
        for (const l of s.listeners) l();
    }

    async function run() {
        const token = ++s.seq;
        if (s.status !== 'ready') {
            s.status = 'loading';
            s.error = null;
            notify();
        }
        try {
            const data = await load();
            if (token !== s.seq) return;
            s.data = data;
            s.status = 'ready';
            s.error = null;
            s.stale = false;
            notify();
        } catch (e) {
            if (token !== s.seq) return;
            s.status = s.status === 'ready' ? 'ready' : 'error';
            s.error = e?.message || 'Ladefehler';
            notify();
        }
    }

    function ensure() {
        if (s.status === 'idle') run();
        else if (s.stale && s.listeners.size > 0) run();
    }

    function scheduleRefresh() {
        if (s.status === 'idle') return; // nie geladen → nichts zu aktualisieren
        if (s.listeners.size === 0) {
            s.stale = true; // nicht sichtbar → lazy beim nächsten Mount
            return;
        }
        if (s.timer) return;
        s.timer = setTimeout(() => {
            s.timer = null;
            run();
        }, debounceMs);
    }

    onEntityChanged((detail) => {
        if (!shouldRefresh || shouldRefresh(detail)) scheduleRefresh();
    });
    onReconnected(() => scheduleRefresh());

    return function useResource() {
        useEffect(() => {
            ensure();
        }, []);
        const sub = useCallback((cb) => {
            s.listeners.add(cb);
            return () => s.listeners.delete(cb);
        }, []);
        const snap = useCallback(() => {
            if (!s.snapshot) rebuild();
            return s.snapshot;
        }, []);
        return useSyncExternalStore(sub, snap, snap);
    };
}
