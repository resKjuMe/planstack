import React, { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { interpolate } from '../../summary/i18n.js';
import { formatDuration, relativeTime } from '../../stats/format.js';

// Startseite (Ziel des Logos): die Ich-Sicht über ALLE sichtbaren Projekte. Kern
// ist die projektübergreifende Fassung des Board-Filters „Bei mir" — eigene
// Arbeitsschritte, Reviews (eigene oder freie) und eigene Ausnahmen.
//
// Die Auswertung kommt fertig vom Server (App\Support\DashboardPresenter); der
// geteilte React-Store hält immer genau EIN Projekt, projektübergreifend gibt es
// nichts zu teilen. Task-Änderungen (Socket) ziehen nur die `data`-Prop nach,
// damit das Dashboard nicht veraltet neben dem Board steht.

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';

// Akzent je Gruppe der „Bei mir"-Liste: eigene Arbeit, Review, Ausnahme.
const GROUP = {
    work: {
        dot: 'bg-indigo-500',
        count: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    },
    review: {
        dot: 'bg-purple-500',
        count: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    },
    blocked: {
        dot: 'bg-red-500',
        count: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    },
};

const PILL_BASE = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium';
const PILL_GRAY = 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
const PILL_RED = 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
const PILL_AMBER = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
const PILL_GREEN = 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';

function Tile({ label, value, unit, sub }) {
    return (
        <div className={`${CARD} p-4`}>
            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{label}</div>
            <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">
                {value}
                {unit && <span className="ms-1 text-lg font-semibold text-gray-400 dark:text-gray-500">{unit}</span>}
            </div>
            {sub && <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{sub}</div>}
        </div>
    );
}

// Kopfzeile eines Panels: Titel + erklärender Untertitel, überall gleich.
function PanelHead({ title, sub, right }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                {sub && <p className="text-xs text-gray-400 dark:text-gray-500">{sub}</p>}
            </div>
            {right}
        </div>
    );
}

