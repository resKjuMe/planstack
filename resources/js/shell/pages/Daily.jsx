import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PrAuthors from '../components/PrAuthors.jsx';
import { interpolate, transChoice } from '../../summary/i18n.js';
import { relativeTime } from '../../stats/format.js';

// Stand-up-Ansicht: je Person eine kompakte Karte (Gestern / Heute / Blocker),
// daneben ein Panel mit Tabs für alles, was nur bei Bedarf gebraucht wird
// (Blocker, Review & Test, PO, UX, PRs, Verlauf). Listen zeigen wenige Zeilen und
// klappen auf Wunsch auf. Die Auswertung kommt fertig vom Server
// (App\Support\DailyPresenter).

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';
const PILL = 'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap';
const TONE = {
    gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
};
const CATEGORY_TONE = { new: 'gray', indeterminate: 'blue', done: 'green' };
const PR_TONE = { merged: 'emerald', opened: 'blue', closed: 'gray', ciFailed: 'red' };
const DECISION_TONE = { APPROVE: 'green', REQUEST_CHANGES: 'amber' };
const HEAT = ['bg-gray-100 dark:bg-gray-700/60', 'bg-indigo-200 dark:bg-indigo-900', 'bg-indigo-400 dark:bg-indigo-700', 'bg-indigo-600 dark:bg-indigo-500'];
const PER_SECTION = { me: 10, team: 3 };
const PER_TAB = 5;

const pill = (tone) => `${PILL} ${TONE[tone] ?? TONE.gray}`;
const initials = (name) =>
    String(name ?? '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

function time(iso, locale) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Berlin' });
}

// Ein Ticket als einzeilige Zeile: Key · Titel · Status · PR-Ersteller.
function TicketLine({ ticket, status, tone }) {
    return (
        <div className="flex min-w-0 items-center gap-2 text-sm">
            <a href={ticket.url} target="_blank" rel="noreferrer" className="shrink-0 font-mono text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                {ticket.key}
            </a>
            <span className="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200" title={ticket.summary}>{ticket.summary}</span>
            {status && <span className={pill(tone)}>{status}</span>}
            <PrAuthors prs={ticket.prs} compact limit={2} className="shrink-0" />
        </div>
    );
}

// PR- bzw. Review-Zeile, aber mit dem Ticket statt des PR-Titels.
function PrLine({ row, status, tone }) {
    const ticket = row.tickets?.[0];

    return (
        <div className="flex min-w-0 items-center gap-2 text-sm">
            {ticket ? (
                <a href={ticket.url} target="_blank" rel="noreferrer" className="shrink-0 font-mono text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                    {ticket.key}
                </a>
            ) : (
                <a href={row.pr?.url ?? row.url} className="shrink-0 font-mono text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-400">#{row.number}</a>
            )}
            <span className="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200" title={ticket?.summary ?? row.title}>{ticket?.summary ?? row.title}</span>
            {status && <span className={pill(tone)}>{status}</span>}
            {row.pr && <PrAuthors prs={[row.pr]} compact className="shrink-0" />}
        </div>
    );
}

function More({ count, open, onClick, strings }) {
    if (count <= 0) return null;
    return (
        <button type="button" onClick={onClick} className="self-start text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
            {open ? strings.show_less : interpolate(strings.show_more, { count })}
        </button>
    );
}

function Section({ label, rows, render, strings, limit, empty = null }) {
    const [open, setOpen] = useState(false);
    if (!rows.length && !empty) return null;
    const shown = open ? rows : rows.slice(0, limit);

    return (
        <div className="flex flex-col gap-1">
            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</span>
            {rows.length ? shown.map(render) : <span className="text-xs text-gray-400 dark:text-gray-500">{empty}</span>}
            <More count={rows.length - limit} open={open} onClick={() => setOpen((v) => !v)} strings={strings} />
        </div>
    );
}

