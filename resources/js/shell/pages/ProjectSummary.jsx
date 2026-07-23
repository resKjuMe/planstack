import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import ProjectHeaderBar from '../components/ProjectHeaderBar.jsx';
import ProjectTabs from '../components/ProjectTabs.jsx';
import PageHead from '../components/PageHead.jsx';
import Flash from '../components/Flash.jsx';

// Vollständig als React umgesetzte Summary-Seite (ehemals status/summary.blade.php).
// KPI-Kacheln, Phasen-Fortschritt (mit Hover-Readout + aufklappbaren Details) und
// die pickbaren PRs als Karten sind alle React; die Daten kommen bereits aufbereitet
// als Inertia-Props aus ProjectSummaryController.
export default function ProjectSummary({ project, can, tabs, kpis, rows, pickable, flash, strings }) {
    const { errors } = usePage().props;

    return (
        <>
            <Head><title>{`${project.name} · ${strings.title}`}</title></Head>

            <PageBands
                header={<ProjectHeaderBar project={project} can={can} strings={strings} />}
                subnav={<ProjectTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    <Flash status={flash?.status} error={flash?.error} errors={errors} />

                    <PageHead
                        title={strings.title}
                        toggleLabel={strings.showHideExplanation}
                        bullets={strings.helpBullets}
                    />

                    {/* 1. KPI-Kacheln */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {kpis.tiles.map((tile) => (
                            <div key={tile.title} className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                                <div className="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">{tile.title}</div>
                                <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{tile.pct} %</div>
                                <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{tile.sub}</div>
                                <div className="mt-3 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                    <div className="h-full bg-green-500" style={{ width: `${tile.pct}%` }}></div>
                                </div>
                            </div>
                        ))}

                        {kpis.velocity && (
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                                <div className="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">{kpis.velocity.title}</div>
                                <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    {kpis.velocity.rate} <span className="text-lg font-semibold text-gray-500 dark:text-gray-400">{kpis.velocity.unit}</span>
                                </div>
                                {kpis.velocity.sub && (
                                    <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{kpis.velocity.sub}</div>
                                )}
                            </div>
                        )}

                        {kpis.lastMerge && (
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                                <div className="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">{kpis.lastMerge.title}</div>
                                <div className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{kpis.lastMerge.when}</div>
                                <div className="mt-1 text-sm font-mono text-gray-500 dark:text-gray-400">{kpis.lastMerge.pr}</div>
                            </div>
                        )}
                    </div>

                    {/* 2. Phasen-Übersicht */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 className="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-4">{strings.phasesTitle}</h2>
                        <div className="space-y-3">
                            {rows.map((row, i) => (
                                <PhaseRow key={i} row={row} strings={strings} />
                            ))}
                        </div>
                    </div>

                    {/* 3. Pickbare PRs als Karten */}
                    <div>
                        <h2 className="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-4">{strings.pickableTitle}</h2>
                        {pickable.length === 0 ? (
                            <p className="text-sm text-gray-400 dark:text-gray-500">{strings.nothingPickable}</p>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                {pickable.map((task) => (
                                    <div
                                        key={task.name}
                                        className={
                                            'bg-white dark:bg-gray-800 rounded-lg p-4 ' +
                                            (task.best
                                                ? 'ring-2 ring-indigo-500 shadow-md'
                                                : 'ring-1 ring-gray-100 dark:ring-gray-700 shadow-sm')
                                        }
                                    >
                                        <div className="flex items-center justify-between">
                                            <a href={task.url} className="font-mono font-semibold text-indigo-700 dark:text-indigo-400 hover:underline">{task.name}</a>
                                            {task.best && (
                                                <span className="inline-flex items-center rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">★ {strings.bestPick}</span>
                                            )}
                                        </div>

                                        <div className="mt-2 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span>{task.sp} SP</span>
                                            <span>·</span>
                                            <span>{task.tokens} {strings.tokens}</span>
                                            <span>·</span>
                                            <span>{task.files} {strings.files}</span>
                                        </div>

                                        {task.unlocks > 0 && (
                                            <div className="mt-2 inline-flex items-center rounded-full bg-green-50 dark:bg-green-900/30 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300">
                                                {task.unlocksLabel}
                                            </div>
                                        )}

                                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{task.summary}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

// Eine Phasen-Zeile mit Hover-Readout auf dem gestapelten Status-Balken und
// aufklappbaren Sekundär-Metriken (früher Alpine „{ open, hover }").
function PhaseRow({ row, strings }) {
    const [open, setOpen] = useState(false);
    const [hover, setHover] = useState(null);

    return (
        <div className="rounded-lg ring-1 ring-gray-100 dark:ring-gray-700 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="font-medium text-gray-800 dark:text-gray-100">{row.phase}</span>
                    {row.blocked_by.map((blocker, i) => (
                        <span key={i} className="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">
                            🔒 {blocker}
                        </span>
                    ))}
                </div>
                <div className="flex items-center gap-2">
                    <span className={'text-sm font-semibold ' + (hover ? hover.text : 'text-gray-500 dark:text-gray-400')}>
                        {hover
                            ? `${hover.label} · ${hover.count} / ${row.total} Tasks · ${hover.pctLabel} % SP`
                            : `${row.done} / ${row.total} PRs (${row.pct}%)`}
                    </span>
                    <button type="button" onClick={() => setOpen((v) => !v)} className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                        {open ? strings.hideDetails : strings.showDetails}
                    </button>
                </div>
            </div>

            {/* Fortschrittsbalken: ein Segment je Status (SP-anteilig) */}
            <div className="relative mt-3">
                {/* Sichtbarer Balken */}
                <div className="flex h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                    {row.statuses.map((s, i) => (
                        <div key={i} className={'h-full ' + s.bar} style={{ width: `${s.width}%` }}></div>
                    ))}
                </div>
                {/* Transparente 36px-Hover-Ebene (zentriert, kein Layout-Shift) */}
                <div className="absolute inset-x-0 top-1/2 flex h-9 -translate-y-1/2">
                    {row.statuses.map((s, i) => (
                        <div
                            key={i}
                            className="h-full"
                            style={{ width: `${s.width}%` }}
                            onMouseEnter={() => setHover({ pctLabel: s.pctLabel, count: s.count, text: s.text, label: s.label })}
                            onMouseLeave={() => setHover(null)}
                            title={`${s.count} ${s.label}`}
                        ></div>
                    ))}
                </div>
            </div>

            {/* Status-Badges (nach echtem Status differenziert) */}
            <div className="mt-3 flex flex-wrap gap-2 text-xs">
                {row.statuses.map((s, i) => (
                    <span key={i} className={'inline-flex items-center rounded-full px-2 py-0.5 font-medium ' + s.badge}>{s.count} {s.label}</span>
                ))}
            </div>

            {/* Sekundäre Metriken (aufklappbar): verbleibend / geplant */}
            {open && (
                <div className="mt-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt className="text-gray-400 dark:text-gray-500 text-xs">PT</dt>
                            <dd className="text-gray-800 dark:text-gray-100">{row.pt.remaining} <span className="text-gray-400 dark:text-gray-500">{strings.rem}</span> / {row.pt.total} <span className="text-gray-400 dark:text-gray-500">{strings.planned}</span></dd>
                        </div>
                        <div>
                            <dt className="text-gray-400 dark:text-gray-500 text-xs">{strings.files}</dt>
                            <dd className="text-gray-800 dark:text-gray-100">{row.files.remaining} <span className="text-gray-400 dark:text-gray-500">{strings.rem}</span> / {row.files.total} <span className="text-gray-400 dark:text-gray-500">{strings.planned}</span></dd>
                        </div>
                        <div>
                            <dt className="text-gray-400 dark:text-gray-500 text-xs">{strings.tokens}</dt>
                            <dd className="text-gray-800 dark:text-gray-100">{row.tokens.remaining} <span className="text-gray-400 dark:text-gray-500">{strings.rem}</span> / {row.tokens.total} <span className="text-gray-400 dark:text-gray-500">{strings.planned}</span></dd>
                        </div>
                    </div>

                    {/* Offene (noch nicht gemergte) PRs dieser Phase */}
                    {row.open_prs.length > 0 && (
                        <div className="mt-4">
                            <dt className="text-gray-400 dark:text-gray-500 text-xs mb-2">{row.open_prs_label}</dt>
                            <ul className="space-y-1">
                                {row.open_prs.map((task) => (
                                    <li key={task.name} className="flex flex-wrap items-center gap-2 text-sm">
                                        <a href={task.url} className="font-mono font-medium text-indigo-700 dark:text-indigo-400 hover:underline">{task.name}</a>
                                        <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + task.badge}>{task.label}</span>
                                        <span className="text-gray-500 dark:text-gray-400 truncate">{task.summary}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
ProjectSummary.layout = AppShell;
