import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useStatusActivity } from '../../performance/useStatusActivity.js';
import { buildHeatmap, cellClass, EMPTY_CLASS, GROUP_LEVELS, GROUPS, RANGE_WEEKS } from '../../performance/heatmap.js';
import { interpolate, transChoice } from '../../summary/i18n.js';

// Aktivitäts-Heatmap der Performance-Unterseite: X = Kalendertag, Y = Stunde,
// Farbton = vorherrschende Status-Familie, Helligkeit = Zahl der Statusupdates.
//
// Die Legende nennt beide Skalen benannt, jedes Kästchen trägt seine
// Aufschlüsselung im Tooltip, und die Zeile über dem Raster fasst Summe, Spitze und
// die Verteilung auf die Familien als TEXT zusammen — die Aussage hängt damit nie
// allein am Farbton (blau gegen lila ist für Rotblindheit ein schwacher Kontrast).
//
// Die Daten kommen aus einem eigenen Endpunkt (Änderungsprotokoll), nicht aus dem
// Tasks-Store; das größte Fenster wird einmal geladen und hier zugeschnitten.

const CELL = 'h-3 rounded-[2px]';
/** Feste Breite nur für die Kästchen der Legende (dort gibt es keine Spalten). */
const LEGEND_CELL = CELL + ' w-3 shrink-0';

/**
 * Spalten des Rasters: die Stunden-Beschriftung fest, die Tage elastisch zwischen
 * MIN und MAX. Der Vorgabe-Zeitraum (12 Wochen) soll ohne Scrollbalken auskommen —
 * bei fester Kästchenbreite tat er das nicht —, gleichzeitig darf ein kurzer
 * Zeitraum die Kästchen nicht zu Klötzen aufblasen. Erst wenn selbst MIN nicht mehr
 * passt (26 Wochen auf schmalem Fenster), scrollt das Raster.
 */
const COL_MIN_PX = 6;
const COL_MAX_PX = 14;
const columnTracks = (days) => `2.5rem repeat(${days}, minmax(${COL_MIN_PX}px, ${COL_MAX_PX}px))`;

/**
 * Eine Skala je vorkommender Familie (benannt) plus die Richtung der Helligkeit.
 * „Sonstiges" erscheint nur, wenn es solche Updates gibt — Bearbeitung und Review
 * stehen immer, damit die Legende beim Filtern nicht springt.
 */
function Legend({ groupTotals, groupLabels, strings }) {
    const shown = GROUPS.filter((g) => g !== 'other' || groupTotals[g] > 0);

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[10px] text-gray-400 dark:text-gray-500">
            {shown.map((g) => (
                <span key={g} className="inline-flex items-center gap-1.5">
                    <span className="text-gray-500 dark:text-gray-400">{groupLabels[g]}</span>
                    {GROUP_LEVELS[g].map((cls, i) => (
                        <span key={i} className={LEGEND_CELL + ' ' + cls} aria-hidden="true" />
                    ))}
                </span>
            ))}
            {/* Die Reihenfolge der Kästchen IST die Skala — hier nur ihre Richtung. */}
            <span>{strings.heatmapLegendLess} → {strings.heatmapLegendMore}</span>
        </div>
    );
}

