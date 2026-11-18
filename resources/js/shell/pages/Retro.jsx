import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PrAuthors from '../components/PrAuthors.jsx';
import { interpolate, transChoice } from '../../summary/i18n.js';

// Retro-Ansicht: oben eine Scorecard (Bewertung + Kennzahlen je Kategorie), darunter
// die Gesprächsthemen nach Gewicht – Belege erst auf Klick. Die Auswertung kommt
// fertig vom Server (App\Support\RetroPresenter); die eigene Bewertung bleibt im
// Browser (localStorage) und wandert in den kopierten Retro-Text.

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';
const CATEGORIES = [
    { key: 'terrible', bar: 'border-t-red-500', text: 'text-red-700 dark:text-red-400', dot: 'bg-red-500' },
    { key: 'bad', bar: 'border-t-amber-500', text: 'text-amber-700 dark:text-amber-400', dot: 'bg-amber-500' },
    { key: 'excellent', bar: 'border-t-emerald-500', text: 'text-emerald-700 dark:text-emerald-400', dot: 'bg-emerald-500' },
    { key: 'good', bar: 'border-t-blue-500', text: 'text-blue-700 dark:text-blue-400', dot: 'bg-blue-500' },
];
const SCORE_ORDER = ['excellent', 'good', 'bad', 'terrible'];
const COMPONENTS = ['delivery', 'quality', 'flow'];
const LEARNED = ['reopened', 'flagged', 'slow', 'findings'];
const PER_LIST = 5;

function readStore(key) {
    try {
        return JSON.parse(window.localStorage.getItem(key) ?? 'null');
    } catch {
        return null;
    }
}

function writeStore(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Ohne Speicher bleibt der Stand nur bis zum Neuladen.
    }
}

function itemText(item, strings) {
    return transChoice(strings[`item_${item.key}`], item.params.count, item.params);
}

// Beleg als einzeilige Zeile: Key · Titel · PR-Ersteller.
function RefLine({ item }) {
    return (
        <div className="flex min-w-0 items-center gap-2 text-sm">
            <a
                href={item.url}
                target={item.url?.startsWith('http') ? '_blank' : undefined}
                rel="noreferrer"
                className="shrink-0 font-mono text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-400"
            >
                {item.label}
            </a>
            <span className="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300" title={item.title}>
                {item.title}
                {item.days ? ` (${item.days} d)` : ''}
            </span>
            <PrAuthors prs={item.prs} compact className="shrink-0" />
        </div>
    );
}

function RefList({ refs, more, strings }) {
    const [all, setAll] = useState(false);
    const shown = all ? refs : refs.slice(0, PER_LIST);

    return (
        <div className="flex flex-col gap-1">
            {shown.map((ref) => <RefLine key={ref.id} item={ref} />)}
            {!all && refs.length > PER_LIST && (
                <button type="button" onClick={() => setAll(true)} className="self-start text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                    {interpolate(strings.more, { count: refs.length - PER_LIST + more })}
                </button>
            )}
            {(all || refs.length <= PER_LIST) && more > 0 && (
                <span className="text-xs text-gray-400 dark:text-gray-500">{interpolate(strings.more_hidden, { count: more })}</span>
            )}
        </div>
    );
}

function RatingTile({ rating, value, onChange, onReset, strings }) {
    const shown = value ?? rating.percent;

    return (
        <div className={`${CARD} flex flex-col gap-2 p-4`}>
            <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{strings.q_rating}</span>
            <div className="text-4xl font-bold leading-none text-gray-900 dark:text-gray-100">
                {shown ?? '–'}
                <span className="ms-1 text-xl font-semibold text-gray-400 dark:text-gray-500">%</span>
            </div>
            <input
                aria-label={strings.rating_own}
                type="range"
                min="0"
                max="100"
                step="5"
                value={shown ?? 50}
                onChange={(e) => onChange(Number(e.target.value))}
                className="w-full accent-indigo-600"
            />
            <p className="text-xs text-gray-500 dark:text-gray-400" title={COMPONENTS.map((key) => `${strings[`comp_${key}`]}: ${strings[`comp_${key}_hint`]}`).join('\n')}>
                {COMPONENTS.map((key) => `${strings[`comp_${key}`]} ${rating.components[key] ?? '–'}`).join(' · ')}
            </p>
            {value !== null && rating.percent !== null && value !== rating.percent && (
                <button type="button" onClick={onReset} className="self-start text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                    {interpolate(strings.rating_reset_to, { percent: rating.percent })}
                </button>
            )}
        </div>
    );
}

