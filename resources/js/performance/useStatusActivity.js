import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { onEntityChanged, onReconnected } from '../data/liveRefresh';

// Gecachter Loader für die Aktivitäts-Heatmap (Statusupdates je Tag/Stunde). Wie der
// Changelog-Feed ein eigener Endpunkt statt einer Ableitung aus dem Tasks-Store: die
// Zeitpunkte der Statuswechsel stehen im Änderungsprotokoll, nicht am Task.
//
// Geladen wird EINMAL je Endpunkt das größte Fenster, das die Ansicht zeigen kann —
// die kürzeren Zeiträume schneidet der Client daraus zu (kein Netzzugriff beim
// Umschalten). Der Cache hängt an der URL, weil dieselbe Karte drei Geltungsbereiche
// bedient (Projekt, Organisation, eigene Updates). Die Zeitzone des Browsers geht mit,
// weil der Server in DIESER Zone buckettet (siehe StatusActivityPresenter).
//
// Ein entity-changed-Event lädt neu, aber nur wenn die Heatmap gerade sichtbar ist;
// sonst wird sie als stale markiert und beim nächsten Öffnen frisch geholt. Ist die
// Karte auf ein Projekt bezogen, zählt nur dessen Alias — projektübergreifende
// Ansichten reagieren auf jede Task-Änderung.

export const ACTIVITY_DAYS = 182;

const slices = new Map();

function timezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch {
        return '';
    }
}

function getSlice(url, alias = null) {
    let s = slices.get(url);
    if (!s) {
        s = {
            url,
            alias,
            payload: null,
            status: 'idle', // idle | loading | ready | error
            error: null,
            stale: false,
            listeners: new Set(),
            snapshot: null,
            seq: 0,
        };
        slices.set(url, s);
    }
    return s;
}

function rebuild(s) {
    s.snapshot = { payload: s.payload, status: s.status, error: s.error };
}

function notify(s) {
    rebuild(s);
    for (const l of s.listeners) l();
}

async function load(endpoint) {
    if (!endpoint) return;
    const s = getSlice(endpoint);
    const token = ++s.seq;

    if (s.status !== 'ready') {
        s.status = 'loading';
        s.error = null;
        notify(s);
    }

    try {
        const url =
            `${endpoint}?days=${ACTIVITY_DAYS}&tz=${encodeURIComponent(timezone())}`;
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const body = await res.json().catch(() => ({}));
        if (token !== s.seq) return; // veraltet
        if (!res.ok) throw new Error(body.message || `HTTP ${res.status}`);

        s.payload = body;
        s.status = 'ready';
        s.error = null;
        notify(s);
    } catch (e) {
        if (token !== s.seq) return;
        s.status = s.status === 'ready' ? 'ready' : 'error';
        s.error = e?.message || 'Ladefehler';
        notify(s);
    }
}

function refreshSlice(s) {
    if (s.status === 'idle') return;
    if (s.listeners.size > 0) {
        s.stale = false;
        load(s.url);
    } else {
        s.stale = true;
    }
}

onEntityChanged((d) => {
    if (!d) return;
    for (const s of slices.values()) {
        // Projektbezogene Karte: nur das eigene Projekt. Projektübergreifende
        // (Organisation, eigene Updates): jede Task-Änderung kann sie betreffen.
        if (s.alias === null || s.alias === d.project_alias) refreshSlice(s);
    }
});
onReconnected(() => {
    for (const s of slices.values()) refreshSlice(s);
});

/**
 * @param {string} url    Endpunkt ohne Query (siehe StatusActivityController)
 * @param {?string} alias Projekt-Alias, wenn die Karte auf EIN Projekt zeigt
 */
export function useStatusActivity(url, alias = null) {
    useEffect(() => {
        const s = getSlice(url, alias);
        if (s.status === 'idle' || s.stale) {
            s.stale = false;
            load(url);
        }
    }, [url, alias]);

    const sub = useCallback((cb) => {
        const s = getSlice(url, alias);
        s.listeners.add(cb);
        return () => s.listeners.delete(cb);
    }, [url, alias]);
    const snap = useCallback(() => {
        const s = getSlice(url, alias);
        if (!s.snapshot) rebuild(s);
        return s.snapshot;
    }, [url, alias]);

    return useSyncExternalStore(sub, snap, snap);
}
