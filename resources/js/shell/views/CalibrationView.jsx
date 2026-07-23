import React, { useMemo, useRef, useState } from 'react';
import PageHead from '../components/PageHead.jsx';
import { useProjectData } from '../../data/useProjectData';
import { deriveCalibration } from '../../calibration/derive.js';
import { interpolate, transChoice } from '../../summary/i18n.js';

const tileText = (c) =>
    ({
        green: 'text-green-600 dark:text-green-400',
        amber: 'text-amber-600 dark:text-amber-400',
        red: 'text-red-600 dark:text-red-400',
    })[c] || 'text-gray-300 dark:text-gray-600';

const deComma = (v, digits = 1) => Number(v || 0).toFixed(digits).replace('.', ',');

// Hilfe-Infobox (zwei Abschnitte: Kennzahlen + Diagramme/Tabelle).
function Help({ strings }) {
    const Section = ({ heading, bullets }) => (
        <div>
            <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{heading}</div>
            <ul className="list-disc space-y-1 ps-4">
                {bullets.map((b, i) => (
                    <li key={i}>
                        <span className="font-medium">{b.strong}</span>: {b.text}
                    </li>
                ))}
            </ul>
        </div>
    );
    return (
        <div className="space-y-4">
            <Section heading={strings.metrics} bullets={strings.helpMetrics} />
            <Section heading={strings.chartsTable} bullets={strings.helpCharts} />
        </div>
    );
}

function Scatter({ scatter, strings }) {
    const ax = scatter.axis;
    const L = 30;
    const B = 214;
    const T = 8;
    const Rr = 332;
    const sx = (v) => L + (ax > 0 ? v / ax : 0) * (Rr - L);
    const sy = (v) => B - (ax > 0 ? v / ax : 0) * (B - T);
    const ticks = [];
    for (let t = 0; t <= ax; t += 10) ticks.push(t);

    return (
        <svg viewBox="0 0 340 240" className="mt-3 w-full" fontFamily="ui-sans-serif, system-ui">
            {ticks.map((t) => (
                <g key={t}>
                    <line x1={sx(t)} y1={sy(0)} x2={sx(t)} y2={sy(ax)} stroke="#f1f5f9" strokeWidth="1" />
                    <line x1={sx(0)} y1={sy(t)} x2={sx(ax)} y2={sy(t)} stroke="#f1f5f9" strokeWidth="1" />
                    <text x={sx(t)} y="234" fill="#9ca3af" fontSize="9" textAnchor="middle">{t}</text>
                    <text x="24" y={sy(t) + 3} fill="#9ca3af" fontSize="9" textAnchor="end">{t}</text>
                </g>
            ))}
            <line x1={sx(0)} y1={sy(0)} x2={sx(ax)} y2={sy(ax)} stroke="#cbd5e1" strokeWidth="1.5" strokeDasharray="4 4" />
            <text x={sx(ax / 2)} y="240" fill="#9ca3af" fontSize="9" textAnchor="middle">{strings.estimated}</text>
            <text x="10" y={sy(ax / 2)} fill="#9ca3af" fontSize="9" textAnchor="middle" transform={`rotate(-90 10 ${sy(ax / 2)})`}>{strings.changed}</text>
            {scatter.points.map((p, i) =>
                p.hit ? (
                    <circle key={i} cx={sx(p.x)} cy={sy(p.y)} r="4" fill="#16a34a" fillOpacity="0.85"><title>{`${p.name}: ${p.x} → ${p.y}`}</title></circle>
                ) : (
                    <rect key={i} x={sx(p.x) - 4} y={sy(p.y) - 4} width="8" height="8" fill="#ef4444" fillOpacity="0.8"><title>{`${p.name}: ${p.x} → ${p.y}`}</title></rect>
                ),
            )}
        </svg>
    );
}

