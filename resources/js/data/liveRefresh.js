// Zentraler „Live-Refresh"-Bus: bündelt die beiden Auslöser, auf die die Daten-
// Loader reagieren — Entity-Änderungen (Socket) und Reconnect (nach getrennter
// Pusher-Phase). Statt dass jeder Loader eigene window-Listener registriert,
// abonniert er hier — so liegt die Refresh-Politik an EINER Stelle.

const entityHandlers = new Set();
const reconnectHandlers = new Set();

/** Wird mit dem entity-changed-Detail aufgerufen. Gibt eine Abmelde-Funktion zurück. */
export function onEntityChanged(fn) {
    entityHandlers.add(fn);
    return () => entityHandlers.delete(fn);
}

/** Wird nach einem Reconnect (verpasste Events möglich) aufgerufen. */
export function onReconnected(fn) {
    reconnectHandlers.add(fn);
    return () => reconnectHandlers.delete(fn);
}

if (typeof window !== 'undefined') {
    window.addEventListener('planstack:entity-changed', (e) => {
        for (const fn of entityHandlers) fn(e.detail);
    });
    window.addEventListener('planstack:reconnected', () => {
        for (const fn of reconnectHandlers) fn();
    });
}
