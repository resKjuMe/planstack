import React, { createContext, useContext, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { interpolate, transChoice } from '../../summary/i18n.js';
import { ApprovalChips, StateBadge } from '../components/PullRequestParts.jsx';

// Strings der PR-Liste (/pull-requests) für die aufgeklappten PRs.
const PrStrings = createContext({});

// Stand-up-Ansicht: je Person eine Karte mit Fortschritt, Heute und „Braucht Hilfe“.
// Die Auswertung kommt fertig vom Server (App\Support\DailyPresenter).

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';
const PILL = 'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap';
const TONE = {
    gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
};
const CATEGORY_TONE = { new: 'gray', indeterminate: 'blue', done: 'green' };
const PR_TONE = { merged: 'emerald', opened: 'blue', closed: 'gray' };
const DECISION_TEXT = { APPROVE: 'text-green-700 dark:text-green-400', REQUEST_CHANGES: 'text-amber-700 dark:text-amber-400' };
// Gründe, bei denen der PR-Ersteller selbst etwas tun muss (sonst: wartet auf andere).
const ACTION_REASONS = ['ciFailed', 'conflict', 'changesRequested', 'threads'];
const PER_SECTION = { me: 10, team: 4 };

const pill = (tone) => `${PILL} ${TONE[tone] ?? TONE.gray}`;
const days = (iso) => Math.max(1, Math.floor((Date.now() - Date.parse(iso)) / 86400000));
const initials = (name) =>
    String(name ?? '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

// Ein Raster je Karte, die Zeilen hängen per Subgrid daran: Spalten fluchten und
// sind nur so breit wie nötig. Avatar · vorher · → · nachher · Key · Titel · PRs.
const CARD_GRID = 'grid grid-cols-[auto_auto_auto_auto_auto_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-1';
const STANDUP_GRID = 'col-span-full grid grid-cols-subgrid items-center';
const CELL_PILL = 'max-w-full justify-self-start truncate';
const KEY_LINK = 'truncate font-mono text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-400';

function Avatar({ pr }) {
    if (pr?.avatar) return <img src={pr.avatar} alt="" title={pr.author ?? ''} className="h-5 w-5 shrink-0 rounded-full ring-1 ring-gray-200 dark:ring-gray-600" loading="lazy" />;
    if (pr) {
        return (
            <span title={pr.author ?? ''} className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                {pr.initials}
            </span>
        );
    }
    return <span className="h-5 w-5 shrink-0" aria-hidden="true" />;
}

function Toggle({ count, open, onClick, title }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-expanded={open}
            title={title}
            className="inline-flex items-center justify-end gap-1 rounded px-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
        >
            {count}
            <span className={`inline-block transition-transform ${open ? 'rotate-90' : ''}`} aria-hidden="true">▸</span>
        </button>
    );
}

// Stand-up-Zeile: PR-Ersteller · Status (vorher → nachher) · Ticket; der Pfeil
// klappt die PRs zum Ticket auf.
function StandupLine({ ticket, from = null, status, tone, statusTitle, note, prs = [], strings }) {
    const [open, setOpen] = useState(false);
    const link = ticket?.url ?? prs[0]?.url;

    return (
        <div className="contents">
            <div className={`${STANDUP_GRID} text-sm`}>
                <Avatar pr={prs[0]} />
                {from && from !== status ? (
                    <>
                        <span className={`${pill('gray')} ${CELL_PILL}`} title={from}>{from}</span>
                        <span className="text-center text-xs text-gray-400" aria-hidden="true">→</span>
                    </>
                ) : (
                    <>
                        <span />
                        <span />
                    </>
                )}
                {status ? <span className={`${pill(tone)} ${CELL_PILL}`} title={statusTitle ?? status}>{status}</span> : <span />}
                <a href={link} target="_blank" rel="noreferrer" className={KEY_LINK}>
                    {ticket?.key ?? `#${prs[0]?.number}`}
                </a>
                <span className="min-w-0 truncate text-gray-800 dark:text-gray-200" title={ticket?.summary}>
                    {ticket?.summary}
                    {note && <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">{note}</span>}
                </span>
                {prs.length > 0 ? (
                    <Toggle count={prs.length} open={open} onClick={() => setOpen((v) => !v)} title={transChoice(strings.pr_count, prs.length)} />
                ) : (
                    <span aria-hidden="true" />
                )}
            </div>
            {open && <PrDetails prs={prs} />}
        </div>
    );
}

// Mehrere Unteraufgaben einer Story in einer Zeile; der Pfeil zeigt die einzelnen Tickets.
function GroupLine({ group, strings }) {
    const [open, setOpen] = useState(false);
    const done = group.rows.every((row) => row.isDone);
    const counts = [...group.rows.reduce((map, row) => map.set(row.to, (map.get(row.to) ?? 0) + 1), new Map())]
        .map(([to, count]) => `${count} → ${to}`)
        .join(' · ');

    return (
        <div className="contents">
            <div className={`${STANDUP_GRID} text-sm`}>
                <Avatar pr={group.rows.flatMap((row) => row.prs ?? [])[0]} />
                <span />
                <span />
                <span className={`${pill(done ? 'green' : 'blue')} ${CELL_PILL}`}>{transChoice(strings.group_count, group.rows.length)}</span>
                <a href={group.parent.url} target="_blank" rel="noreferrer" className={KEY_LINK}>{group.parent.key}</a>
                <span className="min-w-0 truncate text-gray-800 dark:text-gray-200" title={group.parent.summary}>
                    {group.parent.summary}
                    <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">{counts}</span>
                </span>
                <Toggle count={group.rows.length} open={open} onClick={() => setOpen((v) => !v)} title={transChoice(strings.group_count, group.rows.length)} />
            </div>
            {open && (
                <div className="col-span-full grid grid-cols-subgrid gap-y-1 rounded-md bg-gray-50 py-1 dark:bg-gray-900/40">
                    {group.rows.map((row) => <ChangeLine key={row.key} row={row} strings={strings} />)}
                </div>
            )}
        </div>
    );
}

function ChangeLine({ row, strings }) {
    return <StandupLine ticket={row} from={row.from} status={row.to} tone={CATEGORY_TONE[row.toCategory]} prs={row.prs ?? []} strings={strings} />;
}

function ConflictIcon({ title }) {
    return (
        <span className="shrink-0 text-red-600 dark:text-red-400" title={title}>
            <svg viewBox="0 0 24 24" fill="currentColor" className="h-4 w-4" aria-hidden="true">
                <path fillRule="evenodd" d="M10.27 3.5a2 2 0 0 1 3.46 0l8.04 13.93A2 2 0 0 1 20.04 20.5H3.96a2 2 0 0 1-1.73-3.07L10.27 3.5zM12 9a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0v-4a1 1 0 0 0-1-1zm0 9a1.25 1.25 0 1 0 0-2.5A1.25 1.25 0 0 0 12 18z" clipRule="evenodd" />
            </svg>
        </span>
    );
}

// Aufgeklappte PRs: Zustand, Konflikt, CI und Freigaben (inkl. ungelöster Kommentare).
function PrDetails({ prs }) {
    const t = useContext(PrStrings);

    return (
        <div className="col-span-full mb-2 ml-[0.6rem] flex flex-col gap-1 border-l border-gray-200 py-1 pl-[1.15rem] text-xs dark:border-gray-700">
            {prs.map((pr) => {
                const d = pr.details;
                const open = pr.state === 'OPEN';
                const ciFailed = open && d?.ciState === 'failed';

                return (
                    <div key={pr.url} className="flex flex-wrap items-center gap-2">
                        <Avatar pr={pr} />
                        <a href={pr.url} className="font-mono font-semibold text-indigo-700 hover:underline dark:text-indigo-400">#{pr.number}</a>
                        <StateBadge state={pr.state} strings={t} />
                        {open && d?.conflicting && <ConflictIcon title={t.conflicting} />}
                        {ciFailed && (
                            <span className="rounded bg-red-600 px-1.5 py-0.5 text-[11px] font-semibold text-white" title={(d.ciFailedChecks ?? []).join('\n') || t.ci_failed}>
                                {t.status_ci_failed}
                            </span>
                        )}
                        {d && <ApprovalChips approvals={d.approvals} unresolvedThreads={open ? d.unresolvedThreads : 0} strings={t} />}
                    </div>
                );
            })}
        </div>
    );
}

// Reviews für andere: eine Zeile statt einer Zeile je PR.
function ReviewsLine({ reviews, strings }) {
    if (!reviews.length) return null;

    return (
        <div className="col-span-full flex flex-wrap items-baseline gap-x-2 gap-y-0.5 pt-1 text-xs text-gray-600 dark:text-gray-400">
            <span className="font-medium text-gray-800 dark:text-gray-200">{transChoice(strings.reviews_line, reviews.length)}:</span>
            {reviews.map((review) => {
                const ticket = review.tickets?.[0];
                return (
                    <a
                        key={review.key}
                        href={ticket?.url ?? review.pr?.url ?? review.url}
                        target="_blank"
                        rel="noreferrer"
                        title={`${ticket?.summary ?? review.title} · ${strings[`decision_${review.decision}`] ?? strings.decision_none}`}
                        className={`font-mono font-semibold hover:underline ${DECISION_TEXT[review.decision] ?? 'text-gray-600 dark:text-gray-400'}`}
                    >
                        {ticket?.key ?? `#${review.number}`}
                    </a>
                );
            })}
        </div>
    );
}

function AttentionLine({ row, strings }) {
    const ticket = row.tickets?.[0] ?? { key: `#${row.number}`, summary: row.title, url: row.pr?.url ?? row.url };
    const urgent = row.reasons.some((reason) => reason === 'ciFailed' || reason === 'conflict');

    return (
        <StandupLine
            ticket={ticket}
            status={row.reasons.map((reason) => strings[`reason_${reason}`]).join(' · ')}
            statusTitle={row.ciFailedChecks?.length ? row.ciFailedChecks.join('\n') : undefined}
            tone={urgent ? 'red' : 'amber'}
            note={row.openedAt && row.reasons.includes('waitingReview') ? interpolate(strings.waiting_days, { days: days(row.openedAt) }) : null}
            prs={row.pr ? [row.pr] : []}
            strings={strings}
        />
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

function Section({ label, rows, render, strings, limit, empty = null, children = null }) {
    const [open, setOpen] = useState(false);
    if (!rows.length && !empty && !children) return null;
    const shown = open ? rows : rows.slice(0, limit);

    return (
        <div className="contents">
            <span className="col-span-full pt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</span>
            {rows.length ? shown.map(render) : !children && <span className="col-span-full text-xs text-gray-400 dark:text-gray-500">{empty}</span>}
            {rows.length > limit && (
                <span className="col-span-full">
                    <More count={rows.length - limit} open={open} onClick={() => setOpen((v) => !v)} strings={strings} />
                </span>
            )}
            {children}
        </div>
    );
}

function ProgressItem({ item, strings }) {
    if (item.kind === 'group') return <GroupLine group={item} strings={strings} />;
    if (item.kind === 'change') return <ChangeLine row={item.row} strings={strings} />;
    const pr = item.row;
    return (
        <StandupLine
            ticket={pr.tickets?.[0] ?? { key: `#${pr.number}`, summary: pr.title, url: pr.pr?.url ?? pr.url }}
            status={strings[`pr_${pr.event}`]}
            tone={PR_TONE[pr.event]}
            prs={pr.pr ? [pr.pr] : []}
            strings={strings}
        />
    );
}

function HelpItem({ item, strings }) {
    const row = item.row;
    if (item.kind === 'pr') return <AttentionLine row={row} strings={strings} />;
    if (item.kind === 'stale') {
        return <StandupLine ticket={row} status={row.status} tone="amber" note={row.updatedAt ? interpolate(strings.stale, { days: days(row.updatedAt) }) : null} prs={row.prs ?? []} strings={strings} />;
    }
    return <StandupLine ticket={row} status={row.flagged ? strings.flagged : row.status} tone="red" prs={row.prs ?? []} strings={strings} />;
}

const itemKey = (item) => (item.kind === 'group' ? `g-${item.parent.key}` : item.kind === 'change' ? item.row.key : `${item.kind}-${item.row.key ?? item.row.number}`);

// Karte einer Person: Fortschritt / Heute / Braucht Hilfe.
function PersonCard({ person, strings, limit }) {
    return (
        <article className={`${CARD} flex flex-col gap-2 p-4`}>
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
            <div className={CARD_GRID}>
                <Section
                    label={strings.section_progress}
                    rows={person.progress}
                    strings={strings}
                    limit={limit}
                    empty={strings.standup_none}
                    render={(item) => <ProgressItem key={itemKey(item)} item={item} strings={strings} />}
                >
                    <ReviewsLine reviews={person.reviews} strings={strings} />
                </Section>
                <Section
                    label={strings.section_today}
                    rows={[...person.attention.map((row) => ({ kind: 'pr', row })), ...person.today.map((row) => ({ kind: 'today', row }))]}
                    strings={strings}
                    limit={limit}
                    render={(item) =>
                        item.kind === 'pr' ? (
                            <AttentionLine key={`pr-${item.row.number}`} row={item.row} strings={strings} />
                        ) : (
                            <StandupLine key={item.row.key} ticket={item.row} status={item.row.status} tone={CATEGORY_TONE[item.row.category]} prs={item.row.prs ?? []} strings={strings} />
                        )
                    }
                />
                {person.help.length > 0 && (
                    <>
                        <span className="col-span-full pt-2 text-[11px] font-semibold uppercase tracking-wide text-red-700 dark:text-red-400">{strings.section_help}</span>
                        <div className="col-span-full grid grid-cols-subgrid gap-y-1 rounded-md bg-red-50 py-2 dark:bg-red-900/20">
                            {person.help.map((item) => <HelpItem key={itemKey(item)} item={item} strings={strings} />)}
                        </div>
                    </>
                )}
            </div>
        </article>
    );
}

// Statuswechsel nach Story zusammengefasst; Tickets ohne Story bzw. allein unter ihrer Story einzeln.
function progress(rows) {
    const groups = new Map();
    const items = [];
    rows.forEach((row) => {
        const key = row.parent?.key;
        if (!key) {
            items.push({ kind: 'change', row });
            return;
        }
        if (!groups.has(key)) {
            const group = { kind: 'group', parent: row.parent, rows: [] };
            groups.set(key, group);
            items.push(group);
        }
        groups.get(key).rows.push(row);
    });

    return items.map((item) => (item.kind === 'group' && item.rows.length === 1 ? { kind: 'change', row: item.rows[0] } : item));
}

// Personen aus den Listen: im Team nach Zuständigem bzw. PR-Ersteller gruppiert,
// sonst eine Karte für mich. Wer Hilfe braucht zuerst, sonst nach letzter Aktivität.
function people(data, scope, me, strings) {
    const byName = new Map();
    const get = (name) => {
        const key = scope === 'team' ? name || strings.unassigned : me;
        if (!byName.has(key)) byName.set(key, { name: key, changes: [], prs: [], reviews: [], today: [], attention: [], help: [], last: '' });
        return byName.get(key);
    };
    const touch = (p, at) => {
        if (at && at > p.last) p.last = at;
    };

    data.issueChanges.forEach((row) => {
        const p = get(row.assigneeName);
        p.changes.push(row);
        touch(p, row.at);
    });
    // PRs des Tages nur, wenn ihr Ticket nicht schon als Statuswechsel dasteht.
    data.pullRequests.forEach((pr) => {
        const p = get(pr.authorName);
        const known = pr.tickets?.length > 0 && pr.tickets.every((t) => p.changes.some((row) => row.key === t.key));
        if (!known) p.prs.push({ kind: 'pr', row: pr });
        touch(p, pr.at);
    });
    data.reviews.forEach((review) => {
        const p = get(review.reviewerName);
        p.reviews.push(review);
        touch(p, review.at);
    });
    data.attention.forEach((row) => {
        const p = get(row.authorName);
        if (row.reasons.some((reason) => ACTION_REASONS.includes(reason))) p.attention.push(row);
        else p.help.push({ kind: 'pr', row });
    });
    data.today.forEach((row) => {
        const p = get(row.assigneeName);
        if (row.isStale) p.help.push({ kind: 'stale', row });
        else p.today.push(row);
    });
    data.blockers.forEach((row) => get(row.assigneeName).help.unshift({ kind: 'blocker', row }));

    return [...byName.values()]
        .map((p) => {
            const done = p.changes.filter((r) => r.isDone).length;
            const merged = data.pullRequests.filter((pr) => pr.event === 'merged' && (scope !== 'team' || pr.authorName === p.name)).length;
            const avatar = [...p.changes, ...p.today].flatMap((r) => r.prs ?? []).find((pr) => pr.author === p.name && pr.avatar)?.avatar;

            return {
                ...p,
                avatar,
                progress: [...progress(p.changes), ...p.prs],
                stats: [
                    transChoice(strings.person_moved, p.changes.length),
                    done > 0 && interpolate(strings.person_done, { count: done }),
                    merged > 0 && interpolate(strings.person_merged, { count: merged }),
                    p.reviews.length > 0 && transChoice(strings.person_reviews, p.reviews.length),
                ].filter(Boolean).join(' · '),
            };
        })
        .filter((p) => p.changes.length || p.prs.length || p.reviews.length || p.today.length || p.attention.length || p.help.length)
        .sort((a, b) => (b.help.length > 0) - (a.help.length > 0) || (b.last > a.last ? 1 : -1));
}

// Gestern / Heute / Blocker & Hilfe als Text zum Vorlesen oder für Slack.
function standupText(cards, scope, strings) {
    const task = (row) => `${row.key} ${row.summary}`;
    const prTitle = (pr) => (pr.tickets?.[0] ? task(pr.tickets[0]) : pr.title);
    const reasons = (row) => row.reasons.map((reason) => strings[`reason_${reason}`]).join(', ');
    const section = (title, items) => `${title}\n${items.length ? items.map((item) => `- ${item}`).join('\n') : `- ${strings.standup_none}`}`;

    return cards
        .map((p) => {
            const yesterday = [
                ...p.progress.map((item) => {
                    if (item.kind === 'change') return interpolate(strings.standup_status, { task: task(item.row), status: item.row.to ?? '' });
                    if (item.kind === 'group') {
                        const counts = [...item.rows.reduce((map, row) => map.set(row.to, (map.get(row.to) ?? 0) + 1), new Map())].map(([to, count]) => `${count} → ${to}`).join(', ');
                        return `${item.parent.key} ${item.parent.summary}: ${counts}`;
                    }
                    return interpolate(strings.standup_pr, { number: item.row.number, event: strings[`pr_${item.row.event}`], title: prTitle(item.row) });
                }),
                ...(p.reviews.length ? [transChoice(strings.standup_reviews, p.reviews.length)] : []),
            ];
            const today = [...p.attention.map((row) => `PR #${row.number} ${prTitle(row)}: ${reasons(row)}`), ...p.today.map(task)];
            const help = p.help.map(({ kind, row }) => {
                if (kind === 'pr') return `PR #${row.number} ${prTitle(row)}: ${reasons(row)}`;
                if (kind === 'stale') return `${task(row)} (${interpolate(strings.stale, { days: days(row.updatedAt) })})`;
                return `${task(row)} (${row.flagged ? strings.flagged : row.status})`;
            });
            const body = [section(strings.standup_yesterday, yesterday), section(strings.standup_today, today), section(strings.standup_blockers, help)].join('\n\n');

            return scope === 'team' ? `*${p.name}*\n${body}` : body;
        })
        .join('\n\n');
}

export default function Daily({ day, scope, data, urls, strings, prStrings = {}, me }) {
    const [copied, setCopied] = useState(false);
    const cards = useMemo(() => people(data, scope, me, strings), [data, scope, me, strings]);
    const text = useMemo(() => standupText(cards, scope, strings), [cards, scope, strings]);
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

    return (
        <PrStrings.Provider value={prStrings}>
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
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {(!data.jira.connected || data.jira.error) && (
                        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/60">
                            {data.jira.error ? interpolate(strings.jira_error, { error: data.jira.error }) : strings.jira_missing}{' '}
                            <a href={urls.profile} className="font-semibold underline">{strings.jira_profile}</a>
                        </div>
                    )}

                    <section className="space-y-2">
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
                </div>
            </div>
        </PrStrings.Provider>
    );
}

Daily.layout = AppShell;