// Kalibrierung als Teilansicht des ProjectWorkspace. Daten kommen gecacht über
// GET /api/projects/{alias}/calibration (useCalibration) und aktualisieren sich
// live per entity-changed. Tabs/Sortierung sind clientseitiger React-State.
export default function CalibrationView({ project, strings }) {
    const { tasks, statusConfig, status, error } = useProjectData(project.alias);

    const data = useMemo(() => {
        if (status !== 'ready' || !statusConfig) return null;
        return deriveCalibration({
            tasks,
            statusConfig,
            strings,
            taskUrlTemplate: project.taskUrlTemplate,
            locale: (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de',
        });
    }, [tasks, statusConfig, status, strings, project.taskUrlTemplate]);

    const [tab, setTab] = useState('all');
    const [sort, setSort] = useState('dev');
    const listRef = useRef(null);

    const list = useMemo(() => {
        if (!data) return [];
        const key = { dev: 'sortDev', sp: 'sortSp', date: 'sortDate', time: 'sortTime' }[sort];
        const rows = data.rowData.filter((x) =>
            tab === 'outliers' ? x.isOutlier : tab === 'noEstimate' ? !x.hasEstimate : true,
        );
        return rows.slice().sort((a, b) => b[key] - a[key]);
    }, [data, tab, sort]);

    const kpis = data?.kpis;
    const medArrow = !kpis || kpis.median === null ? '' : kpis.median < 0 ? '↘' : kpis.median > 0 ? '↗' : '→';

    return (
        <div className="space-y-6">
            <PageHead
                title={strings.title}
                toggleLabel={strings.showHideExplanation}
                meta={
                    kpis ? (
                        <span className="text-sm text-gray-400 dark:text-gray-500">
                            {interpolate(strings.totalMergedTasks, { total: kpis.total })}
                            {kpis.lastSync ? ' · ' + interpolate(strings.lastSyncedTime, { time: kpis.lastSync }) : ''}
                        </span>
                    ) : null
                }
            >
                <Help strings={strings} />
            </PageHead>

            {status !== 'ready' && status !== 'error' && (
                <p className="text-sm text-gray-400 dark:text-gray-500">{strings.loading}</p>
            )}
            {status === 'error' && <p className="text-sm text-red-600 dark:text-red-400">{error || 'Fehler'}</p>}

            {data && kpis && (
                <>
                    {/* KPI-Kacheln */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.medianDeviation}</div>
                            {kpis.medianLabel ? (
                                <>
                                    <div className={'mt-1 text-3xl font-bold ' + tileText(kpis.medianClass)}>{kpis.medianLabel}</div>
                                    <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{medArrow} {kpis.medianHint}</div>
                                </>
                            ) : (
                                <>
                                    <div className="mt-1 text-3xl font-bold text-gray-300 dark:text-gray-600">—</div>
                                    <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{kpis.medianHint}</div>
                                </>
                            )}
                        </div>

                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.velocity}</div>
                            {kpis.spPerDay !== null ? (
                                <>
                                    <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{deComma(kpis.spPerDay)} <span className="text-lg font-semibold text-gray-500 dark:text-gray-400">{strings.spDay}</span></div>
                                    <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{kpis.daysPerSpLabel ? `Ø ${kpis.daysPerSpLabel} ${strings.perSp} · ` : ''}{strings.claimMerge}</div>
                                </>
                            ) : (
                                <>
                                    <div className="mt-1 text-3xl font-bold text-gray-300 dark:text-gray-600">—</div>
                                    <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.claimMerge}</div>
                                </>
                            )}
                        </div>

                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.accuracy25}</div>
                            <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{kpis.hits} <span className="text-lg font-semibold text-gray-400 dark:text-gray-500">/ {kpis.hitsTotal}</span></div>
                            <div className="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div className="h-full bg-amber-400" style={{ width: `${kpis.hitsTotal ? Math.round((kpis.hits / kpis.hitsTotal) * 100) : 0}%` }}></div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.dataBasis}</div>
                            <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{kpis.withEstimate} <span className="text-lg font-semibold text-gray-400 dark:text-gray-500">/ {kpis.total}</span></div>
                            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.tasksWithEstimate}</div>
                        </div>
                    </div>

                    {/* Warnbanner: Tasks ohne Dateischätzung */}
                    {kpis.noEstimate > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 px-4 py-3">
                            <div className="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-300">
                                <svg className="mt-0.5 h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>
                                <span>{transChoice(strings.noEstimateNote, kpis.noEstimate, { count: kpis.noEstimate })}</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => {
                                    setTab('noEstimate');
                                    requestAnimationFrame(() => listRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                                }}
                                className="shrink-0 rounded-md bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-semibold text-amber-800 dark:text-amber-300 ring-1 ring-amber-300 dark:ring-amber-700 hover:bg-amber-100 dark:hover:bg-amber-900/40"
                            >
                                {strings.show}
                            </button>
                        </div>
                    )}

                    {/* Scatter + Treffsicherheit nach SP */}
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div className="rounded-lg bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700">
                            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.estimatedVsActual}</h2>
                            <p className="text-xs text-gray-400 dark:text-gray-500">{strings.filesPerTaskDiagonal}</p>
                            {data.scatter.points.length > 0 ? (
                                <Scatter scatter={data.scatter} strings={strings} />
                            ) : (
                                <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">{strings.noTasksWithEstimate}</p>
                            )}
                        </div>

                        <div className="rounded-lg bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700">
                            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.accuracyBySp}</h2>
                            <p className="text-xs text-gray-400 dark:text-gray-500">{strings.shareWithin25}</p>
                            {data.spAccuracy.length > 0 ? (
                                <>
                                    <div className="mt-4 space-y-3">
                                        {data.spAccuracy.map((g, i) => (
                                            <div key={i}>
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="font-medium text-gray-700 dark:text-gray-300">{g.label}</span>
                                                    <span className="text-gray-400 dark:text-gray-500">{g.hits}/{g.total}</span>
                                                </div>
                                                <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div className={'h-full ' + (g.pct > 0 ? 'bg-green-500' : 'bg-red-400')} style={{ width: `${g.pct > 0 ? g.pct : 5}%` }}></div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    {data.tip && (
                                        <p className="mt-4 flex items-start gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <svg className="mt-0.5 h-4 w-4 shrink-0 text-indigo-500 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 18h6" /><path d="M10 22h4" /><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2h6c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z" /></svg>
                                            <span>{data.tip}</span>
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">{strings.noTasksWithEstimate}</p>
                            )}
                        </div>
                    </div>

                    {/* Tabs + Sortierung */}
                    <div ref={listRef} className="flex flex-wrap items-center justify-between gap-3 scroll-mt-6">
                        <div className="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 p-1">
                            {[
                                { key: 'all', label: strings.all },
                                { key: 'outliers', label: strings.outliersOnly },
                                { key: 'noEstimate', label: strings.noEstimate },
                                { key: 'grouped', label: strings.groupedBySp },
                            ].map((t) => (
                                <button
                                    key={t.key}
                                    type="button"
                                    onClick={() => setTab(t.key)}
                                    className={
                                        'rounded-full px-3 py-1 text-sm font-medium ' +
                                        (tab === t.key
                                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                                    }
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>
                        {tab !== 'grouped' && (
                            <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                {strings.sort}
                                <select value={sort} onChange={(e) => setSort(e.target.value)} className="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 py-1 text-sm">
                                    <option value="dev">{strings.deviation}</option>
                                    <option value="sp">{strings.storyPoints}</option>
                                    <option value="date">{strings.date}</option>
                                    <option value="time">{strings.timeSp}</option>
                                </select>
                            </label>
                        )}
                    </div>

                    {/* Tabelle */}
                    {tab !== 'grouped' && (
                        <div className="overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-700 text-left text-xs text-gray-400 dark:text-gray-500">
                                        <th className="px-4 py-2 font-medium">{strings.task}</th>
                                        <th className="px-4 py-2 font-medium">SP</th>
                                        <th className="px-4 py-2 font-medium">{strings.filesEstimatedChanged}</th>
                                        <th className="px-4 py-2 font-medium">{strings.deviation}</th>
                                        <th className="px-4 py-2 text-right font-medium">{strings.timeSp}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {list.map((row) => (
                                        <tr key={row.name} className="border-b border-gray-50 dark:border-gray-700 last:border-0">
                                            <td className="px-4 py-3">
                                                <a href={row.url} className="font-mono font-semibold text-indigo-700 dark:text-indigo-400 hover:underline">{row.name}</a>
                                                <div className="text-xs text-gray-400 dark:text-gray-500">{row.dateShort} · {row.meta}</div>
                                            </td>
                                            <td className="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">{row.sp ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs text-gray-600 dark:text-gray-400">{(row.filesEstimated ?? '—') + ' → ' + row.filesActual}</span>
                                                    {row.hasEstimate ? (
                                                        <span className="inline-block h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                                                            <span className={'block h-full ' + row.barClass} style={{ width: `${row.barWidth}%` }}></span>
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">{strings.noEstimate2}</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.hasEstimate ? (
                                                    <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + row.pillClass}>{row.deviationLabel}</span>
                                                ) : (
                                                    <span className="text-gray-300 dark:text-gray-600">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-gray-600 dark:text-gray-400">{row.timePerSp}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {list.length === 0 && (
                                <p className="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{strings.noEntries}</p>
                            )}
                        </div>
                    )}

                    {/* Nach SP gruppiert */}
                    {tab === 'grouped' && (
                        <div className="overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-700 text-left text-xs text-gray-400 dark:text-gray-500">
                                        <th className="px-4 py-2 font-medium">SP</th>
                                        <th className="px-4 py-2 font-medium">{strings.avgToMerge}</th>
                                        <th className="px-4 py-2 font-medium">{strings.tasks}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.groups.map((group, i) => (
                                        <tr key={i} className="border-b border-gray-50 dark:border-gray-700 align-top last:border-0">
                                            <td className="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">{group.storyPoints} SP</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">Ø {deComma(group.avgDuration)} {strings.days}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {group.rows.map((r) => (
                                                        <a key={r.name} href={r.url} className="rounded bg-gray-50 dark:bg-gray-800/50 px-2 py-0.5 font-mono text-xs text-indigo-700 dark:text-indigo-400 ring-1 ring-gray-100 dark:ring-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">{r.name}</a>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {data.groups.length === 0 && (
                                <p className="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{strings.noEntries}</p>
                            )}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