// Eine Zeile der „Bei mir"-Liste. Die ganze Karte führt zum Task (wie auf der
// Projektliste: Klicks auf echte Links darin bleiben unberührt), damit der
// Sprung in die Arbeit ein Klick ist.
function TaskRow({ item, strings, locale }) {
    const open = (e) => {
        if (item.url && !e.target.closest('a')) router.visit(item.url);
    };

    return (
        <div
            onClick={open}
            className="cursor-pointer rounded-lg p-3 ring-1 ring-gray-100 transition hover:bg-gray-50 hover:ring-gray-200 dark:ring-gray-700/60 dark:hover:bg-gray-700/30 dark:hover:ring-gray-600"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <a
                            href={item.boardUrl}
                            title={item.projectName}
                            className="inline-flex items-center rounded bg-gray-800 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-white dark:bg-gray-700"
                        >
                            {item.projectAlias}
                        </a>
                        <a href={item.url} className="truncate font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                            {item.name}
                        </a>
                        {item.statusLabel && (
                            <span className={`${PILL_BASE} ${item.statusBadge}`}>{item.statusLabel}</span>
                        )}
                        {item.isFreeReview && <span className={`${PILL_BASE} ${PILL_GREEN}`}>{strings.freeReview}</span>}
                        {item.bucket === 'review' && !item.isFreeReview && (
                            <span className={`${PILL_BASE} ${PILL_GRAY}`}>{strings.assignedToMe}</span>
                        )}
                        {/* Ein freies Review der EIGENEN Arbeit ist kein Auftrag an
                            mich — der Hinweis verhindert, dass man sich selbst
                            reviewt, ohne dass die Zeile aus der Board-Logik fällt. */}
                        {item.bucket === 'review' && item.isMyClaim && (
                            <span className={`${PILL_BASE} ${PILL_AMBER}`}>{strings.reviewOfOwnWork}</span>
                        )}
                    </div>
                    {item.summary && (
                        <p className="mt-1 truncate text-sm text-gray-500 dark:text-gray-400" title={item.summary}>
                            {item.summary}
                        </p>
                    )}
                    {item.concern && (
                        <p className="mt-1 truncate text-sm text-red-600 dark:text-red-400" title={item.concern}>
                            „{item.concern}"
                        </p>
                    )}
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                        {/* Nur die relative Angabe („vor 5 Tagen"), kein „seit"
                            davor: Intl.RelativeTimeFormat liefert bereits eine
                            vollständige Wendung, ein Präfix ergäbe „seit vor 5
                            Tagen". Was der Zeitpunkt bedeutet, sagt der Tooltip. */}
                        <span title={strings.sinceHint}>{relativeTime(item.sinceAt, locale) || strings.none}</span>
                        {item.phase && <span className="truncate">{item.phase}</span>}
                        {item.bucket !== 'work' && item.claimerName && !item.isMyClaim && (
                            <span>{interpolate(strings.claimedFrom, { name: item.claimerName })}</span>
                        )}
                        {item.prUrl && (
                            <a href={item.prUrl} target="_blank" rel="noopener" data-native className="font-mono hover:text-indigo-600 hover:underline dark:hover:text-indigo-400">
                                #{item.prNumber}
                            </a>
                        )}
                        {item.ciFailed > 0 && <span className="font-medium text-red-600 dark:text-red-400">{strings.ciFailed}</span>}
                        {item.openThreads > 0 && (
                            <span>{interpolate(strings.openThreads, { count: item.openThreads })}</span>
                        )}
                        {item.reviewRecommendation === 'REQUEST_CHANGES' && (
                            <span className="font-medium text-amber-600 dark:text-amber-400">{strings.changesRequested}</span>
                        )}
                    </div>
                </div>
                {item.sp !== null && item.sp !== undefined && (
                    <span className="shrink-0 whitespace-nowrap text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {item.sp} SP
                    </span>
                )}
            </div>
        </div>
    );
}

