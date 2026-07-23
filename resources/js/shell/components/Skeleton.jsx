import React from 'react';

// Wiederverwendbare Skeleton-Platzhalter fuer ladende React-Ansichten. Alle
// Bausteine pulsieren synchron (animate-pulse am jeweiligen Wurzelknoten) und
// nutzen dieselben Grautoene wie das restliche UI. Rein dekorativ — die
// aufrufende View setzt aria-busy/den Ladezustand. Erscheinen nur beim ersten
// Laden der Projektdaten pro Session (danach kommt alles aus dem Store).

const bar = 'rounded bg-gray-200 dark:bg-gray-700';

// KPI-Kachelreihe (Summary / PR-Sequenz / Kalibrierung).
export function KpiTilesSkeleton({ count = 4 }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 animate-pulse" aria-hidden="true">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="rounded-lg bg-white dark:bg-gray-800 shadow p-5">
                    <div className={`h-3 w-24 ${bar}`} />
                    <div className={`mt-3 h-8 w-20 ${bar}`} />
                    <div className={`mt-3 h-1.5 w-full rounded-full ${bar}`} />
                </div>
            ))}
        </div>
    );
}

// Weisse Karte mit mehreren Zeilen-Bloecken (z. B. Phasen-Uebersicht der Summary).
export function LinesCardSkeleton({ rows = 3 }) {
    return (
        <div className="rounded-lg bg-white dark:bg-gray-800 shadow p-6 animate-pulse" aria-hidden="true">
            <div className={`h-3 w-32 ${bar}`} />
            <div className="mt-4 space-y-4">
                {Array.from({ length: rows }).map((_, i) => (
                    <div key={i} className="rounded-lg ring-1 ring-gray-100 dark:ring-gray-700 p-4">
                        <div className="flex items-center justify-between">
                            <div className={`h-4 w-32 ${bar}`} />
                            <div className={`h-4 w-24 ${bar}`} />
                        </div>
                        <div className={`mt-3 h-2.5 w-full rounded-full ${bar}`} />
                        <div className="mt-3 flex gap-2">
                            <div className={`h-5 w-16 rounded-full ${bar}`} />
                            <div className={`h-5 w-16 rounded-full ${bar}`} />
                            <div className={`h-5 w-16 rounded-full ${bar}`} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// Karten-Grid (Summary pickbare PRs, PR-Sequenz-Liste, Changelog-Eintraege,
// Kalibrierung-Charts). cols steuert die Spaltenzahl ab sm/lg.
export function CardsSkeleton({ count = 3, cols = 3, bodyClass = '' }) {
    const grid =
        { 1: '', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-2 lg:grid-cols-3' }[cols] ??
        'sm:grid-cols-2 lg:grid-cols-3';
    return (
        <div className={`grid grid-cols-1 gap-4 ${grid} animate-pulse`} aria-hidden="true">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className={`rounded-lg bg-white dark:bg-gray-800 shadow p-4 ${bodyClass}`}>
                    <div className="flex items-center justify-between">
                        <div className={`h-4 w-24 ${bar}`} />
                        <div className={`h-4 w-12 ${bar}`} />
                    </div>
                    <div className={`mt-3 h-3 w-full ${bar}`} />
                    <div className={`mt-2 h-3 w-4/5 ${bar}`} />
                </div>
            ))}
        </div>
    );
}

// Einzelner grosser Block (z. B. Diagramm-Zeichenflaeche, Chart-Platzhalter).
export function BlockSkeleton({ className = 'h-72' }) {
    return <div className={`rounded-lg bg-gray-200 dark:bg-gray-700 animate-pulse ${className}`} aria-hidden="true" />;
}

// Tabelle mit Kopfzeile + Datenzeilen (Kalibrierung).
export function TableSkeleton({ rows = 6, cols = 5 }) {
    return (
        <div className="overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 animate-pulse" aria-hidden="true">
            <div className="flex gap-4 border-b border-gray-100 dark:border-gray-700 px-4 py-2">
                {Array.from({ length: cols }).map((_, i) => (
                    <div key={i} className={`h-3 flex-1 ${bar}`} />
                ))}
            </div>
            {Array.from({ length: rows }).map((_, r) => (
                <div key={r} className="flex gap-4 border-b border-gray-50 dark:border-gray-700 px-4 py-3 last:border-0">
                    {Array.from({ length: cols }).map((_, c) => (
                        <div key={c} className={`h-4 flex-1 ${bar}`} />
                    ))}
                </div>
            ))}
        </div>
    );
}

// Chip-/Pill-Reihe (Filter-Leisten von Diagramm / PR-Sequenz).
export function ChipsSkeleton({ count = 5 }) {
    return (
        <div className="flex flex-wrap gap-2 animate-pulse" aria-hidden="true">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className={`h-8 w-24 rounded-full ${bar}`} />
            ))}
        </div>
    );
}

// Kanban-Board: mehrere Spalten mit Kartenplatzhaltern.
export function BoardSkeleton({ columns = 5 }) {
    const cols = Math.max(1, Math.min(columns, 8));
    return (
        <div
            className="grid min-h-[65vh] gap-3 overflow-x-auto animate-pulse"
            style={{ gridTemplateColumns: `repeat(${cols}, minmax(16rem, 1fr))` }}
            aria-hidden="true"
        >
            {Array.from({ length: cols }).map((_, c) => (
                <div key={c} className="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
                    <div className="flex items-center justify-between">
                        <div className={`h-4 w-24 ${bar}`} />
                        <div className={`h-4 w-6 ${bar}`} />
                    </div>
                    <div className="mt-3 space-y-3">
                        {Array.from({ length: 3 - (c % 2) }).map((_, i) => (
                            <div key={i} className="rounded-lg bg-white dark:bg-gray-800 shadow p-3">
                                <div className={`h-3 w-16 ${bar}`} />
                                <div className={`mt-2 h-3 w-full ${bar}`} />
                                <div className={`mt-1.5 h-3 w-3/4 ${bar}`} />
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
