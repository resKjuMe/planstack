import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { ensureLoaded, getSnapshot, subscribe } from './projectStore';

/**
 * React-Anbindung an den geteilten Projekt-Store. Stellt sicher, dass die Daten
 * (Tasks + Phasen + Status-Konfiguration) für `alias` einmalig geladen sind, und
 * abonniert Änderungen. Liefert { alias, tasks, phases, statusConfig, status,
 * error }. `tasks` sind die rohen API-Tasks (snake_case) — die Seiten leiten ihre
 * jeweilige Ansicht clientseitig daraus ab.
 */
export function useProjectData(alias) {
    useEffect(() => {
        ensureLoaded(alias);
    }, [alias]);

    const sub = useCallback((cb) => subscribe(alias, cb), [alias]);
    const snap = useCallback(() => getSnapshot(alias), [alias]);

    return useSyncExternalStore(sub, snap, snap);
}