function Group({ bucket, label, hint, strings, locale }) {
    const accent = GROUP[bucket.key];

    return (
        <section>
            <div className="flex items-center gap-2">
                <span className={`h-2 w-2 shrink-0 rounded-full ${accent.dot}`} />
                <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200" title={hint}>
                    {label}
                </h3>
                <span className={`${PILL_BASE} ${accent.count}`}>{bucket.count}</span>
                {bucket.sp > 0 && <span className="text-xs text-gray-400 dark:text-gray-500">{bucket.sp} SP</span>}
            </div>

            {bucket.items.length === 0 ? (
                <p className="mt-2 text-sm text-gray-400 dark:text-gray-500">{strings.groupEmpty}</p>
            ) : (
                <div className="mt-2 space-y-2">
                    {bucket.items.map((item) => (
                        <TaskRow key={`${item.projectAlias}-${item.id}`} item={item} strings={strings} locale={locale} />
                    ))}
                    {bucket.count > bucket.items.length && (
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            {interpolate(strings.moreItems, { count: bucket.count - bucket.items.length })}
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}

function Pickable({ rows, strings }) {
    if (rows.length === 0) {
        return <p className="mt-4 text-sm text-gray-400 dark:text-gray-500">{strings.pickableEmpty}</p>;
    }

    return (
        <div className="mt-3 space-y-2">
            {rows.map((task) => (
                <div key={`${task.projectAlias}-${task.id}`} className="rounded-lg p-2.5 ring-1 ring-gray-100 dark:ring-gray-700/60">
                    <div className="flex items-center gap-2">
                        <span className="shrink-0 rounded bg-gray-800 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-white dark:bg-gray-700">
                            {task.projectAlias}
                        </span>
                        <a href={task.url} className="truncate font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                            {task.name}
                        </a>
                        {task.sp !== null && task.sp !== undefined && (
                            <span className="ms-auto shrink-0 text-xs font-semibold text-gray-600 dark:text-gray-400">{task.sp} SP</span>
                        )}
                    </div>
                    {task.summary && (
                        <p className="mt-1 truncate text-xs text-gray-500 dark:text-gray-400" title={task.summary}>
                            {task.summary}
                        </p>
                    )}
                    {task.dependents > 0 && (
                        <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            {interpolate(strings.dependents, { count: task.dependents })}
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
}

function Projects({ rows, strings }) {
    if (rows.length === 0) return null;

    return (
        <div className="mt-3 space-y-3">
            {rows.map((project) => (
                <div key={project.alias}>
                    <div className="flex items-center justify-between gap-2 text-sm">
                        <a href={project.url} className="min-w-0 truncate font-medium text-gray-800 hover:text-indigo-700 hover:underline dark:text-gray-200 dark:hover:text-indigo-400">
                            <span className="font-mono text-xs text-gray-400 dark:text-gray-500">{project.alias}</span>{' '}
                            {project.name}
                        </a>
                        <span className="shrink-0 text-xs text-gray-500 dark:text-gray-400">{project.percent} %</span>
                    </div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                        <div className="h-full bg-indigo-500 dark:bg-indigo-400" style={{ width: `${project.percent}%` }} />
                    </div>
                    <div className="mt-1 flex flex-wrap gap-x-3 text-xs text-gray-400 dark:text-gray-500">
                        {project.myActionable > 0 && (
                            <span className="font-medium text-indigo-600 dark:text-indigo-400">
                                {interpolate(strings.projectMine, { count: project.myActionable })}
                            </span>
                        )}
                        <span>{interpolate(strings.projectOpen, { count: project.myOpen })}</span>
                        <span>
                            {project.doneCount} / {project.tasksCount} {strings.tasks}
                        </span>
                    </div>
                </div>
            ))}
        </div>
    );
}

function Activity({ rows, strings, locale }) {
    if (rows.length === 0) {
        return <p className="mt-4 text-sm text-gray-400 dark:text-gray-500">{strings.activityEmpty}</p>;
    }

    return (
        <ul className="mt-3 space-y-2">
            {rows.map((entry) => (
                <li key={entry.id} className="flex items-baseline justify-between gap-2 text-xs">
                    <span className="min-w-0">
                        <span className={`${PILL_BASE} ${PILL_GRAY} me-1.5`}>{entry.label}</span>
                        <a href={entry.url} className="font-mono font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                            {entry.taskName}
                        </a>
                        <span className="ms-1.5 font-mono text-gray-400 dark:text-gray-500">{entry.projectAlias}</span>
                        <span className="ms-1.5 text-gray-500 dark:text-gray-400">
                            {entry.isMe ? strings.activityYou : entry.actorName}
                        </span>
                    </span>
                    <span className="shrink-0 whitespace-nowrap text-gray-400 dark:text-gray-500">
                        {relativeTime(entry.at, locale)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

const headerLink =
    'text-sm font-medium text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400';

export default function Dashboard({ person, data, urls, strings }) {
    const locale = (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de';

    // Task-Änderungen ziehen NUR die Auswertung nach (Labels und Shell bleiben).
    // Entkoppelt über eine kurze Wartezeit: auf einem aktiven Board kommen die
    // Events in Schüben, und die Aggregation ist projektübergreifend.
    useEffect(() => {
        let timer = null;
        const onEntity = (e) => {
            if (e.detail?.entity !== 'task') return;
            clearTimeout(timer);
            timer = setTimeout(() => router.reload({ only: ['data'] }), 2000);
        };
        window.addEventListener('planstack:entity-changed', onEntity);
        return () => {
            clearTimeout(timer);
            window.removeEventListener('planstack:entity-changed', onEntity);
        };
    }, []);

    const groupLabels = {
        work: { label: strings.groupWork, hint: strings.groupWorkHint },
        review: { label: strings.groupReview, hint: strings.groupReviewHint },
        blocked: { label: strings.groupBlocked, hint: strings.groupBlockedHint },
    };

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                                {interpolate(strings.greeting, { name: person.firstName })}
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">{strings.subtitle}</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <a href={urls.projects} className={headerLink}>{strings.linkProjects}</a>
                            <a href={urls.statistics} className={headerLink}>{strings.linkStatistics}</a>
                            <a
                                href={urls.newProject}
                                className="inline-flex items-center whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                + {strings.linkNewProject}
                            </a>
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {!data.hasProjects && (
                        <div className={`${CARD} px-6 py-10 text-center`}>
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{strings.emptyTitle}</h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.emptyText}</p>
                            <a
                                href={urls.projects}
                                className="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                {strings.linkProjects}
                            </a>
                        </div>
                    )}

                    {data.hasProjects && (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Tile
                                    label={strings.kpiActionable}
                                    value={data.kpis.actionable}
                                    sub={interpolate(strings.kpiActionableSub, {
                                        sp: data.kpis.actionableSp,
                                        tasks: data.kpis.actionable,
                                    })}
                                />
                                <Tile
                                    label={strings.kpiReviews}
                                    value={data.kpis.reviewsMine + data.kpis.reviewsFree}
                                    sub={interpolate(strings.kpiReviewsSub, {
                                        mine: data.kpis.reviewsMine,
                                        free: data.kpis.reviewsFree,
                                    })}
                                />
                                <Tile
                                    label={strings.kpiWeek}
                                    value={data.kpis.deliveredSp}
                                    unit="SP"
                                    sub={interpolate(strings.kpiWeekSub, {
                                        tasks: data.kpis.deliveredTasks,
                                        reviews: data.kpis.reviewsGiven,
                                    })}
                                />
                                <Tile
                                    label={strings.kpiOldest}
                                    value={formatDuration(data.kpis.oldestDays, strings) || strings.none}
                                    sub={strings.kpiOldestSub}
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                {/* „Bei mir" — der Kern der Seite. self-start: die
                                    Karte soll nicht auf die Höhe der (meist
                                    längeren) Randspalte gestreckt werden. */}
                                <div className={`${CARD} self-start p-5 lg:col-span-2`}>
                                    <PanelHead title={strings.myWorkTitle} sub={strings.myWorkSub} />

                                    {data.kpis.actionable === 0 ? (
                                        <div className="mt-6 rounded-lg bg-gray-50 px-6 py-8 text-center dark:bg-gray-900/40">
                                            <p className="font-semibold text-gray-900 dark:text-gray-100">{strings.allClearTitle}</p>
                                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.allClearText}</p>
                                        </div>
                                    ) : (
                                        <div className="mt-4 space-y-6">
                                            {data.buckets
                                                .filter((bucket) => bucket.count > 0)
                                                .map((bucket) => (
                                                    <Group
                                                        key={bucket.key}
                                                        bucket={bucket}
                                                        label={groupLabels[bucket.key].label}
                                                        hint={groupLabels[bucket.key].hint}
                                                        strings={strings}
                                                        locale={locale}
                                                    />
                                                ))}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-6">
                                    <div className={`${CARD} p-5`}>
                                        <PanelHead title={strings.pickableTitle} sub={strings.pickableSub} />
                                        <Pickable rows={data.pickable} strings={strings} />
                                    </div>

                                    <div className={`${CARD} p-5`}>
                                        <PanelHead title={strings.projectsTitle} sub={strings.projectsSub} />
                                        <Projects rows={data.projects} strings={strings} />
                                    </div>

                                    <div className={`${CARD} p-5`}>
                                        <PanelHead title={strings.activityTitle} sub={strings.activitySub} />
                                        <Activity rows={data.activity} strings={strings} locale={locale} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
Dashboard.layout = AppShell;
