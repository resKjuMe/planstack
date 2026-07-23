import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { onEntityChanged, onReconnected } from '../data/liveRefresh';

// Gecachter, paginierter Loader für den Changelog-Feed (serverseitig aufbereitet,
// Audit-Log-basiert — nicht aus dem Tasks-Store ableitbar). Seite 1 wird beim ersten
// Öffnen geladen und über die Navigation gecacht (0 Calls beim Tab-Wechsel);
// „Mehr laden" hängt weitere Seiten an. Ein entity-changed-Event (oder Reconnect)
// setzt auf die neueste Seite 1 zurück — aber nur, wenn der Feed gerade sichtbar
// ist; sonst wird er als stale markiert und beim nächsten Öffnen neu geladen.

const slices = new Map();

function getSlice(alias) {
    let s = slices.get(alias);
    if (!s) {
        s = {
            alias,
            items: [],
            loadedPage: 0,
            hasMore: false,
            status: 'idle', // idle | loading | ready | error
            loadingMore: false,
            error: null,
            stale: false,
            listeners: new Set(),
            snapshot: null,
            seq: 0,
        };
        slices.set(alias, s);
    }
    return s;
}

function rebuild(s) {
    s.snapshot = {
        items: s.items,
        status: s.status,
        loadingMore: s.loadingMore,
        hasMore: s.hasMore,
        error: s.error,
    };
}

function notify(s) {
    rebuild(s);
    for (const l of s.listeners) l();
}

async function fetchPage(alias, page, { reset }) {
    if (!alias) return;
    const s = getSlice(alias);
    const token = ++s.seq;

    if (reset && s.status !== 'ready') {
        s.status = 'loading';
        s.error = null;
        notify(s);
    } else if (!reset) {
        s.loadingMore = true;
        notify(s);
    }

    try {
        const res = await fetch(`/api/projects/${encodeURIComponent(alias)}/changelog?page=${page}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const body = await res.json().catch(() => ({}));
        if (token !== s.seq) return; // veraltet
        if (!res.ok) throw new Error(body.message || `HTTP ${res.status}`);

        const items = body.items ?? [];
        s.items = reset ? items : [...s.items, ...items];
        s.loadedPage = body.pagination?.currentPage ?? page;
        s.hasMore = !!body.pagination?.hasMore;
        s.status = 'ready';
        s.loadingMore = false;
        s.error = null;
        notify(s);
    } catch (e) {
        if (token !== s.seq) return;
        s.status = s.status === 'ready' ? 'ready' : 'error';
        s.loadingMore = false;
        s.error = e?.message || 'Ladefehler';
        notify(s);
    }
}

export function ensureChangelog(alias) {
    const s = getSlice(alias);
    if (s.status === 'idle') {
        fetchPage(alias, 1, { reset: true });
    } else if (s.stale && s.listeners.size > 0) {
        s.stale = false;
        fetchPage(alias, 1, { reset: true });
    }
}

export function loadMoreChangelog(alias) {
    const s = getSlice(alias);
    if (s.status === 'ready' && s.hasMore && !s.loadingMore) {
        fetchPage(alias, s.loadedPage + 1, { reset: false });
    }
}

// Sichtbar → auf die neueste Seite 1 zurücksetzen; sonst nur als stale markieren
// und beim nächsten Öffnen laden (kein Hintergrund-Refetch).
function refreshSlice(s) {
    if (s.status === 'idle') return;
    if (s.listeners.size > 0) {
        s.stale = false;
        fetchPage(s.alias, 1, { reset: true });
    } else {
        s.stale = true;
    }
}

onEntityChanged((d) => {
    if (!d || !d.project_alias) return;
    const s = slices.get(d.project_alias);
    if (s) refreshSlice(s);
});
onReconnected(() => {
    for (const s of slices.values()) refreshSlice(s);
});

export function useChangelog(alias) {
    useEffect(() => {
        ensureChangelog(alias);
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

    const state = useSyncExternalStore(sub, snap, snap);
    return { ...state, loadMore: () => loadMoreChangelog(alias) };
}
