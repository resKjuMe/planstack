import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { interpolate, transChoice } from '../../summary/i18n.js';
import { relativeTime } from '../../stats/format.js';

// Stand-up-Ansicht: was ist am letzten Arbeitstag passiert. Die Auswertung kommt
// fertig vom Server (App\Support\DailyPresenter); hier wird nur dargestellt,
// nach Zielgruppe gefiltert und der Stand-up-Text erzeugt.

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';
const ROW = 'rounded-lg p-3 ring-1 ring-gray-100 dark:ring-gray-700/60';
const PILL = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap';
const TONE = {
    gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
};
const PR_TONE = { merged: 'emerald', opened: 'blue', closed: 'gray', ciFailed: 'red' };
const DECISION_TONE = { APPROVE: 'green', REQUEST_CHANGES: 'amber' };
const HEAT = [
    'bg-gray-100 dark:bg-gray-700/60',
    'bg-indigo-200 dark:bg-indigo-900',
    'bg-indigo-400 dark:bg-indigo-700',
    'bg-indigo-600 dark:bg-indigo-500',
];
const AUDIENCES = ['all', 'team', 'po', 'ux'];

const pill = (tone) => `${PILL} ${TONE[tone] ?? TONE.gray}`;

function time(iso, locale) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Berlin' });
}

function Tile({ label, value, unit, sub, danger = false }) {
    return (
        <div className={`${CARD} p-4 ${danger ? 'ring-red-200 dark:ring-red-900/60' : ''}`}>
            <div className={`text-xs font-medium ${danger ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'}`}>{label}</div>
            <div className={`mt-1 text-3xl font-bold ${danger ? 'text-red-800 dark:text-red-300' : 'text-gray-900 dark:text-gray-100'}`}>
                {value}
                {unit && <span className="ms-1.5 text-base font-medium text-gray-400 dark:text-gray-500">{unit}</span>}
            </div>
            {sub && <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{sub}</div>}
        </div>
    );
}

function PanelHead({ title, sub, count, tone = 'indigo', className = '' }) {
    return (
        <div className={className}>
            <div className="flex items-center gap-2">
                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                {count > 0 && <span className={pill(tone)}>{count}</span>}
            </div>
            {sub && <p className="text-xs text-gray-400 dark:text-gray-500">{sub}</p>}
        </div>
    );
}

function GroupHead({ dot, label, count, tone }) {
    return (
        <div className="flex items-center gap-2">
            <span className={`h-2 w-2 rounded-full ${dot}`} />
            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{label}</h3>
            <span className={pill(tone)}>{count}</span>
        </div>
    );
}

function TaskHead({ task, jiraBaseUrl, children }) {
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            {task.projectAlias && (
                <a
                    href={task.boardUrl}
                    title={task.projectName}
                    className="inline-flex items-center rounded bg-gray-800 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-white dark:bg-gray-700"
                >
                    {task.projectAlias}
                </a>
            )}
            <a href={task.url} className="font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                {task.name}
            </a>
            {task.jiraKey && (
                jiraBaseUrl ? (
                    <a href={`${jiraBaseUrl}/browse/${task.jiraKey}`} target="_blank" rel="noreferrer" className="font-mono text-xs text-gray-500 hover:underline dark:text-gray-400">
                        {task.jiraKey}
                    </a>
                ) : (
                    <span className="font-mono text-xs text-gray-500 dark:text-gray-400">{task.jiraKey}</span>
                )
            )}
            {children}
        </div>
    );
}

function Summary({ text }) {
    return text ? <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">{text}</p> : null;
}

function Meta({ children }) {
    return <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{children}</p>;
}

function Empty({ children }) {
    return <p className="rounded-lg bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">{children}</p>;
}

