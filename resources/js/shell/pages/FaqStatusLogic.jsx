import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import FaqTabs from '../components/FaqTabs.jsx';

// Kleiner Helfer: farbiges Status-Badge (nutzt dieselben Klassen wie überall).
function Badge({ name, badges }) {
    const b = badges[name];
    return (
        <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + b.classes}>{b.label}</span>
    );
}

// FAQ / Statuslogik (ehemals faq/status-logic.blade.php). Reine, enum-getriebene
// Inhaltsseite: Badges, Legende und Transitions kommen fertig als Props vom Server.
export default function FaqStatusLogic({ tabs, badges, legend, transitions, strings }) {
    return (
        <>
            <Head><title>{strings.faq}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.faq}</h2>}
                subnav={<FaqTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {strings.introPre} <span className="font-medium text-gray-700 dark:text-gray-300">{strings.introEmph}</span> {strings.introPost}
                    </p>

                    {/* 1) Status-Legende */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.legendTitle}</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.legendHint}</p>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                    <th className="px-6 py-2 font-medium">{strings.status}</th>
                                    <th className="px-6 py-2 font-medium">{strings.value}</th>
                                    <th className="px-6 py-2 font-medium">{strings.meaning}</th>
                                    <th className="px-6 py-2 font-medium">{strings.origin}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {legend.map((row) => (
                                    <tr key={row.name} className="border-b border-gray-50 last:border-0 align-top">
                                        <td className="px-6 py-3 whitespace-nowrap"><Badge name={row.name} badges={badges} /></td>
                                        <td className="px-6 py-3 whitespace-nowrap font-mono text-xs text-gray-500 dark:text-gray-400">{row.value}</td>
                                        <td className="px-6 py-3 text-gray-700 dark:text-gray-300">{row.desc}</td>
                                        <td className="px-6 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {row.kinds.map((kind, i) => (
                                                    <span key={i} className={'inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium ' + (kind.derived ? 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400')}>{kind.label}</span>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* 2) Auslöser → Status */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.setTitle}</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.setHint}</p>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                    <th className="px-6 py-2 font-medium">{strings.trigger}</th>
                                    <th className="px-6 py-2 font-medium">{strings.result}</th>
                                    <th className="px-6 py-2 font-medium">{strings.note}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transitions.map((t, ti) => (
                                    <tr key={ti} className="border-b border-gray-50 last:border-0 align-top">
                                        <td className="px-6 py-3 whitespace-nowrap font-medium text-gray-800 dark:text-gray-100">{t.trigger}</td>
                                        <td className="px-6 py-3">
                                            {t.results.length === 0 ? (
                                                <span className="text-xs text-gray-400 dark:text-gray-500">{strings.anyNoChange}</span>
                                            ) : (
                                                t.results.map((r, i) => (
                                                    <React.Fragment key={r}>
                                                        {i > 0 && <span className="mx-1 text-gray-400 dark:text-gray-500">/</span>}
                                                        <Badge name={r} badges={badges} />
                                                    </React.Fragment>
                                                ))
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-gray-600 dark:text-gray-400">{t.note ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* 3) Regeln für Claude während der Bearbeitung */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden ring-1 ring-indigo-100 dark:ring-indigo-900/50">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-indigo-50/40 dark:bg-indigo-900/30">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.rulesTitle}</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {strings.rulesHintPre} <span className="font-medium text-gray-700 dark:text-gray-300">{strings.rulesHintEmph}</span>{strings.rulesHintPost}
                            </p>
                        </div>

                        {/* Ablauf als Statuskette */}
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <div className="flex flex-wrap items-center gap-x-1 gap-y-2 text-sm">
                                <span className="text-gray-500 dark:text-gray-400">{strings.chainRead}</span>
                                <span className="text-gray-300 dark:text-gray-600">→</span>
                                <span className="text-gray-500 dark:text-gray-400">{strings.chainChoose}</span>
                                <span className="text-gray-300 dark:text-gray-600">→</span>
                                <Badge name="CLAIMED" badges={badges} />
                                <span className="text-gray-300 dark:text-gray-600">→</span>
                                <Badge name="ANALYZING" badges={badges} />
                                <span className="text-gray-300 dark:text-gray-600">→</span>
                                <span className="inline-flex items-center gap-1 rounded-md bg-gray-50 dark:bg-gray-800/50 px-2 py-1 ring-1 ring-gray-100 dark:ring-gray-700">
                                    <Badge name="IN_PROGRESS" badges={badges} /><span className="text-gray-400 dark:text-gray-500">→ PR →</span><Badge name="IN_REVIEW" badges={badges} /><span className="text-gray-400 dark:text-gray-500">→</span><Badge name="MERGED" badges={badges} />
                                </span>
                                <span className="text-gray-300 dark:text-gray-600">|</span>
                                <span className="inline-flex items-center gap-1 rounded-md bg-red-50 dark:bg-red-900/30 px-2 py-1 ring-1 ring-red-100 dark:ring-red-900/50">
                                    <span className="text-gray-400 dark:text-gray-500">{strings.chainOr}</span><Badge name="CONCERNED" badges={badges} />
                                </span>
                            </div>
                        </div>

                        {/* Regeln */}
                        <ul className="divide-y divide-gray-50 dark:divide-gray-700">
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleReread}</span> {strings.ruleRereadText}
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleChoose}</span> {strings.ruleChooseText}
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleClaim}<Badge name="CLAIMED" badges={badges} />{strings.ruleClaimIsAtomic}</span> {strings.ruleClaimIfApi} <code className="rounded bg-gray-100 dark:bg-gray-700 px-1 text-xs">409</code>{strings.ruleClaimTaken}{' '}<Badge name="PICKABLE" badges={badges} />{strings.textWord}
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleAnalyzeFirst}<Badge name="ANALYZING" badges={badges} />{strings.ruleThenImplement}<Badge name="IN_PROGRESS" badges={badges} />{strings.textWord}</span> {strings.ruleAnalyzeText}
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleConcern}<Badge name="CONCERNED" badges={badges} />{strings.ruleConcernText}</span> {strings.ruleConcernInstead}{' '}<Badge name="CLAIMED" badges={badges} /> {strings.orWord}{' '}<Badge name="PICKABLE" badges={badges} />.
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.rulePr}</span> {strings.rulePrText} <em>{strings.openWord}</em> {strings.rulePrText2}
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleDone}{' '}<Badge name="IN_REVIEW" badges={badges} />{strings.ruleDoneText2}</span> {strings.ruleDoneProvided}{' '}<Badge name="IN_PROGRESS" badges={badges} />{strings.ruleDoneAlone}
                            </li>
                            <li className="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{strings.ruleMerge}<Badge name="MERGED" badges={badges} />{strings.textWord}</span> {strings.ruleMergeText}{' '}<Badge name="COMPLETED" badges={badges} /> {strings.ruleMergeArises}
                            </li>
                        </ul>
                    </div>

                    {/* 4) Abgeleiteter Anzeige-Status */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.derivedTitle}</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {strings.derivedHintPre}<Badge name="CLAIMED" badges={badges} />, <Badge name="ANALYZING" badges={badges} />, <Badge name="IN_PROGRESS" badges={badges} />,{' '}
                                <Badge name="IN_REVIEW" badges={badges} />, <Badge name="CONCERNED" badges={badges} />, <Badge name="COMPLETED" badges={badges} />, <Badge name="MERGED" badges={badges} />{strings.derivedHintMid}{' '}<Badge name="UNKNOWN" badges={badges} /> {strings.derivedHintPost}
                            </p>
                        </div>
                        <ol className="divide-y divide-gray-50 dark:divide-gray-700">
                            <li className="flex items-start gap-3 px-6 py-3">
                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400">1</span>
                                <p className="text-sm text-gray-700 dark:text-gray-300">{strings.derived1}</p>
                            </li>
                            <li className="flex items-start gap-3 px-6 py-3">
                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400">2</span>
                                <p className="text-sm text-gray-700 dark:text-gray-300">{strings.derived2Pre} <span className="font-medium">{strings.derived2Emph}</span>{strings.derived2Post}{' '}<Badge name="BLOCKED" badges={badges} /></p>
                            </li>
                            <li className="flex items-start gap-3 px-6 py-3">
                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400">3</span>
                                <p className="text-sm text-gray-700 dark:text-gray-300">{strings.derived3Pre} <span className="font-medium">{strings.derived3Emph}</span> {strings.derived3Post}{' '}<Badge name="PICKABLE" badges={badges} /></p>
                            </li>
                            <li className="flex items-start gap-3 px-6 py-3">
                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400">4</span>
                                <p className="text-sm text-gray-700 dark:text-gray-300">{strings.derived4}{' '}<Badge name="UNKNOWN" badges={badges} />.</p>
                            </li>
                        </ol>
                    </div>

                    {/* 5) Abhängigkeiten & Regeln */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.deliveredTitle}</h3>
                            <div className="mt-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                <p><span className="font-medium">{strings.deliveredEmph}</span> {strings.deliveredAsSoon} <span className="text-gray-400 dark:text-gray-500">{strings.deliveredOr}</span> {strings.deliveredDone}{' '}
                                    <span className="text-gray-500 dark:text-gray-400">{strings.deliveredImportant} <em>{strings.openWord}</em> {strings.deliveredPrCounts}</span></p>
                                <div>
                                    <p className="font-medium">{strings.taskIs} <span className="text-indigo-600 dark:text-indigo-400">{strings.pickable}</span>{strings.ifWord} <span className="font-normal text-gray-500 dark:text-gray-400">{strings.allWord}</span> {strings.applyWord}</p>
                                    <ul className="mt-1 list-disc space-y-1 ps-5 text-gray-600 dark:text-gray-400">
                                        <li>{strings.pickNotClaimed}</li>
                                        <li>{strings.pickNoPr}</li>
                                        <li>{strings.pickStatusNone}</li>
                                        <li>{strings.pickAllPrereq}</li>
                                    </ul>
                                </div>
                                <p><span className="font-medium">{strings.blocked}</span> {strings.vs} <span className="font-medium">{strings.pickable}</span>{strings.blockedText}</p>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.bottleneckTitle}</h3>
                            <div className="mt-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                <p>{strings.bottleneckWhen} <span className="font-normal text-gray-500 dark:text-gray-400">{strings.allWord}</span> {strings.applyWord}</p>
                                <ul className="list-disc space-y-1 ps-5 text-gray-600 dark:text-gray-400">
                                    <li>{strings.bnTaskIs} <span className="font-medium">{strings.bnNot}</span> {strings.bnDone}</li>
                                    <li>{strings.bnNumberOf} <span className="font-medium">{strings.bnInTotal}</span> {strings.bnDependent}</li>
                                    <li>{strings.bnAtLeast2}</li>
                                </ul>
                                <p className="text-gray-500 dark:text-gray-400">{strings.bnPrSeq}</p>
                            </div>
                        </div>
                    </div>

                    {/* 6) CI / PR-Sync */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.ciTitle}</h3>
                        <div className="mt-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <p>{strings.ciIntro}</p>
                            <ul className="list-disc space-y-1 ps-5 text-gray-600 dark:text-gray-400">
                                <li>{strings.ciEach}</li>
                                <li>{strings.ciAs} <span className="font-medium">{strings.merged}</span> {strings.ciOnlyIf} <code className="rounded bg-gray-100 dark:bg-gray-700 px-1 text-xs">merged = true</code> {strings.ciOrA} <code className="rounded bg-gray-100 dark:bg-gray-700 px-1 text-xs">merged_at</code>{strings.ciTimestamp}</li>
                                <li>{strings.ciOnMerge}{' '}<Badge name="MERGED" badges={badges} /> {strings.ciAndTs}</li>
                                <li>{strings.ciMetrics} <span className="font-medium">{strings.ciNot}</span>{strings.ciNever}</li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </>
    );
}

FaqStatusLogic.layout = AppShell;
