import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { DurationBar, StatusDurations } from '../components/Durations.jsx';
import { interpolate, transChoice } from '../../summary/i18n.js';
import {
    PILL,
    TILE_TEXT,
    dateShort,
    deTrim,
    deviationClass,
    deviationLabel,
    formatDuration,
    formatTokens,
    relativeTime,
    shareClass,
} from '../../stats/format.js';

// „Statistik" aus dem Benutzermenü: die EIGENE Bilanz über alle sichtbaren
// Projekte. Anders als Board/Summary/Performance kommt die Auswertung fertig vom
// Server (UserStatisticsPresenter) — projektübergreifend gibt es keinen geteilten
// Store, aus dem sich das ableiten ließe. Änderungen an Tasks ziehen die Props
// per Partial-Reload nach, damit die Seite nicht veraltet neben dem Board steht.

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';

// Infobox unter dem Kopfband. Der „?"-Knopf sitzt im Kopfband selbst (dort steht
// der Seitentitel, wie bei Profil/Organisation/Changelog) — deshalb hier nur der
// Inhalt, nicht die eigene Überschrift.
function Help({ strings }) {
    return (
        <div className="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
            <div>
                <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{strings.helpScope}</div>
                <p>{strings.helpScopeText}</p>
            </div>
            <ul className="list-disc space-y-1 ps-4">
                {strings.helpBullets.map((b, i) => (
                    <li key={i}>
                        <span className="font-medium">{b.strong}</span>: {b.text}
                    </li>
                ))}
            </ul>
            <div>
                <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{strings.helpLimits}</div>
                <p>{strings.helpLimitsText}</p>
            </div>
        </div>
    );
}

function HelpToggle({ label, expanded, onToggle }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-expanded={expanded}
            title={label}
            className="text-gray-400 hover:text-indigo-600 dark:text-gray-500 dark:hover:text-indigo-400"
        >
            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                <path d="M12 17h.01" />
            </svg>
        </button>
    );
}

function Tile({ label, value, unit, sub, tone }) {
    return (
        <div className={`${CARD} p-4`}>
            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{label}</div>
            <div className={'mt-1 text-3xl font-bold ' + (tone || 'text-gray-900 dark:text-gray-100')}>
                {value}
                {unit && <span className="ms-1 text-lg font-semibold text-gray-400 dark:text-gray-500">{unit}</span>}
            </div>
            {sub && <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{sub}</div>}
        </div>
    );
}

// Kennzahl in den Panels „Qualität" / „Umfang".
function Metric({ label, value, sub, tone }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-gray-50 py-1.5 last:border-0 dark:border-gray-700/60">
            <span className="text-sm text-gray-500 dark:text-gray-400">{label}</span>
            <span className="text-end">
                <span className={'text-sm font-semibold ' + (tone || 'text-gray-800 dark:text-gray-200')}>{value}</span>
                {sub && <span className="ms-1 text-xs text-gray-400 dark:text-gray-500">{sub}</span>}
            </span>
        </div>
    );
}

// Wochenleistung als gestapelte Säulen: unten die selbst gelieferten SP, darüber
// die SP der gereviewten Tasks. Getrennt gestapelt und nicht addiert, weil die SP
// eines gereviewten Tasks dem Autor gehören — sie stehen für den Umfang des
// Reviewten, nicht für eigene Lieferung.
//
// Reines Flex-Diagramm ohne SVG; die Höhe skaliert auf die stärkste Woche
// (Summe beider Reihen), leere Wochen bleiben als Grundlinie sichtbar, sonst
// täuschte die Kurve Kontinuität vor.
const WEEKLY_SERIES = {
    delivered: 'bg-indigo-500 dark:bg-indigo-400',
    reviewed: 'bg-teal-400 dark:bg-teal-500',
};