function TaskChanges({ rows, strings, locale, jiraBaseUrl }) {
    return rows.map((row) => (
        <div key={row.id} className={ROW}>
            <TaskHead task={row} jiraBaseUrl={jiraBaseUrl}>
                <span className="ms-auto inline-flex items-center gap-1.5">
                    {row.from && <span className={pill('gray')}>{row.from}</span>}
                    <svg className="h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                    {row.to && <span className={`${PILL} ${row.toBadge}`}>{row.to}</span>}
                </span>
            </TaskHead>
            <Summary text={row.summary} />
            <Meta>
                {interpolate(strings.at, { time: time(row.at, locale) })}
                {row.changes > 1 && ` · ${transChoice(strings.changes, row.changes)}`}
                {row.sp ? ` · ${row.sp} SP` : ''}
            </Meta>
        </div>
    ));
}

function PullRequests({ rows, strings, locale, scope }) {
    return rows.map((pr) => (
        <div key={pr.id} className={ROW}>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <a href={pr.detailUrl} className="font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">#{pr.number}</a>
                <span className={pill(PR_TONE[pr.event])}>{strings[`pr_${pr.event}`]}</span>
                <a href={pr.url} target="_blank" rel="noreferrer" className="min-w-0 text-sm text-gray-800 hover:underline dark:text-gray-200">{pr.title}</a>
            </div>
            <Meta>
                {[
                    pr.at && interpolate(strings.at, { time: time(pr.at, locale) }),
                    scope === 'team' && pr.authorName,
                    `+${pr.additions} / −${pr.deletions}`,
                    pr.approvals > 0 && transChoice(strings.approvals, pr.approvals),
                    pr.ciState === 'failed' && interpolate(strings.ci_failed, { checks: pr.ciFailedChecks.join(', ') }),
                    pr.unresolvedThreads > 0 && transChoice(strings.threads, pr.unresolvedThreads),
                ].filter(Boolean).join(' · ')}
            </Meta>
        </div>
    ));
}

function Reviews({ rows, strings, locale, scope }) {
    return rows.map((review) => (
        <div key={review.key} className={ROW}>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <a href={review.url} className="font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">#{review.number}</a>
                <span className="min-w-0 text-sm text-gray-800 dark:text-gray-200">{review.title}</span>
                <span className={`${pill(DECISION_TONE[review.decision])} ms-auto`}>
                    {strings[`decision_${review.decision}`] ?? strings.decision_none}
                </span>
            </div>
            <Meta>
                {[
                    interpolate(strings.at, { time: time(review.at, locale) }),
                    scope === 'team' && review.reviewerName,
                    review.source === 'github' && strings.source_github,
                    review.critical !== null && interpolate(strings.findings, review),
                    review.poLikelihood >= 50 && strings.po_likely,
                ].filter(Boolean).join(' · ')}
            </Meta>
        </div>
    ));
}

function Heatmap({ heatmap, strings }) {
    const { hours, rows } = heatmap;
    const level = (n) => HEAT[Math.min(n, 3)];

    return (
        <div className="overflow-x-auto">
            <div
                className="grid min-w-[520px] items-center gap-1"
                style={{ gridTemplateColumns: `8rem repeat(${hours.length}, minmax(1.5rem, 1fr))` }}
            >
                <span />
                {hours.map((hour) => (
                    <span key={hour} className="text-center text-[11px] text-gray-400 dark:text-gray-500">{hour}</span>
                ))}
                {rows.map((row) => (
                    <React.Fragment key={row.key}>
                        <span className="text-sm text-gray-700 dark:text-gray-300">{strings[`row_${row.key}`]}</span>
                        {row.cells.map((count, index) => (
                            <span
                                key={index}
                                title={`${hours[index]}:00 · ${count}`}
                                className={`h-5 rounded ${level(count)}`}
                            />
                        ))}
                    </React.Fragment>
                ))}
            </div>
            <div className="mt-3 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                {strings.less}
                {HEAT.map((cls) => <span key={cls} className={`h-3.5 w-3.5 rounded-sm ${cls}`} />)}
                {strings.more}
            </div>
        </div>
    );
}

