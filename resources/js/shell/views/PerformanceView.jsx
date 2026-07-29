import React, { useMemo, useState } from 'react';
import PageHead from '../components/PageHead.jsx';
import { useProjectData } from '../../data/useProjectData';
import { derivePerformance } from '../../performance/derive.js';
import { interpolate } from '../../summary/i18n.js';
import { KpiTilesSkeleton, CardsSkeleton, TableSkeleton } from '../components/Skeleton.jsx';

// Performance je Mitarbeiter — Owner-only Unterseite des ProjectWorkspace. Die
// Daten kommen aus DEMSELBEN geteilten Store wie Board/Summary/Kalibrierung; die
// Aggregation je Person passiert clientseitig (performance/derive.js) und
// aktualisiert sich live über das entity-changed-Event.

const tileText = (c) =>
    ({
        green: 'text-green-600 dark:text-green-400',
        amber: 'text-amber-600 dark:text-amber-400',
        red: 'text-red-600 dark:text-red-400',
    })[c] || 'text-gray-300 dark:text-gray-600';

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';
const TILE = `${CARD} p-4`;

function Help({ strings }) {
    return (
        <div className="space-y-4">
            <div>
                <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{strings.helpAttribution}</div>
                <p>{strings.helpAttributionText}</p>
            </div>
            <div>
                <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{strings.helpMetrics}</div>
                <ul className="list-disc space-y-1 ps-4">
                    {strings.helpMetricBullets.map((b, i) => (
                        <li key={i}>
                            <span className="font-medium">{b.strong}</span>: {b.text}
                        </li>
                    ))}
                </ul>
            </div>
            <div>
                <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{strings.helpReviews}</div>
                <p>{strings.helpReviewsText}</p>
            </div>
            <div>
                <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{strings.helpLimits}</div>
                <p>{strings.helpLimitsText}</p>
            </div>
        </div>
    );
}

function Avatar({ person }) {
    return (
        <span
            className={
                'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ' +
                person.avatarClass
            }
            aria-hidden="true"
        >
            {person.initials}
        </span>
    );
}

// Eine Kennzahl in der Detailkarte: Label + Wert (+ optionale Zusatzzeile).
function Metric({ label, value, sub, tone }) {
    return (
        <div>
            <div className="text-xs text-gray-400 dark:text-gray-500">{label}</div>
            <div className={'text-sm font-semibold ' + (tone || 'text-gray-800 dark:text-gray-200')}>{value}</div>
            {sub && <div className="text-xs text-gray-400 dark:text-gray-500">{sub}</div>}
        </div>
    );
}

// Gestapelter Balken „geliefert / offen" für den SP-Vergleich.
function SpBar({ person, scales, strings }) {
    const total = Math.max(1, scales.maxSp);
    const donePct = (person.deliveredSp / total) * 100;
    const openPct = (person.openSp / total) * 100;
    return (
        <div className="flex items-center gap-3">
            <div className="w-32 shrink-0 truncate text-sm text-gray-700 dark:text-gray-300" title={person.name}>
                {person.name}
            </div>
            <div className="h-3 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                <div className="flex h-full">
                    <div
                        className="h-full bg-indigo-500"
                        style={{ width: `${donePct}%` }}
                        title={`${person.deliveredSp} SP`}
                    />
                    <div
                        className="h-full bg-indigo-200 dark:bg-indigo-900"
                        style={{ width: `${openPct}%` }}
                        title={`${person.openSp} SP ${strings.chartOpen}`}
                    />
                </div>
            </div>
            <div className="w-24 shrink-0 text-right text-sm tabular-nums text-gray-600 dark:text-gray-400">
                {person.deliveredSp}
                <span className="text-gray-400 dark:text-gray-500"> / {person.totalSp} SP</span>
            </div>
        </div>
    );
}

function CycleBar({ person, scales, strings }) {
    const pct = person.cycleMedian !== null ? Math.max(3, (person.cycleMedian / scales.maxCycle) * 100) : 0;
    return (
        <div className="flex items-center gap-3">
            <div className="w-32 shrink-0 truncate text-sm text-gray-700 dark:text-gray-300" title={person.name}>
                {person.name}
            </div>
            <div className="h-3 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                {person.cycleMedian !== null && <div className="h-full bg-teal-500" style={{ width: `${pct}%` }} />}
            </div>
            <div className="w-24 shrink-0 text-right text-sm tabular-nums text-gray-600 dark:text-gray-400">
                {person.cycleMedianLabel || strings.none}
            </div>
        </div>
    );
}