// Karte einer Person: Gestern / Heute / Blocker.
function PersonCard({ person, strings, limit }) {
    return (
        <article className={`${CARD} flex flex-col gap-3 p-4`}>
            <div className="flex items-center gap-2.5">
                {person.avatar ? (
                    <img src={person.avatar} alt="" className="h-8 w-8 rounded-full" />
                ) : (
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        {initials(person.name)}
                    </span>
                )}
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{person.name}</p>
                    <p className="truncate text-xs text-gray-500 dark:text-gray-400">{person.stats}</p>
                </div>
            </div>
            <Section
                label={strings.standup_yesterday}
                rows={person.yesterday}
                strings={strings}
                limit={limit}
                empty={person.activity.length ? null : strings.standup_none}
                render={(row) => <TicketLine key={row.key} ticket={row} status={row.to} tone={CATEGORY_TONE[row.toCategory]} />}
            />
            <Section
                label={strings.section_prs}
                rows={person.activity}
                strings={strings}
                limit={limit}
                render={(row) =>
                    row.kind === 'pr' ? (
                        <PrLine key={`pr-${row.number}-${row.event}`} row={row} status={strings[`pr_${row.event}`]} tone={PR_TONE[row.event]} />
                    ) : (
                        <PrLine key={`review-${row.prId ?? row.number}`} row={row} status={`${strings.review_short}: ${strings[`decision_${row.decision}`] ?? strings.decision_none}`} tone={DECISION_TONE[row.decision]} />
                    )
                }
            />
            <Section
                label={strings.standup_today}
                rows={person.today}
                strings={strings}
                limit={limit}
                render={(row) => <TicketLine key={row.key} ticket={row} status={row.status} tone={row.isStale ? 'amber' : CATEGORY_TONE[row.category]} />}
            />
            {person.blockers.length > 0 && (
                <div className="flex flex-col gap-1 rounded-md bg-red-50 p-2 dark:bg-red-900/20">
                    {person.blockers.map((row) => (
                        <TicketLine key={row.key} ticket={row} status={row.flagged ? strings.flagged : row.status} tone="red" />
                    ))}
                </div>
            )}
        </article>
    );
}

// Personen aus den Ticket-Listen: im Team nach Zuständigem gruppiert, sonst eine
// Karte für mich. Blocker zuerst, sonst nach letzter Aktivität.
function people(data, scope, me, strings) {
    const byName = new Map();
    const get = (name) => {
        const key = scope === 'team' ? name || strings.unassigned : me;
        if (!byName.has(key)) byName.set(key, { name: key, yesterday: [], today: [], blockers: [], activity: [], last: '' });
        return byName.get(key);
    };
    data.issueChanges.forEach((row) => {
        const p = get(row.assigneeName);
        p.yesterday.push(row);
        if (row.at > p.last) p.last = row.at;
    });
    data.today.forEach((row) => get(row.assigneeName).today.push(row));
    // PRs und Reviews des Tags; PRs nur, wenn ihr Ticket nicht schon unter „Gestern“ steht.
    data.pullRequests.forEach((pr) => {
        const p = get(pr.authorName);
        const known = pr.tickets?.length > 0 && pr.tickets.every((t) => p.yesterday.some((row) => row.key === t.key));
        if (!known || pr.event === 'ciFailed') p.activity.push({ ...pr, kind: 'pr' });
    });
    data.reviews.forEach((review) => get(review.reviewerName).activity.push({ ...review, kind: 'review' }));
    data.blockers.forEach((row) => get(row.assigneeName).blockers.push(row));

    const team = new Map(data.team.map((m) => [m.name, m]));

    return [...byName.values()]
        .map((p) => {
            const done = p.yesterday.filter((r) => r.isDone).length;
            const merged = data.pullRequests.filter((pr) => pr.event === 'merged' && (scope !== 'team' || pr.authorName === p.name)).length;
            const reviews = scope === 'team' ? team.get(p.name)?.reviews ?? 0 : data.kpis.reviewsGiven;
            const avatar = [...p.yesterday, ...p.today, ...p.blockers].flatMap((r) => r.prs ?? []).find((pr) => pr.author === p.name && pr.avatar)?.avatar;

            return {
                ...p,
                avatar,
                stats: [
                    transChoice(strings.person_moved, p.yesterday.length),
                    done > 0 && interpolate(strings.person_done, { count: done }),
                    merged > 0 && interpolate(strings.person_merged, { count: merged }),
                    reviews > 0 && transChoice(strings.person_reviews, reviews),
                ].filter(Boolean).join(' · '),
            };
        })
        .filter((p) => p.yesterday.length || p.today.length || p.blockers.length || p.activity.length)
        .sort((a, b) => (b.blockers.length > 0) - (a.blockers.length > 0) || (b.last > a.last ? 1 : -1));
}

