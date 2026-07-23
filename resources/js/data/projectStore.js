// Geteilter, normalisierter Projekt-Store (Single Source of Truth für Tasks +
// Phasen + Org-Status-Konfiguration). Als Module-Singleton lebt er außerhalb des
// Inertia-Seiten-Lebenszyklus und überlebt damit die Navigation zwischen den
// Projekt-Unterseiten (Board ↔ Summary). Folge: Daten werden nur beim ERSTEN
// Aufruf eines Projekts geladen; danach navigiert man beliebig hin und her, ohne
// erneut zu laden. Änderungen kommen als Socket-Event `planstack:entity-changed`
// (siehe resources/js/app.js) herein und werden PARTIELL nachgeladen — es wird
// immer nur die betroffene Entity neu geholt, nie das ganze Board.
//
// Board und Summary konsumieren denselben Store über useProjectData(alias).

import { fetchProjectTasks, fetchTask, fetchPhases, fetchStatusConfig } from './projectApi';
import { onEntityChanged, onReconnected } from './liveRefresh';

/** @type {Map<string, object>} alias → slice */
const slices = new Map();

// ORG-weite Status-Konfiguration: einmal laden, über alle Projekte/Unterseiten
// UND die Projektübersicht teilen (Punkt 3: in Unterseiten nicht neu laden, wenn
// bereits gesetzt).
let sharedStatusConfig = null;
let statusConfigPromise = null;

export function getStatusConfig() {
    return sharedStatusConfig;
}

export function ensureStatusConfig() {
    if (sharedStatusConfig) return Promise.resolve(sharedStatusConfig);
    if (!statusConfigPromise) {
        statusConfigPromise = fetchStatusConfig().then((cfg) => {
            sharedStatusConfig = cfg;
            return cfg;
        });
    }
    return statusConfigPromise;
}

function createSlice(alias) {
    return {
        alias,
        tasks: new Map(), // id → apiTask (snake_case, TaskResource fields=full)
        phases: [],
        statusConfig: null, // { statuses, roleKey }
        status: 'idle', // idle | loading | ready | error
        error: null,
        listeners: new Set(),
        snapshot: null, // stabile Referenz für useSyncExternalStore
    };
}

function getSlice(alias) {
    let s = slices.get(alias);
    if (!s) {
        s = createSlice(alias);
        slices.set(alias, s);
    }
    return s;
}

// useSyncExternalStore verlangt, dass getSnapshot() bei unverändertem Zustand
// dieselbe Referenz liefert. Darum wird der Snapshot nur bei echten Änderungen
// (in notify) neu gebaut.
function rebuildSnapshot(s) {
    s.snapshot = {
        alias: s.alias,
        tasks: Array.from(s.tasks.values()),
        phases: s.phases,
        statusConfig: s.statusConfig,
        status: s.status,
        error: s.error,
    };
}

function notify(s) {
    rebuildSnapshot(s);
    for (const l of s.listeners) l();
}

export function getSnapshot(alias) {
    const s = getSlice(alias);
    if (s.snapshot === null) rebuildSnapshot(s);
    return s.snapshot;
}

export function subscribe(alias, cb) {
    const s = getSlice(alias);
    s.listeners.add(cb);
    return () => s.listeners.delete(cb);
}

/**
 * Einmalig Tasks + Phasen + Status-Konfiguration laden. Mehrfachaufrufe (z. B.
 * beim erneuten Mount einer bereits besuchten Seite) sind No-ops, solange bereits
 * geladen wird oder geladen wurde.
 */
export async function ensureLoaded(alias) {
    if (!alias) return;
    const s = getSlice(alias);
    if (s.status === 'loading' || s.status === 'ready') return;

    s.status = 'loading';
    s.error = null;
    notify(s);

    try {
        const [tasks, phases, statusConfig] = await Promise.all([
            fetchProjectTasks(alias),
            fetchPhases(alias),
            ensureStatusConfig(),
        ]);
        s.tasks = new Map(tasks.map((t) => [t.id, t]));
        s.phases = phases;
        s.statusConfig = statusConfig;
        s.status = 'ready';
        notify(s);
    } catch (e) {
        s.status = 'error';
        s.error = e?.message || 'Ladefehler';
        notify(s);
    }
}

/**
 * Optimistisches lokales Patchen eines Tasks (Drag-and-drop). Merged `partial` in
 * den gespeicherten API-Task und gibt den Zustand VOR dem Patch zurück (für den
 * Rollback), oder null, wenn der Task unbekannt ist.
 */
export function patchTask(alias, id, partial) {
    const s = getSlice(alias);
    const prev = s.tasks.get(id);
    if (!prev) return null;
    s.tasks.set(id, { ...prev, ...partial });
    notify(s);
    return prev;
}

// --- Socket-getriebenes partielles Nachladen -----------------------------------

async function applyEntityChange(detail) {
    if (!detail || !detail.project_alias) return;
    const s = slices.get(detail.project_alias);
    // Nichts geladen → nichts nachzuladen (die Seite lädt beim nächsten Besuch frisch).
    if (!s || s.status !== 'ready') return;

    console.debug('[store] entity-changed → partielles Nachladen:', detail);

    if (detail.entity === 'task') {
        // 'delete' (Server) bzw. Legacy 'deleted' → aus dem Store entfernen.
        if (detail.action === 'delete' || detail.action === 'deleted') {
            if (s.tasks.has(detail.id)) {
                s.tasks.delete(detail.id);
                notify(s);
            }
            return;
        }
        try {
            const task = await fetchTask(s.alias, detail.id);
            if (task) {
                s.tasks.set(task.id, task);
                notify(s);
            }
        } catch {
            /* best effort — ein verpasstes Update wird beim nächsten Voll-Load korrigiert */
        }
    } else if (detail.entity === 'phase') {
        try {
            s.phases = await fetchPhases(s.alias);
            notify(s);
        } catch {
            /* best effort */
        }
    }
    // 'project' (und künftige projektweite Typen) behandelt die Seite selbst
    // (z. B. ProjectWorkspace via Inertia-Partial-Reload) — der Store hält keine
    // Projekt-Stammdaten.
}

// Nach einem Reconnect können Socket-Events verpasst worden sein → geladene Slices
// still voll nachladen (Tasks + Phasen; die org-weite statusConfig ändert sich
// praktisch nie und bleibt gecacht).
async function reloadSlice(s) {
    try {
        const [tasks, phases] = await Promise.all([fetchProjectTasks(s.alias), fetchPhases(s.alias)]);
        s.tasks = new Map(tasks.map((t) => [t.id, t]));
        s.phases = phases;
        notify(s);
    } catch {
        /* best effort */
    }
}

onEntityChanged((detail) => applyEntityChange(detail));
onReconnected(() => {
    for (const s of slices.values()) {
        if (s.status === 'ready') reloadSlice(s);
    }
});