// Aufgeklappte Detailkarte einer Person: alle Task-Statistiken, gruppiert.
function PersonDetails({ person, strings }) {
    const s = strings;
    return (
        <div className="space-y-5 border-t border-gray-100 bg-gray-50/60 px-4 py-4 dark:border-gray-700 dark:bg-gray-900/30">
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-4">
                <div>
                    <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {s.detailsDelivery}
                    </div>
                    <div className="space-y-2">
                        <Metric label={s.mDeliveredTasks} value={`${person.deliveredCount} / ${person.tasksTotal}`} />
                        <Metric label={s.mDeliveredSp} value={`${person.deliveredSp} / ${person.totalSp} SP`} />
                        <Metric
                            label={s.mVelocity}
                            value={person.spPerWeekLabel ? `${person.spPerWeekLabel} ${s.unitSpWeek}` : s.none}
                        />
                        <Metric label={s.mCycleMedian} value={person.cycleMedianLabel || s.none} sub={person.cycleAvgLabel ? `Ø ${person.cycleAvgLabel}` : null} />
                        <Metric label={s.mTimePerSp} value={person.timePerSpLabel || s.none} />
                    </div>
                </div>

                <div>
                    <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {s.detailsQuality}
                    </div>
                    <div className="space-y-2">
                        <Metric
                            label={s.mAccuracy}
                            value={
                                person.accuracyPct === null
                                    ? s.none
                                    : `${person.accuracyPct} %`
                            }
                            sub={
                                person.accuracyTotal
                                    ? interpolate(s.ofTotal, { part: person.accuracyHits, total: person.accuracyTotal })
                                    : null
                            }
                            tone={tileText(person.accuracyClass)}
                        />
                        <Metric label={s.mMedianDeviation} value={person.medianDeviationLabel || s.none} />
                        {/* Verlaufskennzahl aus dem Audit-Log — bleibt erhalten,
                            auch wenn danach freigegeben wurde. */}
                        <Metric
                            label={s.mRework}
                            value={`${person.reworkTasks} / ${person.tasksTotal}`}
                            sub={person.reworkMultiple > 0 ? interpolate(s.mReworkMultiple, { count: person.reworkMultiple }) : null}
                            tone={person.reworkTasks > 0 ? tileText('amber') : undefined}
                        />
                        <Metric
                            label={s.mApproved}
                            value={person.reviewedCount ? `${person.approved} / ${person.reviewedCount}` : s.none}
                        />
                        <Metric
                            label={s.mRequestChanges}
                            value={person.requestChanges}
                            tone={person.requestChanges > 0 ? tileText('amber') : undefined}
                        />
                        <Metric
                            label={s.mCiFailed}
                            value={person.ciFailed === null ? s.none : person.ciFailed}
                            sub={person.ciFailed === null ? s.neverSynced : null}
                            tone={person.ciFailed > 0 ? tileText('red') : undefined}
                        />
                        <Metric
                            label={s.mOpenThreads}
                            value={person.openThreads === null ? s.none : person.openThreads}
                            sub={person.openThreads === null ? s.neverSynced : null}
                        />
                        <Metric label={s.mConcerns} value={person.concerns} />
                        <Metric
                            label={s.mCriticality}
                            value={person.critical === null ? s.none : person.critical}
                            sub={
                                person.critical === null
                                    ? s.criticalityUnset
                                    : interpolate(s.criticalityKnown, { known: person.criticalityKnown, total: person.tasksTotal })
                            }
                        />
                    </div>
                </div>

                <div>
                    <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {s.detailsVolume}
                    </div>
                    <div className="space-y-2">
                        <Metric label={s.mFiles} value={person.files} />
                        <Metric label={s.mLines} value={`+${person.additions} / −${person.deletions}`} />
                        <Metric label={s.mCommits} value={person.commits} />
                        <Metric label={s.mTokens} value={person.tokensLabel} />
                        <Metric label={s.mWip} value={person.wip} sub={person.oldestClaimLabel ? `${s.mOldestClaim}: ${person.oldestClaimLabel}` : null} />
                        <Metric
                            label={s.mBlocked}
                            value={person.exceptions}
                            tone={person.exceptions > 0 ? tileText('amber') : undefined}
                        />
                        <Metric label={s.mUnlocks} value={person.unlocks} />
                    </div>
                </div>

                <div>
                    <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {s.detailsReviews}
                    </div>
                    <div className="space-y-2">
                        <Metric
                            label={s.mReviewsGiven}
                            value={person.reviewsGiven}
                            sub={person.reviewedAuthors ? `${s.mReviewsAuthors}: ${person.reviewedAuthors}` : null}
                        />
                    </div>
                </div>
            </div>

            {person.openTasks.length > 0 && (
                <div>
                    <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {s.openTasksTitle}
                    </div>
                    <ul className="space-y-1.5">
                        {person.openTasks.map((t) => (
                            <li key={t.id} className="flex flex-wrap items-center gap-2 text-sm">
                                <a href={t.url} className="font-mono font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                                    {t.name}
                                </a>
                                <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + t.statusBadge}>
                                    {t.statusLabel}
                                </span>
                                {t.sp !== null && (
                                    <span className="text-xs text-gray-500 dark:text-gray-400">{t.sp} SP</span>
                                )}
                                {t.ageLabel && (
                                    <span className="text-xs text-gray-400 dark:text-gray-500">{t.ageLabel}</span>
                                )}
                                {t.ciFailed && (
                                    <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                        CI
                                    </span>
                                )}
                                {t.openThreads > 0 && (
                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        {t.openThreads} 💬
                                    </span>
                                )}
                                <span className="min-w-0 flex-1 truncate text-xs text-gray-400 dark:text-gray-500">
                                    {t.summary}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

export default function PerformanceView({ project, strings }) {
    const { tasks, statusConfig, status, error } = useProjectData(project.alias);

    const data = useMemo(() => {
        if (status !== 'ready' || !statusConfig) return null;
        return derivePerformance({
            tasks,
            statusConfig,
            strings,
            taskUrlTemplate: project.taskUrlTemplate,
        });
    }, [tasks, statusConfig, status, strings, project.taskUrlTemplate]);

    const [sort, setSort] = useState('deliveredSp');
    const [expanded, setExpanded] = useState(null);

    const people = useMemo(() => {
        if (!data) return [];
        // Zykluszeit aufsteigend (kürzer = besser), Name alphabetisch, alles
        // andere absteigend.
        const key = {
            deliveredSp: 'sortDeliveredSp',
            delivered: 'sortDelivered',
            cycle: 'sortCycle',
            accuracy: 'sortAccuracy',
            open: 'sortOpen',
            reviews: 'sortReviews',
            name: 'sortName',
        }[sort];
        const rows = data.people.slice();
        if (sort === 'name') return rows.sort((a, b) => a.sortName.localeCompare(b.sortName));
        if (sort === 'cycle') return rows.sort((a, b) => a.sortCycle - b.sortCycle);
        return rows.sort((a, b) => b[key] - a[key]);
    }, [data, sort]);

    const team = data?.team;

    return (
        <div className="space-y-6">
            <PageHead
                title={strings.title}
                toggleLabel={strings.showHideExplanation}
                meta={
                    <span className="text-sm text-gray-400 dark:text-gray-500">{strings.ownerOnlyNote}</span>
                }
            >
                <Help strings={strings} />
            </PageHead>

            <p className="text-sm text-gray-500 dark:text-gray-400">{strings.subtitle}</p>

            {status !== 'ready' && status !== 'error' && (
                <>
                    <KpiTilesSkeleton count={4} />
                    <CardsSkeleton count={2} cols={2} bodyClass="h-56" />
                    <TableSkeleton rows={5} cols={7} />
                </>
            )}
            {status === 'error' && (
                <p className="text-sm text-red-600 dark:text-red-400">
                    {interpolate(strings.loadError, { message: error || '' })}
                </p>
            )}

            {data && team && (
                <>
                    {/* Team-Kacheln */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className={TILE}>
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.teamPeople}</div>
                            <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{team.people}</div>
                            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {interpolate(strings.teamPeopleSub, { with_tasks: team.peopleWithOpen })}
                            </div>
                        </div>

                        <div className={TILE}>
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.teamDelivered}</div>
                            <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{team.deliveredSp} <span className="text-lg font-semibold text-gray-400 dark:text-gray-500">SP</span></div>
                            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {interpolate(strings.teamDeliveredSub, { tasks: team.deliveredCount, sp: team.deliveredSp })}
                            </div>
                        </div>

                        <div className={TILE}>
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.teamCycle}</div>
                            <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {team.cycleMedianLabel || <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>}
                            </div>
                            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.teamCycleSub}</div>
                        </div>

                        <div className={TILE}>
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.teamAccuracy}</div>
                            <div className={'mt-1 text-3xl font-bold ' + tileText(team.accuracyClass)}>
                                {team.accuracyPct === null ? strings.none : `${team.accuracyPct} %`}
                            </div>
                            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {interpolate(strings.teamAccuracySub, { hits: team.accuracyHits, total: team.accuracyTotal })}
                            </div>
                        </div>
                    </div>

                    {data.unassigned.count > 0 && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                            <span className="font-medium text-gray-700 dark:text-gray-300">{strings.unassigned}</span>
                            {': '}
                            {data.unassigned.note}
                        </div>
                    )}

                    {/* Vergleichsdiagramme */}
                    {people.length > 0 && (
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div className={`${CARD} p-5`}>
                                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.chartDeliveredSp}</h2>
                                <p className="text-xs text-gray-400 dark:text-gray-500">{strings.chartDeliveredSpSub}</p>
                                <div className="mt-4 space-y-2.5">
                                    {people.map((p) => (
                                        <SpBar key={p.id} person={p} scales={data.scales} strings={strings} />
                                    ))}
                                </div>
                            </div>

                            <div className={`${CARD} p-5`}>
                                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.chartCycle}</h2>
                                <p className="text-xs text-gray-400 dark:text-gray-500">{strings.chartCycleSub}</p>
                                <div className="mt-4 space-y-2.5">
                                    {people.map((p) => (
                                        <CycleBar key={p.id} person={p} scales={data.scales} strings={strings} />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Sortierung */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.tableTitle}</h2>
                        <label className="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            {strings.sort}
                            <select
                                value={sort}
                                onChange={(e) => setSort(e.target.value)}
                                className="rounded-md border-gray-300 py-1 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            >
                                <option value="deliveredSp">{strings.sortDeliveredSp}</option>
                                <option value="delivered">{strings.sortDelivered}</option>
                                <option value="cycle">{strings.sortCycle}</option>
                                <option value="accuracy">{strings.sortAccuracy}</option>
                                <option value="open">{strings.sortOpen}</option>
                                <option value="reviews">{strings.sortReviews}</option>
                                <option value="name">{strings.sortName}</option>
                            </select>
                        </label>
                    </div>

                    {/* Tabelle je Person, aufklappbar */}
                    <div className={`overflow-hidden ${CARD}`}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 text-left text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                        <th className="px-4 py-2 font-medium">{strings.colPerson}</th>
                                        <th className="px-4 py-2 font-medium">{strings.colDelivered}</th>
                                        <th className="px-4 py-2 font-medium">{strings.colOpen}</th>
                                        <th className="px-4 py-2 font-medium">{strings.colCycle}</th>
                                        <th className="px-4 py-2 font-medium">{strings.colAccuracy}</th>
                                        <th className="px-4 py-2 font-medium">{strings.colQuality}</th>
                                        <th className="px-4 py-2 font-medium">{strings.colVolume}</th>
                                        <th className="px-4 py-2 text-right font-medium">{strings.colReviews}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {people.map((p) => {
                                        const open = expanded === p.id;
                                        return (
                                            <React.Fragment key={p.id}>
                                                <tr
                                                    className="cursor-pointer border-b border-gray-50 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/30"
                                                    onClick={() => setExpanded(open ? null : p.id)}
                                                    aria-expanded={open}
                                                    title={open ? strings.hideDetails : strings.showDetails}
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar person={p} />
                                                            <div className="min-w-0">
                                                                <div className="truncate font-medium text-gray-900 dark:text-gray-100">{p.name}</div>
                                                                <div className="text-xs text-gray-400 dark:text-gray-500">
                                                                    {p.tasksTotal} {strings.tasks}
                                                                    {p.totalSp > 0 ? ` · ${p.totalSp} SP` : ''}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-gray-800 dark:text-gray-200">
                                                            {p.deliveredCount} <span className="text-xs font-normal text-gray-400 dark:text-gray-500">/ {p.tasksTotal}</span>
                                                        </div>
                                                        <div className="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                                                            <div className="h-full bg-indigo-500" style={{ width: `${p.deliveredSharePct}%` }} />
                                                        </div>
                                                        <div className="mt-1 text-xs text-gray-400 dark:text-gray-500">{p.deliveredSp} SP</div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-gray-800 dark:text-gray-200">{p.openCount}</div>
                                                        <div className="text-xs text-gray-400 dark:text-gray-500">
                                                            {p.openSp} SP
                                                            {p.oldestClaimLabel ? ` · ${p.oldestClaimLabel}` : ''}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                        {p.cycleMedianLabel || <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>}
                                                        {p.timePerSpLabel && (
                                                            <div className="text-xs text-gray-400 dark:text-gray-500">{p.timePerSpLabel} / SP</div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {p.accuracyPct === null ? (
                                                            <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>
                                                        ) : (
                                                            <>
                                                                <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + p.accuracyPill}>
                                                                    {p.accuracyPct} %
                                                                </span>
                                                                <div className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                                    {p.accuracyHits}/{p.accuracyTotal}
                                                                    {p.medianDeviationLabel ? ` · ${p.medianDeviationLabel}` : ''}
                                                                </div>
                                                            </>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex flex-wrap gap-1">
                                                            {/* Nacharbeit zuerst: die einzige Verlaufszahl der
                                                                Spalte, alles andere ist Momentaufnahme. */}
                                                            {p.reworkTasks > 0 && (
                                                                <span
                                                                    className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                                                    title={strings.mRework}
                                                                >
                                                                    {p.reworkTasks}× ↻
                                                                </span>
                                                            )}
                                                            {p.reviewedCount > 0 && (
                                                                <span className={'rounded-full px-2 py-0.5 text-xs font-medium ' + p.firstPassPill}>
                                                                    {p.approved}/{p.reviewedCount} ✓
                                                                </span>
                                                            )}
                                                            {p.ciFailed > 0 && (
                                                                <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                                    {p.ciFailed} CI
                                                                </span>
                                                            )}
                                                            {p.openThreads > 0 && (
                                                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                                    {p.openThreads} 💬
                                                                </span>
                                                            )}
                                                            {p.exceptions > 0 && (
                                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                                    {p.exceptions} ⚠
                                                                </span>
                                                            )}
                                                            {/* Ohne PR-Sync ist die Spalte nicht „sauber",
                                                                sondern ungemessen — das muss sichtbar sein. */}
                                                            {p.prSynced === 0 && (
                                                                <span
                                                                    className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                                                                    title={strings.neverSynced}
                                                                >
                                                                    {strings.none} CI
                                                                </span>
                                                            )}
                                                            {p.reworkTasks === 0 && p.reviewedCount === 0 && !p.ciFailed && !p.openThreads && p.exceptions === 0 && p.prSynced > 0 && (
                                                                <span className="text-gray-300 dark:text-gray-600">{strings.none}</span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                                        <div>{p.files} {strings.mFiles}</div>
                                                        <div>+{p.additions} / −{p.deletions}</div>
                                                        <div>{p.commits} {strings.mCommits} · {p.tokensLabel}</div>
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300">
                                                        {p.reviewsGiven}
                                                    </td>
                                                </tr>
                                                {open && (
                                                    <tr className="border-b border-gray-50 last:border-0 dark:border-gray-700">
                                                        <td colSpan={8} className="p-0">
                                                            <PersonDetails person={p} strings={strings} />
                                                        </td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        {people.length === 0 && (
                            <p className="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{strings.noPeople}</p>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