function Blockers({ rows, strings, jiraBaseUrl }) {
    if (rows.length === 0) return <Empty>{strings.blockers_empty}</Empty>;

    return rows.map((row) => (
        <div
            key={row.id}
            className={`${ROW} ${row.kind === 'exception' ? 'bg-red-50 ring-red-200 dark:bg-red-900/20 dark:ring-red-900/60' : ''}`}
        >
            <TaskHead task={row} jiraBaseUrl={jiraBaseUrl}>
                <span className={row.kind === 'exception' ? `${PILL} ${row.statusBadge}` : pill('purple')}>
                    {row.kind === 'exception' ? row.statusLabel : strings.blocker_noReviewer}
                </span>
                {row.days !== null && (
                    <span className="ms-auto text-xs text-gray-500 dark:text-gray-400">
                        {interpolate(strings.since_days, { days: Math.max(1, Math.round(row.days)) })}
                    </span>
                )}
            </TaskHead>
            <Summary text={row.concern ?? row.summary} />
        </div>
    ));
}

function Waiting({ rows, strings, locale, jiraBaseUrl }) {
    if (rows.length === 0) return <Empty>{strings.waiting_empty}</Empty>;

    return rows.map((row) => (
        <div key={row.id} className={ROW}>
            <TaskHead task={row} jiraBaseUrl={jiraBaseUrl} />
            <Summary text={row.summary} />
            <Meta>
                {[row.authorName && interpolate(strings.by, { name: row.authorName })]
                    .filter(Boolean)
                    .join(' · ')}
                {row.since && <span className="text-amber-700 dark:text-amber-400"> · {relativeTime(row.since, locale)}</span>}
            </Meta>
        </div>
    ));
}

function ForPo({ rows, strings }) {
    if (rows.length === 0) return <Empty>{strings.po_empty}</Empty>;

    return rows.map((row) => (
        <div key={row.number} className={ROW}>
            <div className="flex flex-wrap items-center gap-2">
                <a href={row.url} target="_blank" rel="noreferrer" className="font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">#{row.number}</a>
                <span className={pill(PR_TONE[row.event])}>{strings[`pr_${row.event}`]}</span>
                {row.customerView?.visibility && <span className={pill('amber')}>{row.customerView.visibility}</span>}
            </div>
            <Summary text={row.customerView?.change ?? row.title} />
            {row.poDecisions.map((title) => (
                <div key={title} className="mt-2 rounded-md bg-amber-50 px-2.5 py-1.5 text-sm dark:bg-amber-900/20">
                    <span className="font-semibold text-amber-800 dark:text-amber-300">{strings.po_decision}: </span>
                    <span className="text-gray-800 dark:text-gray-200">{title}</span>
                </div>
            ))}
        </div>
    ));
}

function ForUx({ rows, strings }) {
    if (rows.length === 0) return <Empty>{strings.ux_empty}</Empty>;

    return rows.map((row) => (
        <div key={row.number} className={ROW}>
            <div className="flex flex-wrap items-center gap-2">
                <a href={row.url} target="_blank" rel="noreferrer" className="font-mono text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">#{row.number}</a>
                <span className={pill(PR_TONE[row.event])}>{strings[`pr_${row.event}`]}</span>
                <span className="min-w-0 text-sm text-gray-800 dark:text-gray-200">{row.title}</span>
            </div>
            <Meta>
                {[
                    row.uiFiles > 0 && transChoice(strings.ux_files, row.uiFiles),
                    row.textFiles > 0 && transChoice(strings.ux_texts, row.textFiles),
                ].filter(Boolean).join(' · ')}
            </Meta>
        </div>
    ));
}

