import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useStatusActivity } from '../../performance/useStatusActivity.js';
import { buildHeatmap, LEGEND_CLASSES, RANGE_WEEKS } from '../../performance/heatmap.js';
import { interpolate, transChoice } from '../../summary/i18n.js';

// Aktivitäts-Heatmap der Performance-Unterseite: X = Kalendertag, Y = Stunde,
// Farbe = Zahl der protokollierten Statusupdates.
//
// Einfarbige (sequentielle) Skala — die Menge steckt in der Helligkeit, nicht in
// wechselnden Farbtönen; leere Stunden bleiben neutral grau. Die Legende nennt die
// Richtung, jedes Kästchen den genauen Wert im Tooltip, und die Zeile über dem
// Raster fasst Summe und Spitze als TEXT zusammen — damit die Aussage nicht allein
// an der Farbe hängt.
//
// Die Daten kommen aus einem eigenen Endpunkt (Änderungsprotokoll), nicht aus dem
// Tasks-Store; das größte Fenster wird einmal geladen und hier zugeschnitten.

const CELL = 'h-3 w-3 shrink-0 rounded-[2px]';

function Legend({ strings }) {
    return (
        <div className="flex items-center gap-1.5 text-[10px] text-gray-400 dark:text-gray-500">
            <span>{strings.heatmapLegendLess}</span>
            {LEGEND_CLASSES.map((cls, i) => (
                <span key={i} className={CELL + ' ' + cls} aria-hidden="true" />
            ))}
            <span>{strings.heatmapLegendMore}</span>
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

    const cellTitle = (col, hour, count) =>
        transChoice(strings.heatmapCell, count, {
            date: dayLabel(col.date, true),
            hour: hourLabel(hour),
        });

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
                            {busiestLabel && (
                                <span className="text-gray-400 dark:text-gray-500"> · {busiestLabel}</span>
                            )}
                        </p>
                        <Legend strings={strings} />
                    </div>

                    {grid.total === 0 ? (
                        <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">
                            {activeActor !== null ? strings.heatmapEmptyPerson : strings.heatmapEmpty}
                        </p>
                    ) : (
                        // Das Raster wird breiter als die Karte, sobald der Zeitraum
                        // wächst — es scrollt in sich, die Seite nicht.
                        <div ref={scroller} className="mt-4 overflow-x-auto">
                            <div
                                className="inline-block min-w-full"
                                role="img"
                                aria-label={transChoice(strings.heatmapTotal, grid.total)}
                            >
                                {grid.hours.map((hour) => (
                                    <div key={hour} className="flex items-center gap-[2px] pb-[2px]">
                                        {/* Beschriftung alle drei Stunden — jede Stunde
                                            wäre bei 12px Zeilenhöhe eine Textwand. */}
                                        <span className="w-10 shrink-0 pe-1 text-right text-[10px] leading-none text-gray-400 dark:text-gray-500">
                                            {hour % 3 === 0 ? hourLabel(hour) : ''}
                                        </span>
                                        {grid.columns.map((col) => {
                                            const cell = grid.cells.get(`${col.key}|${hour}`);
                                            return (
                                                <span
                                                    key={col.key}
                                                    className={CELL + ' ' + LEGEND_CLASSES[cell?.level ?? 0]}
                                                    title={cellTitle(col, hour, cell?.count ?? 0)}
                                                />
                                            );
                                        })}
                                    </div>
                                ))}

                                {/* Datumsachse: ein Tick je Montag, linksbündig unter
                                    seiner Spalte. */}
                                <div className="flex items-start gap-[2px] pt-1">
                                    <span className="w-10 shrink-0" />
                                    {grid.columns.map((col) => (
                                        <span key={col.key} className="relative h-4 w-3 shrink-0">
                                            {col.tick && (
                                                <span className="absolute left-0 top-0 whitespace-nowrap text-[10px] leading-none text-gray-400 dark:text-gray-500">
                                                    {dayLabel(col.date, false)}
                                                </span>
                                            )}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
