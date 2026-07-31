import React from 'react';

// Die Filter-Bausteine der beiden Abhängigkeits-Ansichten (Diagramm und Zeitachse):
// Status-Kästchen und Phasen-Pillen. Geteilt, damit „gleiche Filter" nicht heißt
// „zwei Mal dasselbe nachgebaut" — Aussehen, Reihenfolge und Beschriftung kommen
// aus einer Quelle. Den Zustand hält useDependencyFilters().

// Inneres SVG-Markup des Status-Icons in ein <svg> hüllen (wie im Diagramm).
function Ico({ paths, className = 'ps-ico' }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: paths || '' }}
        />
    );
}

/**
 * Ein Kästchen je vorkommendem Status; abgehakt = sichtbar. `children` nimmt
 * ansichts-eigene Optionen auf (z. B. die Kurzbeschreibungen des Diagramms), die
 * links vor den Status stehen.
 */
export function StatusFilterCard({ statuses, hidden, onToggle, label, children = null }) {
    if (statuses.length === 0 && !children) return null;

    return (
        <div className="ps-status-filter rounded-lg bg-white p-4 shadow dark:bg-gray-800 dark:shadow-black/30">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                {children}

                {statuses.length > 0 && (
                    <>
                        {children && <span className="h-4 w-px bg-gray-200 dark:bg-gray-700"></span>}
                        <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
                        {statuses.map((s) => (
                            <label
                                key={s.key}
                                className="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400"
                            >
                                <input
                                    type="checkbox"
                                    checked={!hidden.has(s.key)}
                                    onChange={() => onToggle(s.key)}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-indigo-400"
                                />
                                <span className={`lg-swatch tok-${s.color} cat-${s.cat}`}>
                                    <Ico paths={s.icon} />
                                </span>
                                {s.label}
                            </label>
                        ))}
                    </>
                )}
            </div>
        </div>
    );
}

/**
 * Phasen-Pillen mit Fortschrittsbalken; Klick filtert auf die Phase, erneuter Klick
 * hebt den Filter auf.
 */
export function PhaseFilterPills({ phases, activeId, onToggle, hint }) {
    if (!phases || phases.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
            {phases.map((ph) => {
                const active = activeId === String(ph.id);
                return (
                    <button
                        key={ph.id}
                        type="button"
                        data-diagram-phase={ph.id}
                        {...(active ? { 'data-active': true } : {})}
                        onClick={() => onToggle(ph.id)}
                        // Hover- und Aktiv-Zustand hängen an [data-diagram-phase]
                        // (app.css) — hier nur die Grundform, damit beide Ansichten
                        // dieselbe Pille zeigen.
                        className="flex items-center gap-2 rounded-md bg-gray-50 px-2.5 py-1 ring-1 ring-gray-100 dark:bg-gray-700/40 dark:ring-gray-700"
                        title={`${ph.name} — ${ph.pct}%${hint ? ' · ' + hint : ''}`}
                    >
                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">{ph.short}</span>
                        <span className="h-1.5 w-14 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <span
                                className={'block h-full rounded-full ' + (ph.pct >= 100 ? 'bg-green-600' : 'bg-indigo-500')}
                                style={{ width: `${ph.pct}%` }}
                            ></span>
                        </span>
                        <span className="text-[11px] tabular-nums text-gray-400 dark:text-gray-500">{ph.pct}%</span>
                    </button>
                );
            })}
        </div>
    );
}
