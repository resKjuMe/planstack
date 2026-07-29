import { useEffect, useState } from 'react';

// Gemeinsamer Zeittakt für alles, was allein durch Zeitverlauf veraltet (aktuell:
// der Heartbeat der Claim-Session). EIN Interval für das ganze Board statt eines
// pro Karte — bei >100 Karten wäre je Karte ein Timer messbarer Ballast, und
// alle sollen ohnehin im selben Takt umschalten.
//
// Bewusst kein Server-Event: der Ablauf einer Session ist kein Ereignis, das
// irgendwer melden könnte (ein hart gekillter Worker meldet nichts mehr) — er
// ergibt sich rein aus dem Alter von claim_seen_at.

const TICK_MS = 30_000;

const listeners = new Set();
let timer = null;
let current = Date.now();

function tick() {
    current = Date.now();
    for (const listener of listeners) {
        listener(current);
    }
}

/** Aktueller Zeitstempel (ms), der sich alle 30 s aktualisiert. */
export function useNow() {
    const [now, setNow] = useState(current);

    useEffect(() => {
        listeners.add(setNow);
        if (timer === null) {
            timer = setInterval(tick, TICK_MS);
        }

        return () => {
            listeners.delete(setNow);
            if (listeners.size === 0 && timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        };
    }, []);

    return now;
}