function CategoryTile({ cat, items, strings }) {
    return (
        <div className={`${CARD} flex flex-col gap-2 border-t-4 p-4 ${cat.bar}`}>
            <span className={`text-xs font-semibold uppercase tracking-wide ${cat.text}`}>{strings[`cat_${cat.key}`]}</span>
            {items.length === 0 && <span className="text-sm text-gray-400 dark:text-gray-500">{strings.cat_empty}</span>}
            {items.map((item) => (
                <div key={item.key} className="flex items-baseline gap-2">
                    <span className="min-w-[3.25rem] text-xl font-bold text-gray-900 dark:text-gray-100">
                        {item.params.total ? `${item.params.count}/${item.params.total}` : item.params.count}
                    </span>
                    <span className="text-xs text-gray-600 dark:text-gray-400">{strings[`kpi_${item.key}`]}</span>
                </div>
            ))}
        </div>
    );
}

function Topic({ cat, item, strings }) {
    const [open, setOpen] = useState(false);
    const hasRefs = item.refs.length > 0;

    return (
        <li className="border-t border-gray-100 px-5 py-2.5 first:border-t-0 dark:border-gray-700/60">
            <button
                type="button"
                disabled={!hasRefs}
                onClick={() => setOpen((v) => !v)}
                aria-expanded={hasRefs ? open : undefined}
                className="flex w-full items-center gap-3 text-left disabled:cursor-default"
            >
                <span className={`h-2 w-2 shrink-0 rounded-full ${cat.dot}`} />
                <span className="flex-1 text-sm text-gray-800 dark:text-gray-200">{itemText(item, strings)}</span>
                {hasRefs && (
                    <span className="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                        {transChoice(strings.evidence, item.refs.length + item.more)} {open ? '▾' : '▸'}
                    </span>
                )}
            </button>
            {open && (
                <div className="mt-2 ps-5">
                    <RefList refs={item.refs} more={item.more} strings={strings} />
                </div>
            )}
        </li>
    );
}

function Learned({ learned, strings }) {
    const groups = LEARNED.filter((key) => learned[key].length > 0);
    const [active, setActive] = useState(null);

    if (groups.length === 0) return <p className="text-sm text-gray-400 dark:text-gray-500">{strings.learned_empty}</p>;

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2">
                {groups.map((key) => (
                    <button
                        key={key}
                        type="button"
                        aria-pressed={active === key}
                        onClick={() => setActive(active === key ? null : key)}
                        className={`rounded-full px-3 py-1 text-xs font-medium ring-1 transition ${
                            active === key
                                ? 'bg-gray-900 text-white ring-gray-900 dark:bg-gray-100 dark:text-gray-900'
                                : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'
                        }`}
                    >
                        {learned[key].length} {strings[`learned_${key}`]}
                    </button>
                ))}
            </div>
            {active && <RefList refs={learned[active]} more={0} strings={strings} />}
        </div>
    );
}

function retroText({ sprint, period, rating, ratingValue, data, strings }) {
    const lines = [];
    const list = (items) => (items.length ? items.map((i) => `- ${i}`) : ['- –']);

    lines.push(`${strings.title}: ${sprint ? sprint.name : strings.period} (${period.label})`);
    if (sprint?.goal) lines.push(`${strings.sprint_goal}: ${sprint.goal}`);
    lines.push('');
    lines.push(`${strings.q_rating}: ${ratingValue ?? rating.percent ?? '–'} %`);
    lines.push('');
    SCORE_ORDER.forEach((key) => {
        lines.push(`${strings.q_went} ${strings[`cat_${key}`].toLowerCase()}`);
        lines.push(...list(data.categories[key].map((item) => itemText(item, strings))));
        lines.push('');
    });
    lines.push(strings.q_improve);
    lines.push(...list(data.improve.map((item) => interpolate(strings[`improve_${item.key}`], item.params))));
    lines.push('');
    lines.push(strings.q_learned);
    lines.push(...list(LEARNED.flatMap((key) => data.learned[key].map((ref) => `${strings[`learned_${key}`]}: ${ref.label} ${ref.title}`))));

    return lines.join('\n');
}