export default function ActivityHeatmap({ alias, strings }) {
    const [weeks, setWeeks] = useState(RANGE_WEEKS[1]);
    // null = alle Verursacher summiert (Vorgabe). Der Filter wählt EINE Person aus;
    // die Zähler kommen ohnehin je Person mit, es wird also nichts nachgeladen.
    const [actor, setActor] = useState(null);
    const { payload, status, error } = useStatusActivity(alias);

    const people = payload?.people ?? [];
    // Wer im geladenen Fenster nicht mehr vorkommt (Reload nach Datenänderung), darf
    // nicht als unsichtbarer Filter hängen bleiben.
    const activeActor = people.some((p) => p.id === actor) ? actor : null;

    const grid = useMemo(
        () => buildHeatmap({ payload, weeks, actor: activeActor }),
        [payload, weeks, activeActor],
    );

    // Ans rechte Ende scrollen: dort steht HEUTE. Bei 26 Wochen ist das Raster
    // breiter als die Karte — links stünde sonst der halbjährige Anfang, und die
    // aktuelle Woche läge außerhalb des Sichtfelds.
    const scroller = useRef(null);
    useEffect(() => {
        const el = scroller.current;
        if (el) el.scrollLeft = el.scrollWidth;
    }, [status, weeks, grid.columns.length]);

    const dayLabel = (date, withWeekday) =>
        date.toLocaleDateString(undefined, {
            ...(withWeekday ? { weekday: 'short' } : {}),
            day: '2-digit',
            month: '2-digit',
        });
    const hourLabel = (hour) => `${String(hour).padStart(2, '0')}:00`;

    const groupLabels = {
        work: strings.heatmapGroupWork,
        review: strings.heatmapGroupReview,
        other: strings.heatmapGroupOther,
    };

    /** „n Bearbeitung, m Review" — nur vorkommende Familien, in fester Reihenfolge. */
    const breakdown = (groups) =>
        GROUPS.filter((g) => (groups?.[g] ?? 0) > 0)
            .map((g) => `${groups[g]} ${groupLabels[g]}`)
            .join(', ');

    // Der Tooltip nennt Menge UND Aufschlüsselung: der Farbton allein sagt nur, was
    // überwog, nicht was sonst noch in dieser Stunde passiert ist.
    const cellTitle = (col, hour, cell) => {
        const title = transChoice(strings.heatmapCell, cell?.count ?? 0, {
            date: dayLabel(col.date, true),
            hour: hourLabel(hour),
        });
        const parts = breakdown(cell?.groups);

        return parts ? `${title} · ${parts}` : title;
    };

    const busiestLabel = grid.busiest
        ? interpolate(strings.heatmapBusiest, {
            when: `${dayLabel(new Date(grid.busiest.date + 'T12:00:00'), true)} ${hourLabel(grid.busiest.hour)}`,
            count: grid.busiest.count,
        })
        : null;

    return (
        <div className="rounded-lg bg-white p-5 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.heatmapTitle}</h2>
                    <p className="text-xs text-gray-400 dark:text-gray-500">{strings.heatmapSub}</p>
                </div>
                {/* Filter in EINER Zeile über dem Raster: Person und Zeitraum. */}
                <div className="flex flex-wrap items-center gap-3">
                    <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        {strings.heatmapPerson}
                        <select
                            value={activeActor ?? ''}
                            onChange={(e) => setActor(e.target.value === '' ? null : Number(e.target.value))}
                            disabled={people.length === 0}
                            className="max-w-[16rem] rounded-md border-gray-300 py-1 text-sm disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        >
                            <option value="">{strings.heatmapPersonAll}</option>
                            {people.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {interpolate(strings.heatmapPersonOption, { name: p.name, count: p.count })}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        {strings.heatmapRange}
                        <select
                            value={weeks}
                            onChange={(e) => setWeeks(Number(e.target.value))}
                            className="rounded-md border-gray-300 py-1 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        >
                            {RANGE_WEEKS.map((w) => (
                                <option key={w} value={w}>
                                    {interpolate(strings.heatmapRangeWeeks, { weeks: w })}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </div>

            {status === 'error' && (
                <p className="mt-4 text-sm text-red-600 dark:text-red-400">
                    {interpolate(strings.loadError, { message: error || '' })}
                </p>
            )}

            {status !== 'ready' && status !== 'error' && (
                <div className="mt-4 h-[360px] animate-pulse rounded bg-gray-100 dark:bg-gray-700/50" />
            )}

            {status === 'ready' && (
                <>
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {transChoice(strings.heatmapTotal, grid.total)}
                            {/* Die Verteilung auf die Familien als Text — dieselbe
                                Aussage wie der Farbton, nur ohne Farbe. */}
                            {grid.total > 0 && (
                                <span className="text-gray-400 dark:text-gray-500"> · {breakdown(grid.groupTotals)}</span>
                            )}
                            {busiestLabel && (
                                <span className="text-gray-400 dark:text-gray-500"> · {busiestLabel}</span>
                            )}
                        </p>
                        <Legend groupTotals={grid.groupTotals} groupLabels={groupLabels} strings={strings} />
                    </div>

                    {grid.total === 0 ? (
                        <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">
                            {activeActor !== null ? strings.heatmapEmptyPerson : strings.heatmapEmpty}
                        </p>
                    ) : (
                        // EIN Grid für Stundenzeilen und Datumsachse: dieselben
                        // Spalten-Tracks, also sitzen die Ticks von selbst unter ihrer
                        // Spalte. Die Spaltenbreite ist elastisch (siehe columnTracks) —
                        // der Vorgabe-Zeitraum passt damit ohne Scrollbalken; nur ein
                        // sehr langer Zeitraum scrollt, und zwar in sich, nicht die Seite.
                        <div ref={scroller} className="mt-4 overflow-x-auto">
                            <div
                                className="grid gap-[2px]"
                                style={{ gridTemplateColumns: columnTracks(grid.columns.length) }}
                                role="img"
                                aria-label={transChoice(strings.heatmapTotal, grid.total)}
                            >
                                {grid.hours.map((hour) => (
                                    <React.Fragment key={hour}>
                                        {/* Beschriftung alle drei Stunden — jede Stunde
                                            wäre bei 12px Zeilenhöhe eine Textwand. */}
                                        <span className="pe-1 text-right text-[10px] leading-3 text-gray-400 dark:text-gray-500">
                                            {hour % 3 === 0 ? hourLabel(hour) : ''}
                                        </span>
                                        {grid.columns.map((col) => {
                                            const cell = grid.cells.get(`${col.key}|${hour}`);
                                            return (
                                                <span
                                                    key={col.key}
                                                    className={CELL + ' ' + cellClass(cell)}
                                                    title={cellTitle(col, hour, cell)}
                                                />
                                            );
                                        })}
                                    </React.Fragment>
                                ))}

                                {/* Datumsachse: ein Tick je Montag, linksbündig unter
                                    seiner Spalte (überschreibt die Nachbarspalten, darum
                                    absolut positioniert). */}
                                <span />
                                {grid.columns.map((col) => (
                                    <span key={col.key} className="relative h-4">
                                        {col.tick && (
                                            <span className="absolute left-0 top-1 whitespace-nowrap text-[10px] leading-none text-gray-400 dark:text-gray-500">
                                                {dayLabel(col.date, false)}
                                            </span>
                                        )}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