function Today({ rows, strings, locale, scope, jiraBaseUrl }) {
    if (rows.length === 0) return <Empty>{strings.today_empty}</Empty>;

    return rows.map((row) => (
        <div key={row.id} className={`${ROW} ${row.isStale ? 'bg-gray-50 dark:bg-gray-900/40' : ''}`}>
            <TaskHead task={row} jiraBaseUrl={jiraBaseUrl}>
                {row.statusLabel && <span className={`${PILL} ${row.statusBadge}`}>{row.statusLabel}</span>}
                {row.sp ? <span className="ms-auto text-xs font-semibold text-gray-600 dark:text-gray-300">{row.sp} SP</span> : null}
            </TaskHead>
            <Summary text={row.summary} />
            {(row.isStale || row.progress || scope === 'team') && (
                <Meta>
                    {[
                        scope === 'team' && row.claimerName,
                        row.progress,
                    ].filter(Boolean).join(' · ')}
                    {row.isStale && (
                        <span className="text-amber-700 dark:text-amber-400">
                            {row.progress || scope === 'team' ? ' · ' : ''}
                            {interpolate(strings.stale, { time: relativeTime(row.updatedAt, locale)?.replace(/^vor |^in |ago$/g, '').trim() })}
                        </span>
                    )}
                </Meta>
            )}
        </div>
    ));
}