// Umschalter Story Points ↔ Anzahl Tasks. Beide Größen stehen weiterhin im
// Tooltip jeder Säule — der Toggle bestimmt nur, was die Höhe trägt.
function WeeklyToggle({ value, onChange, strings }) {
    return (
        <div className="inline-flex items-center gap-1 rounded-full bg-gray-100 p-1 dark:bg-gray-700">
            {[
                { key: 'sp', label: strings.storyPoints },
                { key: 'tasks', label: strings.tasks },
            ].map((option) => (
                <button
                    key={option.key}
                    type="button"
                    onClick={() => onChange(option.key)}
                    aria-pressed={value === option.key}
                    className={
                        'rounded-full px-3 py-1 text-xs font-medium ' +
                        (value === option.key
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                    }
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

function WeeklyChart({ weekly, metric, strings }) {
    const isSp = metric === 'sp';
    const own = (w) => (isSp ? w.sp : w.tasks);
    const rev = (w) => (isSp ? w.reviewedSp : w.reviewedTasks);
    const max = Math.max(1, ...weekly.map((w) => own(w) + rev(w)));

    return (
        <div className="mt-4">
            <div className="mb-1 flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500">
                {/* Skalenmarke, sonst sagt die Balkenhöhe nichts. */}
                <span>{max} {isSp ? strings.storyPoints : strings.tasks}</span>
                <span className="flex items-center gap-3">
                    <span className="flex items-center gap-1">
                        <span className={'inline-block h-2 w-2 rounded-sm ' + WEEKLY_SERIES.delivered} />
                        {strings.weeklyDelivered}
                    </span>
                    <span className="flex items-center gap-1">
                        <span className={'inline-block h-2 w-2 rounded-sm ' + WEEKLY_SERIES.reviewed} />
                        {strings.weeklyReviewed}
                    </span>
                </span>
            </div>
            <div className="flex items-end gap-1.5">
                {weekly.map((w) => {
                    const share = (value) => (value > 0 ? Math.max(3, (value / max) * 100) : 0);
                    // Der Tooltip nennt beide Größen, unabhängig vom Toggle.
                    const tip = [
                        w.label,
                        interpolate(strings.weeklyTipDelivered, { sp: w.sp, tasks: w.tasks }),
                        interpolate(strings.weeklyTipReviewed, { sp: w.reviewedSp, tasks: w.reviewedTasks }),
                    ].join('\n');

                    return (
                        <div key={w.key} className="flex min-w-0 flex-1 flex-col items-center gap-1">
                            {/* Die Spur hat eine FESTE Höhe und ist der
                                Bezugsrahmen der Segmente. Prozenthöhen brauchen
                                einen Elternteil mit definiter Höhe — in einer per
                                flex-1 gewachsenen Box kollabieren sie auf 0, was
                                das Diagramm leer aussehen ließ. */}
                            <div className="relative h-32 w-full rounded-t bg-gray-100 dark:bg-gray-900/50" title={tip}>
                                {/* inset-0, NICHT nur bottom-0: die Segmente tragen
                                    Prozenthöhen und brauchen dafür einen Elternteil
                                    mit definiter Höhe. flex-col-reverse stapelt sie
                                    von unten. */}
                                <div className="absolute inset-0 flex flex-col-reverse">
                                    {own(w) > 0 && (
                                        <div className={WEEKLY_SERIES.delivered} style={{ height: `${share(own(w))}%` }} />
                                    )}
                                    {rev(w) > 0 && (
                                        <div
                                            className={'rounded-t ' + WEEKLY_SERIES.reviewed}
                                            style={{ height: `${share(rev(w))}%` }}
                                        />
                                    )}
                                </div>
                            </div>
                            <div className="w-full truncate text-center text-[10px] text-gray-400 dark:text-gray-500">
                                {w.label}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}


// Zwischenüberschrift innerhalb eines Panels — trennt im Qualitäts-Panel die
// eigenen Tasks von der eigenen Review-Arbeit, die sonst leicht verwechselt wird.
function Group({ label }) {
    return (
        <div className="mt-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400 first:mt-0 dark:text-gray-500">
            {label}
        </div>
    );
}

export default function Statistics({ stats, person, strings, urls }) {
    const [help, setHelp] = useState(false);
    // Wochenleistung: Höhe nach Story Points oder nach Anzahl Tasks.
    const [weeklyMetric, setWeeklyMetric] = useState('sp');

    // Eigene Seite vs. die eines Kollegen (nur der Organisations-Owner kommt dort
    // hin): Titel und Untertitel nennen dann die Person, damit niemand fremde
    // Zahlen für seine eigenen hält.
    const title = person.isSelf ? strings.title : interpolate(strings.titleOther, { name: person.name });
    const subtitle = person.isSelf ? strings.subtitle : interpolate(strings.subtitleOther, { name: person.name });

    // Task-Änderungen (Socket-Event aus resources/js/app.js) ziehen die Auswertung
    // nach — nur die `stats`-Prop, Labels und Shell bleiben. Entkoppelt über eine
    // kurze Wartezeit: auf einem aktiven Board kommen die Events in Schüben, und
    // die Aggregation ist projektübergreifend, also nicht kostenlos.
    useEffect(() => {
        let timer = null;
        const onEntity = (e) => {
            if (e.detail?.entity !== 'task') return;
            clearTimeout(timer);
            timer = setTimeout(() => router.reload({ only: ['stats'] }), 2000);
        };
        window.addEventListener('planstack:entity-changed', onEntity);
        return () => {
            clearTimeout(timer);
            window.removeEventListener('planstack:entity-changed', onEntity);
        };
    }, []);

    const { kpis, quality, volume, weekly, statusBuckets, statusDurations, projects, recent } = stats;
    const locale = (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de';

    const accuracyPct = kpis.accuracyTotal ? Math.round((kpis.accuracyHits / kpis.accuracyTotal) * 100) : null;
    const firstPassPct = quality.reviewedCount
        ? Math.round((quality.approved / quality.reviewedCount) * 100)
        : null;
    const hasAnything = kpis.totalTasks > 0;

    const openTotal = statusBuckets.reduce((a, b) => a + b.count, 0);

    // Balken-Maßstab je Tabelle — jeweils der größte Wert DERSELBEN Art, die die
    // Breite trägt: beim Projekt der Median je Task, bei der einzelnen Task ihre
    // Gesamtzeit (siehe DurationBar).
    const projectScale = Math.max(0, ...projects.map((p) => p.durations?.medianTaskDays ?? 0));
    const recentScale = Math.max(0, ...recent.map((t) => t.durations?.totalDays ?? 0));

    return (
        <>
            <Head><title>{title}</title></Head>

            <PageBands
                header={
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">{title}</h2>
                        <HelpToggle
                            label={strings.showHideExplanation}
                            expanded={help}
                            onToggle={() => setHelp((v) => !v)}
                        />
                    </div>
                }
            />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {help && <Help strings={strings} />}

                    <p className="text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>

                    {!hasAnything && (
                        <div className={`${CARD} px-6 py-10 text-center`}>
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{strings.emptyTitle}</h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.emptyText}</p>
                            <a
                                href={urls.projects}
                                className="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                {strings.emptyToProjects}
                            </a>
                        </div>
                    )}

                    {hasAnything && (
                        <>
                            {/* KPI-Kacheln */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Tile
                                    label={strings.kpiDelivered}
                                    value={kpis.deliveredSp}
                                    unit="SP"
                                    sub={transChoice(strings.kpiDeliveredSub, kpis.deliveredTasks, {
                                        tasks: kpis.deliveredTasks,
                                        projects: kpis.projectCount,
                                    })}
                                />
                                <Tile
                                    label={strings.kpiOpen}
                                    value={kpis.openTasks}
                                    sub={interpolate(strings.kpiOpenSub, { sp: kpis.openSp })}
                                />
                                <Tile
                                    label={strings.kpiCycle}
                                    value={formatDuration(kpis.cycleMedianDays, strings) || strings.none}
                                    sub={strings.kpiCycleSub}
                                    tone={kpis.cycleMedianDays === null ? TILE_TEXT.gray : undefined}
                                />
                                <Tile
                                    label={strings.kpiAccuracy}
                                    value={accuracyPct === null ? strings.none : `${accuracyPct} %`}
                                    sub={interpolate(strings.kpiAccuracySub, {
                                        hits: kpis.accuracyHits,
                                        total: kpis.accuracyTotal,
                                    })}
                                    tone={TILE_TEXT[shareClass(accuracyPct)]}
                                />
                            </div>

                            {/* Verlauf je Kalenderwoche */}
                            <div className={`${CARD} p-5`}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.weeklyTitle}</h2>
                                        <p className="text-xs text-gray-400 dark:text-gray-500">{strings.weeklySub}</p>
                                    </div>
                                    <WeeklyToggle value={weeklyMetric} onChange={setWeeklyMetric} strings={strings} />
                                </div>
                                {weekly.length > 0 ? (
                                    <WeeklyChart weekly={weekly} metric={weeklyMetric} strings={strings} />
                                ) : (
                                    <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">{strings.noDeliveries}</p>
                                )}
                            </div>

                            {/* Verweildauer je Status (Verlauf aus dem Protokoll) */}
                            <div className={`${CARD} p-5`}>
                                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.durationsTitle}</h2>
                                <p className="text-xs text-gray-400 dark:text-gray-500">{strings.durationsSub}</p>
                                {statusDurations.length > 0 ? (
                                    <StatusDurations rows={statusDurations} strings={strings} />
                                ) : (
                                    <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">{strings.durationsEmpty}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                {/* Offene Tasks nach Status */}
                                <div className={`${CARD} p-5`}>
                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.statusTitle}</h2>
                                    <p className="text-xs text-gray-400 dark:text-gray-500">{strings.statusSub}</p>
                                    {statusBuckets.length > 0 ? (
                                        <div className="mt-4 space-y-3">
                                            {statusBuckets.map((b) => (
                                                <div key={b.key}>
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + b.badge}>
                                                            {b.label}
                                                        </span>
                                                        <span className="text-gray-500 dark:text-gray-400">
                                                            {b.count}
                                                            {b.sp > 0 ? ` · ${b.sp} SP` : ''}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                                                        <div
                                                            className={'h-full ' + b.bar}
                                                            style={{ width: `${openTotal ? (b.count / openTotal) * 100 : 0}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                            {kpis.oldestOpenClaimDays !== null && (
                                                <div className="pt-2">
                                                    <Metric
                                                        label={strings.mOldestClaim}
                                                        value={formatDuration(kpis.oldestOpenClaimDays, strings)}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">{strings.noOpenTasks}</p>
                                    )}
                                </div>

                                {/* Qualität */}
                                <div className={`${CARD} p-5`}>
                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.qualityTitle}</h2>
                                    <p className="text-xs text-gray-400 dark:text-gray-500">{strings.qualitySub}</p>
                                    <div className="mt-3">
                                        {/* Die Trennung ist nötig, weil sonst unklar bleibt,
                                            WESSEN Review gemeint ist: alles hier bis auf die
                                            letzte Gruppe betrifft die EIGENEN Tasks (fremdes
                                            Review über eigene Arbeit). */}
                                        <Group label={strings.groupOwnTasks} />
                                        {/* Der einzige Verlaufswert des Panels (Audit-Log) —
                                            steht deshalb oben und bleibt nach einer
                                            Freigabe erhalten. */}
                                        <Metric
                                            label={strings.mRework}
                                            value={`${quality.reworkTasks} / ${quality.tasksTotal}`}
                                            sub={quality.reworkMultiple > 0 ? interpolate(strings.mReworkMultiple, { count: quality.reworkMultiple }) : null}
                                            tone={quality.reworkTasks > 0 ? TILE_TEXT.amber : undefined}
                                        />
                                        <Metric
                                            label={strings.mApproved}
                                            value={quality.reviewedCount ? `${quality.approved} / ${quality.reviewedCount}` : strings.none}
                                            sub={firstPassPct !== null ? `${firstPassPct} %` : null}
                                            tone={TILE_TEXT[shareClass(firstPassPct)]}
                                        />
                                        <Metric
                                            label={strings.mRequestChanges}
                                            value={quality.requestChanges}
                                            tone={quality.requestChanges > 0 ? TILE_TEXT.amber : undefined}
                                        />
                                        {/* Ohne PR-Sync liefert der Server null — dann „—"
                                            plus Grund, damit fehlende Messung nicht wie ein
                                            gutes Ergebnis aussieht. */}
                                        <Metric
                                            label={strings.mCiFailed}
                                            value={quality.ciFailed === null ? strings.none : quality.ciFailed}
                                            sub={quality.ciFailed === null ? strings.neverSynced : null}
                                            tone={quality.ciFailed > 0 ? TILE_TEXT.red : undefined}
                                        />
                                        <Metric
                                            label={strings.mOpenThreads}
                                            value={quality.openThreads === null ? strings.none : quality.openThreads}
                                            sub={quality.openThreads === null ? strings.neverSynced : null}
                                        />
                                        <Metric label={strings.mConcerns} value={quality.concerns} />
                                        <Metric
                                            label={strings.mCritical}
                                            value={quality.critical === null ? strings.none : quality.critical}
                                            sub={
                                                quality.critical === null
                                                    ? strings.criticalityUnset
                                                    : interpolate(strings.criticalityKnown, {
                                                        known: quality.criticalityKnown,
                                                        total: quality.tasksTotal,
                                                    })
                                            }
                                        />
                                        <Metric
                                            label={strings.mMedianDeviation}
                                            value={deviationLabel(kpis.medianDeviationPct) || strings.none}
                                            tone={TILE_TEXT[deviationClass(kpis.medianDeviationPct)]}
                                        />
                                        <Group label={strings.groupAsReviewer} />
                                        <Metric
                                            label={strings.mReviewsGiven}
                                            value={kpis.reviewsGiven}
                                            sub={kpis.reviewedAuthors ? `${strings.mReviewedAuthors}: ${kpis.reviewedAuthors}` : null}
                                        />
                                    </div>
                                </div>

                                {/* Umfang + Tempo */}
                                <div className={`${CARD} p-5`}>
                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.volumeTitle}</h2>
                                    <div className="mt-3">
                                        <Metric
                                            label={strings.mVelocity}
                                            value={kpis.spPerWeek !== null ? `${deTrim(kpis.spPerWeek)} ${strings.unitSpWeek}` : strings.none}
                                        />
                                        <Metric
                                            label={strings.mCycleAvg}
                                            value={formatDuration(kpis.cycleAvgDays, strings) || strings.none}
                                        />
                                        <Metric
                                            label={strings.mTimePerSp}
                                            value={formatDuration(kpis.timePerSpDays, strings) || strings.none}
                                        />
                                        <Metric label={strings.mPrs} value={volume.prs} />
                                        <Metric label={strings.mFiles} value={volume.files} />
                                        <Metric label={strings.mLines} value={`+${volume.additions} / −${volume.deletions}`} />
                                        <Metric label={strings.mCommits} value={volume.commits} />
                                        <Metric
                                            label={strings.mComments}
                                            value={volume.comments}
                                            sub={`${strings.mReviewComments}: ${volume.reviewComments}`}
                                        />
                                        <Metric label={strings.mTokens} value={formatTokens(volume.tokens)} />
                                        <Metric
                                            label={strings.mLastDelivery}
                                            value={relativeTime(kpis.lastDeliveryAt, locale) || strings.none}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Nach Projekt */}
                            <div>
                                <h2 className="mb-2 font-semibold text-gray-900 dark:text-gray-100">{strings.projectsTitle}</h2>
                                <div className={`overflow-hidden ${CARD}`}>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-gray-100 text-left text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                                    <th className="px-4 py-2 font-medium">{strings.colProject}</th>
                                                    <th className="px-4 py-2 font-medium">{strings.colDelivered}</th>
                                                    <th className="px-4 py-2 font-medium">{strings.colOpen}</th>
                                                    <th className="px-4 py-2 font-medium">{strings.colCycle}</th>
                                                    <th className="px-4 py-2 text-right font-medium">{strings.colVolume}</th>
                                                    <th className="w-44 px-4 py-2 text-right font-medium">{strings.colDuration}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {projects.map((p) => (
                                                    <tr key={p.alias} className="border-b border-gray-50 last:border-0 dark:border-gray-700">
                                                        <td className="px-4 py-3">
                                                            <a href={p.url} className="font-medium text-indigo-700 hover:underline dark:text-indigo-400">
                                                                {p.name}
                                                            </a>
                                                            <div className="font-mono text-xs text-gray-400 dark:text-gray-500">{p.alias}</div>
                                                        </td>
                                                        <td className="px-4 py-3 text-gray-800 dark:text-gray-200">
                                                            {p.deliveredTasks}
                                                            <span className="text-xs text-gray-400 dark:text-gray-500"> / {p.totalTasks}</span>
                                                            <div className="text-xs text-gray-400 dark:text-gray-500">{p.deliveredSp} SP</div>
                                                        </td>
                                                        <td className="px-4 py-3 text-gray-800 dark:text-gray-200">
                                                            {p.openTasks}
                                                            <div className="text-xs text-gray-400 dark:text-gray-500">{p.openSp} SP</div>
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                            {formatDuration(p.cycleMedianDays, strings) || (
                                                                <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right text-xs text-gray-500 dark:text-gray-400">
                                                            <div>{p.files} {strings.mFiles}</div>
                                                            <div>{p.commits} {strings.mCommits}</div>
                                                        </td>
                                                        <td className="w-44 px-4 py-3">
                                                            <DurationBar durations={p.durations} scale={projectScale} variant="group" strings={strings} />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {/* Zuletzt geliefert */}
                            <div>
                                <h2 className="mb-2 font-semibold text-gray-900 dark:text-gray-100">{strings.recentTitle}</h2>
                                <div className={`overflow-hidden ${CARD}`}>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-gray-100 text-left text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                                    <th className="px-4 py-2 font-medium">{strings.colTask}</th>
                                                    <th className="px-4 py-2 font-medium">SP</th>
                                                    <th className="px-4 py-2 font-medium">{strings.colMerged}</th>
                                                    <th className="px-4 py-2 font-medium">{strings.colCycle}</th>
                                                    <th className="px-4 py-2 font-medium">{strings.colFiles}</th>
                                                    <th className="px-4 py-2 text-right font-medium">{strings.colDeviation}</th>
                                                    <th className="w-44 px-4 py-2 text-right font-medium">{strings.colDuration}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {recent.map((t) => (
                                                    <tr key={`${t.projectAlias}-${t.name}`} className="border-b border-gray-50 last:border-0 dark:border-gray-700">
                                                        {/* w-full max-w-0: die Task-Spalte nimmt den Platz, den
                                                            die übrigen Spalten übrig lassen, und klemmt ihn
                                                            fest. Ohne die Klemme dehnt das auto-Tabellenlayout
                                                            die Spalte bis zum längsten Text aus und `truncate`
                                                            greift nie — die Tabelle wurde dadurch breit. */}
                                                        <td className="w-full max-w-0 px-4 py-3">
                                                            <div className="truncate">
                                                                <a href={t.url} className="font-mono font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                                                                    {t.name}
                                                                </a>
                                                                <span className="ms-2 font-mono text-xs text-gray-400 dark:text-gray-500">{t.projectAlias}</span>
                                                            </div>
                                                            {/* Volltext im Tooltip, damit die Kürzung nichts
                                                                unerreichbar macht. */}
                                                            <div className="truncate text-xs text-gray-400 dark:text-gray-500" title={t.summary}>
                                                                {t.summary}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{t.sp ?? strings.none}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                                            {dateShort(t.mergedAt)}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                                            {formatDuration(t.cycleDays, strings) || strings.none}
                                                        </td>
                                                        <td className="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                                            {(t.filesEstimated ?? '—') + ' → ' + (t.filesActual ?? '—')}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            {t.deviationPct === null ? (
                                                                <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>
                                                            ) : (
                                                                <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + PILL[deviationClass(t.deviationPct)]}>
                                                                    {deviationLabel(t.deviationPct)}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="w-44 px-4 py-3">
                                                            <DurationBar durations={t.durations} scale={recentScale} variant="task" strings={strings} />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {recent.length === 0 && (
                                        <p className="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                            {strings.noDeliveries}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
Statistics.layout = AppShell;