function TabList({ rows, render, empty, strings }) {
    const [open, setOpen] = useState(false);
    if (!rows.length) return <p className="py-6 text-center text-sm text-gray-400 dark:text-gray-500">{empty}</p>;
    const shown = open ? rows : rows.slice(0, PER_TAB);

    return (
        <div className="flex flex-col">
            {shown.map((row, index) => (
                <div key={index} className="border-b border-gray-100 py-2 last:border-0 dark:border-gray-700/60">{render(row)}</div>
            ))}
            <div className="pt-2">
                <More count={rows.length - PER_TAB} open={open} onClick={() => setOpen((v) => !v)} strings={strings} />
            </div>
        </div>
    );
}

function Sub({ children }) {
    return <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{children}</p>;
}

function Heatmap({ heatmap, strings }) {
    const { hours, rows } = heatmap;

    return (
        <div className="grid items-center gap-1 py-2" style={{ gridTemplateColumns: `6.5rem repeat(${hours.length}, minmax(0, 1fr))` }}>
            <span />
            {hours.map((hour) => <span key={hour} className="text-center text-[10px] text-gray-400 dark:text-gray-500">{hour}</span>)}
            {rows.map((row) => (
                <React.Fragment key={row.key}>
                    <span className="truncate text-xs text-gray-700 dark:text-gray-300">{strings[`row_${row.key}`]}</span>
                    {row.cells.map((count, index) => (
                        <span key={index} title={`${hours[index]}:00 · ${count}`} className={`h-4 rounded-sm ${HEAT[Math.min(count, 3)]}`} />
                    ))}
                </React.Fragment>
            ))}
        </div>
    );
}

