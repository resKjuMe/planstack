import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import FaqTabs from '../components/FaqTabs.jsx';

// FAQ / Kommandos & Status: die Sub-Kommandos des planstack-Skills mit ihrer
// chronologischen Aufruf-Kette, jeder Status mit seiner konkreten Wirkung und die
// Fortschritts-Events. Reine Inhaltsseite — Aufrufketten, Status und Events kommen
// fertig als Props vom Server (Status-Herkunft/„erledigt" aus dem Enum).

// Farbiges Status-Badge, gleiche Klassen wie im Board und in der Statuslogik.
function Badge({ name, badges }) {
    const b = badges[name];
    if (!b) return null;
    return (
        <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + b.classes}>{b.label}</span>
    );
}

// Aufruf/Kommando in Monospace. Umbricht bei Bedarf, damit lange Pfade die
// Tabelle auf schmalen Fenstern nicht aufreißen.
function Call({ children }) {
    return (
        <code className="break-words rounded bg-gray-100 dark:bg-gray-700/70 px-1.5 py-0.5 font-mono text-xs text-gray-700 dark:text-gray-200">{children}</code>
    );
}

// Inhalt der Sticky-Statuszeile zu genau diesem Schritt — im Auto-Modus wird sie
// VOR dem Schritt geschrieben, den sie ankündigt. Fehlt sie, gibt es an dieser
// Stelle keine Aktualisierung.
function StatusLine({ line, label }) {
    if (!line) return null;
    return (
        <div className="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <span className="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-400">{label}</span>
            <code className="break-words rounded bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 font-mono text-xs text-indigo-800 dark:text-indigo-200 ring-1 ring-inset ring-indigo-100 dark:ring-indigo-900/60">{line}</code>
        </div>
    );
}

function Card({ title, hint, children }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                {hint && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{hint}</p>}
            </div>
            {children}
        </div>
    );
}

const TH = 'px-6 py-2 font-medium';
const HEAD_ROW = 'text-left text-xs text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700';
const BODY_ROW = 'border-b border-gray-50 dark:border-gray-700/50 last:border-0 align-top';

export default function FaqCommands({ tabs, badges, lifecycle, commands, statuses, events, strings }) {
    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.faq}</h2>}
                subnav={<FaqTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    <p className="text-sm text-gray-500 dark:text-gray-400">{strings.intro}</p>

                    {/* 1) Ein Task von Anfang bis Ende — chronologisch */}
                    <Card title={strings.lifecycleTitle} hint={strings.lifecycleHint}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className={HEAD_ROW}>
                                        <th className={TH}>{strings.thStep}</th>
                                        <th className={TH}>{strings.thCall}</th>
                                        <th className={TH}>{strings.thWhat}</th>
                                        <th className={TH}>{strings.thStatus}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lifecycle.map((row, i) => (
                                        <tr key={i} className={BODY_ROW}>
                                            <td className="px-6 py-3">
                                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400">{i + 1}</span>
                                            </td>
                                            <td className="px-6 py-3"><Call>{row.call}</Call></td>
                                            <td className="px-6 py-3 text-gray-700 dark:text-gray-300">
                                                {row.what}
                                                <StatusLine line={row.statusLine} label={strings.statusLineLabel} />
                                            </td>
                                            <td className="px-6 py-3 whitespace-nowrap">
                                                {row.status
                                                    ? <Badge name={row.status} badges={badges} />
                                                    : <span className="text-xs text-gray-400 dark:text-gray-500">{strings.lifecycleNoChange}</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="border-t border-gray-100 dark:border-gray-700 px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{strings.statusLineNote}</p>
                    </Card>

                    {/* 2) Die Kommandos, je mit chronologischer Aufruf-Kette */}
                    <Card title={strings.commandsTitle} hint={strings.commandsHint}>
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {commands.map((cmd) => (
                                <li key={cmd.name} className="px-6 py-5">
                                    <Call>{cmd.name}</Call>
                                    <p className="mt-2 text-sm text-gray-700 dark:text-gray-300">{cmd.purpose}</p>

                                    {cmd.steps.length === 0 ? (
                                        <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">{strings.noCalls}</p>
                                    ) : (
                                        <ol className="mt-3 space-y-2">
                                            {cmd.steps.map((step, i) => (
                                                <li key={i} className="flex items-start gap-3">
                                                    <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-[11px] font-semibold text-gray-600 dark:text-gray-400">{i + 1}</span>
                                                    <div className="min-w-0 text-sm">
                                                        <Call>{step.call}</Call>
                                                        <p className="mt-1 text-gray-600 dark:text-gray-400">{step.what}</p>
                                                        <StatusLine line={step.statusLine} label={strings.statusLineLabel} />
                                                    </div>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>

                    {/* 3) Was jeder Status genau macht */}
                    <Card title={strings.statusesTitle} hint={strings.statusesHint}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className={HEAD_ROW}>
                                        <th className={TH}>{strings.thStatusSingle}</th>
                                        <th className={TH}>{strings.thMeaning}</th>
                                        <th className={TH}>{strings.thDoes}</th>
                                        <th className={TH}>{strings.thSetBy}</th>
                                        <th className={TH}>{strings.thNext}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {statuses.map((s) => (
                                        <tr key={s.name} className={BODY_ROW}>
                                            <td className="px-6 py-3">
                                                <Badge name={s.name} badges={badges} />
                                                <div className="mt-1.5 font-mono text-[11px] text-gray-400 dark:text-gray-500">{s.value}</div>
                                                <div className="mt-1.5 flex flex-wrap gap-1">
                                                    <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{s.kind}</span>
                                                    {s.derived && <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800">{strings.flagDerived}</span>}
                                                    {s.stored && <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{strings.flagStored}</span>}
                                                    {s.countsDone && <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800">{strings.flagCountsDone}</span>}
                                                </div>
                                            </td>
                                            <td className="px-6 py-3 text-gray-600 dark:text-gray-400">{s.meaning}</td>
                                            <td className="px-6 py-3 text-gray-700 dark:text-gray-300">{s.does}</td>
                                            <td className="px-6 py-3 text-gray-600 dark:text-gray-400">{s.setBy}</td>
                                            <td className="px-6 py-3 text-gray-600 dark:text-gray-400">{s.next}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="border-t border-gray-100 dark:border-gray-700 px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{strings.statusesNote}</p>
                    </Card>

                    {/* 4) Fortschritts-Events */}
                    <Card title={strings.eventsTitle} hint={strings.eventsHint}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className={HEAD_ROW}>
                                        <th className={TH}>{strings.thEvent}</th>
                                        <th className={TH}>{strings.thWhen}</th>
                                        <th className={TH}>{strings.thEffect}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.map((e) => (
                                        <tr key={e.event} className={BODY_ROW}>
                                            <td className="px-6 py-3"><Call>{e.event}</Call></td>
                                            <td className="px-6 py-3 text-gray-700 dark:text-gray-300">{e.when}</td>
                                            <td className="px-6 py-3 whitespace-nowrap">
                                                <span className={'inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium ' + (e.drivesStatus
                                                    ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400')}>
                                                    {e.drivesStatus ? strings.effectStatus : strings.effectInfo}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <ul className="border-t border-gray-100 dark:border-gray-700 divide-y divide-gray-50 dark:divide-gray-700/50">
                            <li className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{strings.eventsAuthoritative}</li>
                            <li className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{strings.eventsBestEffort}</li>
                            <li className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{strings.eventsMergedNote}</li>
                            <li className="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{strings.eventsDefaultNote}</li>
                        </ul>
                    </Card>

                </div>
            </div>
        </>
    );
}

FaqCommands.layout = AppShell;
