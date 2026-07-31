import { useMemo, useState } from 'react';

// Geteilter Filter-Zustand der beiden Abhängigkeits-Ansichten (Diagramm und
// Zeitachse). Sie teilen einen Reiter und zeigen dieselben Tasks — dann müssen sie
// auch dieselben Filter zeigen UND behalten: wer im Diagramm „gemerged" ausblendet
// und auf die Zeitachse wechselt, will dort nicht wieder alles sehen.
//
// Ablage: ausgeblendete Status dauerhaft (localStorage, wie bisher im Diagramm),
// die Phasen-Auswahl nur für die Sitzung (sessionStorage) — sie ist ein kurzlebiger
// Blick auf einen Abschnitt, kein Dauerzustand, muss den Ansichtswechsel aber
// überleben, weil der die Komponente neu aufbaut.

// Bewusst NICHT hier: die „Kurzbeschreibungen"-Checkbox des Diagramms. Sie ist eine
// Darstellungs-Option (was ein Knoten zeigt), kein Filter (welche Tasks zu sehen
// sind) — sie bleibt beim Diagramm.
const HIDDEN_KEY = 'ps-diagram-hidden-statuses';
const PHASE_KEY = 'ps-dep-phase-filter';

function readHidden() {
    try {
        const raw = localStorage.getItem(HIDDEN_KEY);
        return new Set(raw ? JSON.parse(raw) : []);
    } catch {
        return new Set();
    }
}

function readPhase() {
    try {
        return sessionStorage.getItem(PHASE_KEY) || null;
    } catch {
        return null;
    }
}

function persist(store, key, value) {
    try {
        if (value === null) store.removeItem(key);
        else store.setItem(key, value);
    } catch {
        /* ignore */
    }
}

/**
 * @returns {{
 *   hiddenStatuses: Set<string>, toggleStatus: (key: string) => void,
 *   phaseFilter: string|null, togglePhase: (id: number|string) => void,
 *   isFiltered: boolean,
 *   statusFiltersFor: (legend: Array, presentKeys: Set<string>) => Array,
 * }}
 */
export function useDependencyFilters() {
    const [hiddenStatuses, setHiddenStatuses] = useState(readHidden);
    const [phaseFilter, setPhaseFilter] = useState(readPhase);

    const toggleStatus = (statusKey) => {
        setHiddenStatuses((cur) => {
            const next = new Set(cur);
            if (next.has(statusKey)) next.delete(statusKey);
            else next.add(statusKey);
            persist(localStorage, HIDDEN_KEY, JSON.stringify([...next]));
            return next;
        });
    };

    const togglePhase = (id) => {
        const key = String(id);
        setPhaseFilter((cur) => {
            const next = cur === key ? null : key;
            persist(sessionStorage, PHASE_KEY, next);
            return next;
        });
    };

    return {
        hiddenStatuses,
        toggleStatus,
        phaseFilter,
        togglePhase,
        isFiltered: hiddenStatuses.size > 0 || phaseFilter !== null,
    };
}

/**
 * Nur tatsächlich vorkommende Status bekommen ein Filter-Kästchen, in Legenden-
 * Reihenfolge (Board-Position) — ein Kästchen für einen Status, den kein Task hat,
 * wäre nur Rauschen.
 *
 * @param {Array} legend           aus deriveLegend()
 * @param {Set<string>} presentKeys Status-Keys, die im Datensatz vorkommen
 */
export function useStatusFilters(legend, presentKeys) {
    return useMemo(
        () => (legend || []).filter((l) => l.key && presentKeys.has(l.key)),
        [legend, presentKeys],
    );
}