function SidePanel({ data, strings, locale }) {
    const tabs = [
        { key: 'blockers', count: data.blockers.length, danger: true },
        { key: 'waiting', count: data.waiting.length },
        { key: 'po', count: data.forPo.length },
        { key: 'ux', count: data.forUx.length },
        { key: 'prs', count: data.pullRequests.length + data.reviews.length },
        { key: 'timeline', count: null },
    ];
    const [active, setActive] = useState(tabs.find((tab) => tab.count > 0)?.key ?? 'timeline');

    return (
        <aside className={`${CARD} self-start px-4 pb-4`}>
            <div role="tablist" className="-mx-1 flex flex-wrap border-b border-gray-200 dark:border-gray-700">
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        role="tab"
                        aria-selected={active === tab.key}
                        onClick={() => setActive(tab.key)}
                        className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-2 py-2.5 text-sm font-medium ${
                            active === tab.key
                                ? 'border-indigo-500 text-gray-900 dark:text-gray-100'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        }`}
                    >
                        {strings[`tab_${tab.key}`]}
                        {tab.count !== null && <span className={pill(tab.danger && tab.count > 0 ? 'red' : 'gray')}>{tab.count}</span>}
                    </button>
                ))}
            </div>

            {active === 'blockers' && (
                <TabList
                    rows={data.blockers}
                    empty={strings.blockers_empty}
                    strings={strings}
                    render={(row) => (
                        <>
                            <TicketLine ticket={row} status={row.flagged ? strings.flagged : row.status} tone="red" />
                            <Sub>
                                {[row.assigneeName, row.days !== null && interpolate(strings.since_days, { days: Math.max(1, Math.round(row.days)) })].filter(Boolean).join(' · ')}
                            </Sub>
                        </>
                    )}
                />
            )}
            {active === 'waiting' && (
                <TabList
                    rows={data.waiting}
                    empty={strings.waiting_empty}
                    strings={strings}
                    render={(row) => (
                        <>
                            <TicketLine ticket={row} status={row.status} tone="purple" />
                            <Sub>{[row.assigneeName, row.since && relativeTime(row.since, locale)].filter(Boolean).join(' · ')}</Sub>
                        </>
                    )}
                />
            )}
            {active === 'po' && (
                <TabList
                    rows={data.forPo}
                    empty={strings.po_empty}
                    strings={strings}
                    render={(row) => (
                        <>
                            <PrLine row={row} status={strings[`pr_${row.event}`]} tone={PR_TONE[row.event]} />
                            {row.customerView?.change && <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{row.customerView.change}</p>}
                            {(row.customerView?.visibility || row.poDecisions.length > 0) && (
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {row.customerView?.visibility && <span className={pill('amber')}>{row.customerView.visibility}</span>}
                                    {row.poDecisions.length > 0 && <span className={pill('red')}>{transChoice(strings.po_decisions, row.poDecisions.length)}</span>}
                                </div>
                            )}
                        </>
                    )}
                />
            )}
            {active === 'ux' && (
                <TabList
                    rows={data.forUx}
                    empty={strings.ux_empty}
                    strings={strings}
                    render={(row) => (
                        <>
                            <PrLine row={row} status={strings[`pr_${row.event}`]} tone={PR_TONE[row.event]} />
                            <Sub>
                                {[row.uiFiles > 0 && transChoice(strings.ux_files, row.uiFiles), row.textFiles > 0 && transChoice(strings.ux_texts, row.textFiles)].filter(Boolean).join(' · ')}
                            </Sub>
                        </>
                    )}
                />
            )}
            {active === 'prs' && (
                <TabList
                    rows={[...data.pullRequests.map((pr) => ({ ...pr, kind: 'pr' })), ...data.reviews.map((r) => ({ ...r, kind: 'review' }))]}
                    empty={strings.activity_empty}
                    strings={strings}
                    render={(row) =>
                        row.kind === 'pr' ? (
                            <>
                                <PrLine row={row} status={strings[`pr_${row.event}`]} tone={PR_TONE[row.event]} />
                                <Sub>
                                    {[
                                        row.at && interpolate(strings.at, { time: time(row.at, locale) }),
                                        `+${row.additions} / −${row.deletions}`,
                                        row.ciState === 'failed' && interpolate(strings.ci_failed, { checks: row.ciFailedChecks.join(', ') }),
                                        row.unresolvedThreads > 0 && transChoice(strings.threads, row.unresolvedThreads),
                                    ].filter(Boolean).join(' · ')}
                                </Sub>
                            </>
                        ) : (
                            <>
                                <PrLine row={row} status={strings[`decision_${row.decision}`] ?? strings.decision_none} tone={DECISION_TONE[row.decision]} />
                                <Sub>
                                    {[interpolate(strings.review_by, { name: row.reviewerName ?? '' }), row.critical !== null && interpolate(strings.findings, row)].filter(Boolean).join(' · ')}
                                </Sub>
                            </>
                        )
                    }
                />
            )}
            {active === 'timeline' && <Heatmap heatmap={data.heatmap} strings={strings} />}
        </aside>
    );
}

// Gestern / Heute / Blocker als Fließtext zum Vorlesen oder für Slack.
function standupText(data, strings) {
    const task = (row) => `${row.key} ${row.summary}`;
    const prTitle = (pr) => (pr.tickets?.[0] ? `${pr.tickets[0].key} ${pr.tickets[0].summary}` : pr.title);
    const yesterday = [
        ...data.issueChanges.slice(0, 8).map((row) => interpolate(strings.standup_status, { task: task(row), status: row.to ?? '' })),
        ...data.pullRequests
            .filter((pr) => pr.event !== 'ciFailed')
            .map((pr) => interpolate(strings.standup_pr, { number: pr.number, event: strings[`pr_${pr.event}`], title: prTitle(pr) })),
        ...(data.reviews.length ? [transChoice(strings.standup_reviews, data.reviews.length)] : []),
    ];
    const today = data.today.slice(0, 6).map(task);
    const blockers = data.blockers.map((row) => `${task(row)} (${row.status})`);
    const section = (title, items) => `${title}\n${items.length ? items.map((item) => `- ${item}`).join('\n') : `- ${strings.standup_none}`}`;

    return [section(strings.standup_yesterday, yesterday), section(strings.standup_today, today), section(strings.standup_blockers, blockers)].join('\n\n');
}

export default function Daily({ day, scope, data, urls, strings, me }) {
    const locale = (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de';
    const [copied, setCopied] = useState(false);
    const text = useMemo(() => standupText(data, strings), [data, strings]);
    const cards = useMemo(() => people(data, scope, me, strings), [data, scope, me, strings]);
    const quiet = scope === 'team' ? data.team.filter((m) => !m.active && !cards.some((c) => c.name === m.name)) : [];
    const copy = () =>
        navigator.clipboard?.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });

    const segment = (active) =>
        'rounded-md px-3.5 py-1.5 text-sm font-medium transition ' +
        (active ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200');
    const navButton = 'px-2.5 py-1.5 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400';
    const kpis = [
        { value: data.kpis.doneTasks, label: strings.kpi_done_short, sub: interpolate(strings.kpi_done_sub_short, { review: data.kpis.toReview }) },
        { value: data.kpis.prsMerged, label: strings.kpi_prs_short, sub: interpolate(strings.kpi_prs_sub_short, { open: data.kpis.prsOpen }) },
        { value: data.kpis.reviewsGiven, label: strings.kpi_reviews_short, sub: interpolate(strings.kpi_reviews_sub_short, { waiting: data.kpis.waiting }) },
        {
            value: data.kpis.blockers,
            label: strings.kpi_blockers_short,
            sub: data.kpis.blockers > 0 ? interpolate(strings.kpi_blockers_sub, { days: Math.max(1, Math.round(data.kpis.oldestBlockerDays ?? 0)) }) : strings.kpi_blockers_none,
            danger: data.kpis.blockers > 0,
        },
    ];

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
                            <button type="button" onClick={copy} className="inline-flex items-center gap-2 whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                {copied ? strings.copied : strings.copy_standup}
                            </button>
                        </div>
                    </div>
                }
            />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {(!data.jira.connected || data.jira.error) && (
                        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/60">
                            {data.jira.error ? interpolate(strings.jira_error, { error: data.jira.error }) : strings.jira_missing}{' '}
                            <a href={urls.profile} className="font-semibold underline">{strings.jira_profile}</a>
                        </div>
                    )}

                    <div className={`${CARD} grid grid-cols-2 divide-gray-100 py-2 dark:divide-gray-700 lg:grid-cols-4 lg:divide-x`}>
                        {kpis.map((kpi) => (
                            <div key={kpi.label} className="flex flex-wrap items-baseline gap-x-2 px-4 py-1.5">
                                <span className={`text-2xl font-bold ${kpi.danger ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'}`}>{kpi.value}</span>
                                <span className="text-sm text-gray-700 dark:text-gray-300">{kpi.label}</span>
                                <span className="text-xs text-gray-400 dark:text-gray-500">{kpi.sub}</span>
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <section className="space-y-2 lg:col-span-2">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{transChoice(strings.standup_people, cards.length)}</h2>
                                <span className="text-xs text-gray-500 dark:text-gray-400">{strings.standup_order}</span>
                            </div>
                            {cards.length === 0 ? (
                                <p className={`${CARD} px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400`}>{strings.activity_empty}</p>
                            ) : (
                                <div className="grid grid-cols-1 gap-3">
                                    {cards.map((person) => <PersonCard key={person.name} person={person} strings={strings} limit={PER_SECTION[scope] ?? PER_SECTION.team} />)}
                                </div>
                            )}
                            {quiet.length > 0 && (
                                <p className="px-1 text-xs text-gray-500 dark:text-gray-400">
                                    {interpolate(strings.quiet_people, { names: quiet.map((m) => m.name).join(', ') })}
                                </p>
                            )}
                        </section>

                        <SidePanel data={data} strings={strings} locale={locale} />
                    </div>
                </div>
            </div>
        </>
    );
}

Daily.layout = AppShell;