export default function Retro({ period, scope, data, urls, strings }) {
    const storeKey = `planstack:retro:${period.key}:${scope}`;
    const [state, setState] = useState(() => readStore(storeKey) ?? { rating: null });
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        setState(readStore(storeKey) ?? { rating: null });
    }, [storeKey]);

    const update = (patch) =>
        setState((prev) => {
            const next = { ...prev, ...patch };
            writeStore(storeKey, next);
            return next;
        });

    const sprint = data.sprint;
    const text = useMemo(
        () => retroText({ sprint, period, rating: data.rating, ratingValue: state.rating, data, strings }),
        [sprint, period, data, state, strings],
    );
    const copy = () =>
        navigator.clipboard?.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    const topics = CATEGORIES.flatMap((cat) => data.categories[cat.key].map((item) => ({ cat, item })));

    const segment = (active) =>
        'rounded-md px-3.5 py-1.5 text-sm font-medium transition ' +
        (active ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200');
    const navButton = 'px-2.5 py-1.5 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400';

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0">
                            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                                {strings.title}
                                {sprint && <span className="ms-2 font-normal text-gray-500 dark:text-gray-400">{sprint.name}</span>}
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                <span className="font-semibold text-gray-800 dark:text-gray-200">{period.label}</span>
                                {' · '}
                                {scope === 'team' ? strings.subtitle_team : strings.subtitle_me}
                                {sprint?.goal && (
                                    <>
                                        {' · '}
                                        {strings.sprint_goal}: <span className="text-gray-700 dark:text-gray-300">{sprint.goal}</span>
                                    </>
                                )}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="inline-flex items-center rounded-md bg-white ring-1 ring-gray-300 dark:bg-gray-800 dark:ring-gray-600">
                                {urls.prev ? (
                                    <a href={urls.prev} className={navButton} aria-label={strings.prev} title={strings.prev}>‹</a>
                                ) : (
                                    <span className="px-2.5 py-1.5 text-gray-300 dark:text-gray-600" aria-hidden="true">‹</span>
                                )}
                                {urls.next ? (
                                    <a href={urls.next} className={navButton} aria-label={strings.next} title={strings.next}>›</a>
                                ) : (
                                    <span className="px-2.5 py-1.5 text-gray-300 dark:text-gray-600" aria-hidden="true">›</span>
                                )}
                            </div>
                            {urls.weeks && (
                                <div role="group" aria-label={strings.weeks_label} className="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-700">
                                    {Object.entries(urls.weeks).map(([weeks, href]) => (
                                        <a key={weeks} href={href} aria-current={Number(weeks) === period.weeks ? 'true' : undefined} className={segment(Number(weeks) === period.weeks)}>
                                            {transChoice(strings.weeks, Number(weeks))}
                                        </a>
                                    ))}
                                </div>
                            )}
                            <div role="group" aria-label={strings.scope_label} className="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-700">
                                <a href={urls.me} aria-current={scope === 'me' ? 'true' : undefined} className={segment(scope === 'me')}>{strings.scope_me}</a>
                                <a href={urls.team} aria-current={scope === 'team' ? 'true' : undefined} className={segment(scope === 'team')}>{strings.scope_team}</a>
                            </div>
                            <button type="button" onClick={copy} className="inline-flex items-center gap-2 whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                {copied ? strings.copied : strings.copy_retro}
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
                    {data.jira.connected && !data.jira.error && !sprint && <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_sprint}</p>}

                    <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <RatingTile
                            rating={data.rating}
                            value={state.rating}
                            onChange={(rating) => update({ rating })}
                            onReset={() => update({ rating: null })}
                            strings={strings}
                        />
                        {SCORE_ORDER.map((key) => (
                            <CategoryTile key={key} cat={CATEGORIES.find((c) => c.key === key)} items={data.categories[key]} strings={strings} />
                        ))}
                    </section>

                    <section className={`${CARD} py-2`}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2 px-5 py-2">
                            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.topics_title}</h2>
                            <span className="text-xs text-gray-500 dark:text-gray-400">{strings.topics_sub}</span>
                        </div>
                        {topics.length === 0 ? (
                            <p className="px-5 pb-3 text-sm text-gray-400 dark:text-gray-500">{strings.cat_empty}</p>
                        ) : (
                            <ul>
                                {topics.map(({ cat, item }) => <Topic key={`${cat.key}-${item.key}`} cat={cat} item={item} strings={strings} />)}
                            </ul>
                        )}
                    </section>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <section className={`${CARD} space-y-3 p-5`}>
                            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.q_improve}</h2>
                            {data.improve.length === 0 ? (
                                <p className="text-sm text-gray-400 dark:text-gray-500">{strings.improve_empty}</p>
                            ) : (
                                <ul className="list-disc space-y-1.5 ps-5 text-sm text-gray-800 dark:text-gray-200">
                                    {data.improve.map((item) => <li key={item.key}>{interpolate(strings[`improve_${item.key}`], item.params)}</li>)}
                                </ul>
                            )}
                        </section>
                        <section className={`${CARD} space-y-3 p-5`}>
                            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.q_learned}</h2>
                            <Learned learned={data.learned} strings={strings} />
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

Retro.layout = AppShell;