function Team({ rows, strings }) {
    if (rows.length === 0) return <Empty>{strings.team_empty}</Empty>;

    return (
        <ul className="space-y-3">
            {rows.map((member) => (
                <li key={member.id} className="flex items-start gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        {member.initials}
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{member.name}</p>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {member.active
                                ? [
                                      member.merged > 0 && interpolate(strings.team_merged, { count: member.merged }),
                                      member.opened > 0 && transChoice(strings.team_opened, member.opened),
                                      member.done > 0 && interpolate(strings.team_done, { count: member.done }),
                                      member.reviews > 0 && transChoice(strings.team_reviews, member.reviews),
                                      member.tasks > 0 && transChoice(strings.team_tasks, member.tasks),
                                  ].filter(Boolean).join(' · ') || transChoice(strings.changes, member.statusChanges)
                                : strings.team_quiet}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
}

// Gestern / Heute / Blocker als Fließtext zum Vorlesen oder für Slack.
function standupText(data, strings) {
    const task = (row) => `${row.projectAlias} ${row.name}`;
    const yesterday = [
        ...data.taskChanges.slice(0, 8).map((row) => interpolate(strings.standup_status, { task: task(row), status: row.to ?? '' })),
        ...data.pullRequests
            .filter((pr) => pr.event !== 'ciFailed')
            .map((pr) => interpolate(strings.standup_pr, { number: pr.number, event: strings[`pr_${pr.event}`], title: pr.title })),
        ...(data.reviews.length ? [transChoice(strings.standup_reviews, data.reviews.length)] : []),
    ];
    const today = data.today.slice(0, 6).map(task);
    const blockers = data.blockers.map((row) => `${task(row)}: ${row.concern ?? strings.blocker_noReviewer}`);
    const section = (title, items) => `${title}\n${items.length ? items.map((item) => `- ${item}`).join('\n') : `- ${strings.standup_none}`}`;

    return [
        section(strings.standup_yesterday, yesterday),
        section(strings.standup_today, today),
        section(strings.standup_blockers, blockers),
    ].join('\n\n');
}

function useCopy() {
    const [copied, setCopied] = useState(false);
    const copy = (text) => {
        navigator.clipboard?.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return [copied, copy];
}

export default function Daily({ day, scope, data, urls, strings, jiraBaseUrl }) {
    const locale = (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de';
    const [audience, setAudience] = useState('all');
    const [copied, copy] = useCopy();
    const text = useMemo(() => (data.hasProjects ? standupText(data, strings) : ''), [data, strings]);
    const show = (key) => audience === 'all' || audience === key;

    const segment = (active) =>
        'rounded-md px-3.5 py-1.5 text-sm font-medium transition ' +
        (active
            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200');
    const chip = (active) =>
        'rounded-full px-3 py-1 text-xs font-medium transition ' +
        (active
            ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
            : 'bg-white text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-600 dark:hover:bg-gray-700/50');
    const navButton = 'px-2.5 py-1.5 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400';

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">{strings.title}</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {interpolate(strings.subtitle, { day: '' })}
                                <span className="font-semibold text-gray-800 dark:text-gray-200">{day.label}</span>
                                {' · '}
                                {scope === 'team' ? strings.subtitle_team : strings.subtitle_me}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="inline-flex items-center rounded-md bg-white ring-1 ring-gray-300 dark:bg-gray-800 dark:ring-gray-600">
                                <a href={urls.prev} className={navButton} aria-label={strings.prev} title={strings.prev}>‹</a>
                                <span className="px-1 text-sm font-medium text-gray-800 dark:text-gray-200">{day.short}</span>
                                {urls.next ? (
                                    <a href={urls.next} className={navButton} aria-label={strings.next} title={strings.next}>›</a>
                                ) : (
                                    <span className="px-2.5 py-1.5 text-gray-300 dark:text-gray-600" aria-hidden="true">›</span>
                                )}
                            </div>
                            <div role="group" aria-label={strings.scope_label} className="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-700">
                                <a href={urls.me} aria-current={scope === 'me' ? 'true' : undefined} className={segment(scope === 'me')}>{strings.scope_me}</a>
                                <a href={urls.team} aria-current={scope === 'team' ? 'true' : undefined} className={segment(scope === 'team')}>{strings.scope_team}</a>
                            </div>
                            {data.hasProjects && (
                                <button
                                    type="button"
                                    onClick={() => copy(text)}
                                    className="inline-flex items-center gap-2 whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                                        <rect x="9" y="9" width="12" height="12" rx="2" />
                                        <path d="M5 15V5a2 2 0 012-2h10" />
                                    </svg>
                                    {copied ? strings.copied : strings.copy_standup}
                                </button>
                            )}
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {!data.hasProjects ? (
                        <div className={`${CARD} px-6 py-10 text-center`}>
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{strings.empty_title}</h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.empty_text}</p>
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="me-1 text-xs text-gray-500 dark:text-gray-400">{strings.audience}</span>
                                {AUDIENCES.map((key) => (
                                    <button key={key} type="button" onClick={() => setAudience(key)} aria-pressed={audience === key} className={chip(audience === key)}>
                                        {strings[`audience_${key}`]}
                                    </button>
                                ))}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Tile
                                    label={strings.kpi_done}
                                    value={data.kpis.doneTasks}
                                    unit={strings.kpi_done_unit}
                                    sub={interpolate(strings.kpi_done_sub, { sp: data.kpis.doneSp, review: data.kpis.toReview })}
                                />
                                <Tile
                                    label={strings.kpi_prs}
                                    value={data.kpis.prsMerged}
                                    unit={strings.kpi_prs_unit}
                                    sub={interpolate(strings.kpi_prs_sub, {
                                        open: data.kpis.prsOpen,
                                        add: data.kpis.additions.toLocaleString(locale),
                                        del: data.kpis.deletions.toLocaleString(locale),
                                    })}
                                />
                                <Tile
                                    label={strings.kpi_reviews}
                                    value={data.kpis.reviewsGiven}
                                    unit={strings.kpi_reviews_unit}
                                    sub={interpolate(strings.kpi_reviews_sub, { received: data.kpis.reviewsReceived, waiting: data.kpis.waiting })}
                                />
                                <Tile
                                    label={strings.kpi_blockers}
                                    value={data.kpis.blockers}
                                    unit={strings.kpi_blockers_unit}
                                    danger={data.kpis.blockers > 0}
                                    sub={
                                        data.kpis.blockers > 0
                                            ? interpolate(strings.kpi_blockers_sub, { days: Math.max(1, Math.round(data.kpis.oldestBlockerDays ?? 0)) })
                                            : strings.kpi_blockers_none
                                    }
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                <div className="space-y-6 lg:col-span-2">
                                    <section className={`${CARD} space-y-5 p-5`}>
                                        <PanelHead title={strings.activity_title} sub={interpolate(strings.activity_sub, { day: day.label })} />
                                        {data.taskChanges.length + data.pullRequests.length + data.reviews.length === 0 && (
                                            <Empty>{strings.activity_empty}</Empty>
                                        )}
                                        {data.taskChanges.length > 0 && (
                                            <div className="space-y-2">
                                                <GroupHead dot="bg-indigo-500" label={strings.group_tasks} count={data.taskChanges.length} tone="indigo" />
                                                <TaskChanges rows={data.taskChanges} strings={strings} locale={locale} jiraBaseUrl={jiraBaseUrl} />
                                            </div>
                                        )}
                                        {data.pullRequests.length > 0 && (
                                            <div className="space-y-2">
                                                <GroupHead dot="bg-emerald-500" label={strings.group_prs} count={data.pullRequests.length} tone="emerald" />
                                                <PullRequests rows={data.pullRequests} strings={strings} locale={locale} scope={scope} />
                                            </div>
                                        )}
                                        {data.reviews.length > 0 && (
                                            <div className="space-y-2">
                                                <GroupHead dot="bg-purple-500" label={strings.group_reviews} count={data.reviews.length} tone="purple" />
                                                <Reviews rows={data.reviews} strings={strings} locale={locale} scope={scope} />
                                            </div>
                                        )}
                                    </section>

                                    <section className={`${CARD} space-y-4 p-5`}>
                                        <PanelHead title={strings.heatmap_title} sub={strings.heatmap_sub} />
                                        <Heatmap heatmap={data.heatmap} strings={strings} />
                                    </section>
                                </div>

                                <aside className="space-y-6">
                                    {show('team') && (
                                        <>
                                            <section className={`${CARD} space-y-3 p-5 ${data.blockers.length ? 'ring-red-200 dark:ring-red-900/60' : ''}`}>
                                                <PanelHead title={strings.blockers_title} sub={strings.blockers_sub} count={data.blockers.length} tone="red" />
                                                <Blockers rows={data.blockers} strings={strings} jiraBaseUrl={jiraBaseUrl} />
                                            </section>
                                            <section className={`${CARD} space-y-3 p-5`}>
                                                <PanelHead
                                                    title={scope === 'team' ? strings.waiting_title_team : strings.waiting_title}
                                                    sub={strings.waiting_sub}
                                                    count={data.waiting.length}
                                                    tone="amber"
                                                />
                                                <Waiting rows={data.waiting} strings={strings} locale={locale} jiraBaseUrl={jiraBaseUrl} />
                                            </section>
                                        </>
                                    )}
                                    {show('po') && (
                                        <section className={`${CARD} space-y-3 p-5`}>
                                            <PanelHead title={strings.po_title} sub={strings.po_sub} count={data.forPo.length} />
                                            <ForPo rows={data.forPo} strings={strings} />
                                        </section>
                                    )}
                                    {show('ux') && (
                                        <section className={`${CARD} space-y-3 p-5`}>
                                            <PanelHead title={strings.ux_title} sub={strings.ux_sub} count={data.forUx.length} tone="purple" />
                                            <ForUx rows={data.forUx} strings={strings} />
                                        </section>
                                    )}
                                    {show('team') && (
                                        <section className={`${CARD} space-y-3 p-5`}>
                                            <PanelHead title={strings.today_title} sub={strings.today_sub} count={data.today.length} tone="blue" />
                                            <Today rows={data.today} strings={strings} locale={locale} scope={scope} jiraBaseUrl={jiraBaseUrl} />
                                        </section>
                                    )}
                                </aside>
                            </div>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                <section className={`${CARD} space-y-4 p-5 lg:col-span-2`}>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <PanelHead title={strings.standup_title} sub={strings.standup_sub} />
                                        <button
                                            type="button"
                                            onClick={() => copy(text)}
                                            className="rounded-md bg-white px-3.5 py-1.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700"
                                        >
                                            {copied ? strings.copied : strings.copy}
                                        </button>
                                    </div>
                                    <pre className="whitespace-pre-wrap rounded-lg bg-gray-50 p-4 font-sans text-sm leading-relaxed text-gray-800 ring-1 ring-gray-200 dark:bg-gray-900/40 dark:text-gray-200 dark:ring-gray-700">
                                        {text}
                                    </pre>
                                </section>

                                <section className={`${CARD} space-y-3 p-5`}>
                                    <PanelHead title={strings.team_title} sub={interpolate(strings.team_sub, { day: day.short })} />
                                    <Team rows={data.team} strings={strings} />
                                </section>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

Daily.layout = AppShell;
