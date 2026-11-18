import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PrAuthors from '../components/PrAuthors.jsx';
import { interpolate, transChoice } from '../../summary/i18n.js';

// Retro-Ansicht: Vorschläge aus Jira (letzter abgeschlossener Sprint) und GitHub
// für die Fragen der Sprint-Retro. Die Auswertung kommt fertig vom Server
// (App\Support\RetroPresenter); eigene Bewertung und eigene Punkte bleiben im
// Browser (localStorage) und wandern in den kopierten Retro-Text.

const CARD = 'rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700';
const CATEGORIES = [
    { key: 'excellent', ring: 'ring-emerald-200 dark:ring-emerald-900/60', head: 'text-emerald-800 dark:text-emerald-300', dot: 'bg-emerald-500' },
    { key: 'good', ring: 'ring-blue-200 dark:ring-blue-900/60', head: 'text-blue-800 dark:text-blue-300', dot: 'bg-blue-500' },
    { key: 'bad', ring: 'ring-amber-200 dark:ring-amber-900/60', head: 'text-amber-800 dark:text-amber-300', dot: 'bg-amber-500' },
    { key: 'terrible', ring: 'ring-red-200 dark:ring-red-900/60', head: 'text-red-800 dark:text-red-300', dot: 'bg-red-500' },
];
const COMPONENTS = ['delivery', 'quality', 'flow'];
const LEARNED = ['reopened', 'flagged', 'slow', 'findings'];

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

function PanelHead({ title, sub }) {
    return (
        <div>
            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
            {sub && <p className="text-xs text-gray-400 dark:text-gray-500">{sub}</p>}
        </div>
    );
}

function Refs({ refs, more, strings }) {
    if (!refs.length) return null;

    return (
        <ul className="mt-1.5 space-y-1">
            {refs.map((ref) => (
                <li key={ref.id} className="min-w-0 text-xs">
                    <div className="flex min-w-0 gap-2">
                        <a href={ref.url} target={ref.url?.startsWith('http') ? '_blank' : undefined} rel="noreferrer" className="shrink-0 font-mono font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                            {ref.label}
                        </a>
                        <span className="truncate text-gray-600 dark:text-gray-400" title={ref.title}>{ref.title}</span>
                    </div>
                    <PrAuthors prs={ref.prs} compact className="mt-0.5 ps-1" />
                </li>
            ))}
            {more > 0 && <li className="text-xs text-gray-400 dark:text-gray-500">{interpolate(strings.more, { count: more })}</li>}
        </ul>
    );
}

function Notes({ value, onChange, strings }) {
    return (
        <label className="mt-3 block">
            <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{strings.notes_label}</span>
            <textarea
                value={value}
                onChange={(e) => onChange(e.target.value)}
                rows={3}
                placeholder={strings.notes_placeholder}
                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
            />
        </label>
    );
}

function Rating({ rating, value, onChange, onReset, strings }) {
    const shown = value ?? rating.percent;

    return (
        <section className={`${CARD} p-5`}>
            <PanelHead title={strings.q_rating} sub={strings.rating_sub} />
            <div className="mt-4 flex flex-wrap items-center gap-6">
                <div className="text-5xl font-bold text-gray-900 dark:text-gray-100">
                    {shown ?? '–'}
                    <span className="ms-1 text-2xl font-semibold text-gray-400 dark:text-gray-500">%</span>
                </div>
                <div className="min-w-[240px] flex-1">
                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400" htmlFor="retro-rating">{strings.rating_own}</label>
                    <input
                        id="retro-rating"
                        type="range"
                        min="0"
                        max="100"
                        step="5"
                        value={shown ?? 50}
                        onChange={(e) => onChange(Number(e.target.value))}
                        className="mt-1 w-full accent-indigo-600"
                    />
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {rating.percent !== null ? interpolate(strings.rating_suggested, { percent: rating.percent }) : strings.rating_none}
                        {value !== null && rating.percent !== null && value !== rating.percent && (
                            <button type="button" onClick={onReset} className="ms-2 font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                {strings.rating_reset}
                            </button>
                        )}
                    </p>
                </div>
            </div>
            <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                {COMPONENTS.map((key) => {
                    const percent = rating.components[key];
                    return (
                        <div key={key}>
                            <div className="flex items-baseline justify-between text-sm">
                                <span className="font-medium text-gray-800 dark:text-gray-200">{strings[`comp_${key}`]}</span>
                                <span className="font-semibold text-gray-900 dark:text-gray-100">{percent !== null ? `${percent} %` : '–'}</span>
                            </div>
                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div className="h-full rounded-full bg-indigo-500" style={{ width: `${percent ?? 0}%` }} />
                            </div>
                            <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings[`comp_${key}_hint`]}</p>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function retroText({ sprint, period, rating, ratingValue, data, notes, strings }) {
    const lines = [];
    const notesOf = (key) => (notes[key] ?? '').split('\n').map((l) => l.trim()).filter(Boolean);
    const list = (items) => (items.length ? items.map((i) => `- ${i}`) : ['- –']);

    lines.push(`${strings.title}: ${sprint ? sprint.name : strings.period} (${period.label})`);
    if (sprint?.goal) lines.push(`${strings.sprint_goal}: ${sprint.goal}`);
    lines.push('');
    lines.push(`${strings.q_rating}: ${ratingValue ?? rating.percent ?? '–'} %`);
    lines.push('');
    CATEGORIES.forEach(({ key }) => {
        lines.push(`${strings.q_went} ${strings[`cat_${key}`].toLowerCase()}`);
        lines.push(...list([...data.categories[key].map((item) => itemText(item, strings)), ...notesOf(key)]));
        lines.push('');
    });
    lines.push(strings.q_improve);
    lines.push(...list([...data.improve.map((item) => interpolate(strings[`improve_${item.key}`], item.params)), ...notesOf('improve')]));
    lines.push('');
    lines.push(strings.q_learned);
    lines.push(
        ...list([
            ...LEARNED.flatMap((key) => data.learned[key].map((ref) => `${strings[`learned_${key}`]}: ${ref.label} ${ref.title}`)),
            ...notesOf('learned'),
        ]),
    );

    return lines.join('\n');
}

export default function Retro({ period, scope, data, urls, strings }) {
    const storeKey = `planstack:retro:${period.key}:${scope}`;
    const [state, setState] = useState(() => readStore(storeKey) ?? { rating: null, notes: {} });
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        setState(readStore(storeKey) ?? { rating: null, notes: {} });
    }, [storeKey]);

    const update = (patch) =>
        setState((prev) => {
            const next = { ...prev, ...patch };
            writeStore(storeKey, next);
            return next;
        });
    const setNote = (key, value) => update({ notes: { ...state.notes, [key]: value } });

    const sprint = data.sprint;
    const text = useMemo(
        () => retroText({ sprint, period, rating: data.rating, ratingValue: state.rating, data, notes: state.notes, strings }),
        [sprint, period, data, state, strings],
    );
    const copy = () =>
        navigator.clipboard?.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });

    const segment = (active) =>
        'rounded-md px-3.5 py-1.5 text-sm font-medium transition ' +
        (active
            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200');
    const navButton = 'px-2.5 py-1.5 text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400';
    const learnedCount = LEARNED.reduce((sum, key) => sum + data.learned[key].length, 0);

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                                {strings.title}
                                {sprint && <span className="ms-2 font-normal text-gray-500 dark:text-gray-400">{sprint.name}</span>}
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                <span className="font-semibold text-gray-800 dark:text-gray-200">{period.label}</span>
                                {' · '}
                                {scope === 'team' ? strings.subtitle_team : strings.subtitle_me}
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
                            <button
                                type="button"
                                onClick={copy}
                                className="inline-flex items-center gap-2 whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                {copied ? strings.copied : strings.copy_retro}
                            </button>
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {(!data.jira.connected || data.jira.error) && (
                        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/60">
                            {data.jira.error ? interpolate(strings.jira_error, { error: data.jira.error }) : strings.jira_missing}{' '}
                            <a href={urls.profile} className="font-semibold underline">{strings.jira_profile}</a>
                        </div>
                    )}
                    {data.jira.connected && !data.jira.error && !sprint && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_sprint}</p>
                    )}
                    {sprint?.goal && (
                        <div className={`${CARD} px-5 py-4`}>
                            <span className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.sprint_goal}</span>
                            <p className="mt-0.5 text-sm text-gray-800 dark:text-gray-200">{sprint.goal}</p>
                        </div>
                    )}

                    <Rating
                        rating={data.rating}
                        value={state.rating}
                        onChange={(rating) => update({ rating })}
                        onReset={() => update({ rating: null })}
                        strings={strings}
                    />

                    <section className="space-y-3">
                        <h2 className="font-semibold text-gray-900 dark:text-gray-100">{strings.q_went}</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {CATEGORIES.map((cat) => (
                                <div key={cat.key} className={`rounded-lg bg-white p-4 ring-1 dark:bg-gray-800 ${cat.ring}`}>
                                    <h3 className={`flex items-center gap-2 font-semibold ${cat.head}`}>
                                        <span className={`h-2.5 w-2.5 rounded-full ${cat.dot}`} />
                                        {strings[`cat_${cat.key}`]}
                                    </h3>
                                    {data.categories[cat.key].length > 0 && (
                                        <div className="mt-3 grid grid-cols-2 gap-2">
                                            {data.categories[cat.key].map((item) => (
                                                <div key={item.key} className="rounded-md bg-gray-50 px-2.5 py-2 dark:bg-gray-900/40">
                                                    <div className={`text-2xl font-bold leading-none ${cat.head}`}>
                                                        {item.params.total ? `${item.params.count}/${item.params.total}` : item.params.count}
                                                    </div>
                                                    <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{strings[`kpi_${item.key}`]}</div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <div className="mt-4 space-y-3">
                                        {data.categories[cat.key].length === 0 && (
                                            <p className="text-sm text-gray-400 dark:text-gray-500">{strings.cat_empty}</p>
                                        )}
                                        {data.categories[cat.key].map((item) => (
                                            <div key={item.key}>
                                                <p className="text-sm text-gray-800 dark:text-gray-200">{itemText(item, strings)}</p>
                                                <Refs refs={item.refs} more={item.more} strings={strings} />
                                            </div>
                                        ))}
                                    </div>
                                    <Notes value={state.notes[cat.key] ?? ''} onChange={(v) => setNote(cat.key, v)} strings={strings} />
                                </div>
                            ))}
                        </div>
                    </section>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <section className={`${CARD} p-5`}>
                            <PanelHead title={strings.q_improve} sub={strings.improve_sub} />
                            {data.improve.length === 0 ? (
                                <p className="mt-3 text-sm text-gray-400 dark:text-gray-500">{strings.improve_empty}</p>
                            ) : (
                                <ul className="mt-3 list-disc space-y-1.5 ps-5 text-sm text-gray-800 dark:text-gray-200">
                                    {data.improve.map((item) => (
                                        <li key={item.key}>{interpolate(strings[`improve_${item.key}`], item.params)}</li>
                                    ))}
                                </ul>
                            )}
                            <Notes value={state.notes.improve ?? ''} onChange={(v) => setNote('improve', v)} strings={strings} />
                        </section>

                        <section className={`${CARD} p-5`}>
                            <PanelHead title={strings.q_learned} sub={strings.learned_sub} />
                            {learnedCount === 0 && <p className="mt-3 text-sm text-gray-400 dark:text-gray-500">{strings.learned_empty}</p>}
                            <div className="mt-3 space-y-3">
                                {LEARNED.filter((key) => data.learned[key].length > 0).map((key) => (
                                    <div key={key}>
                                        <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{strings[`learned_${key}`]}</h3>
                                        <Refs
                                            refs={data.learned[key].map((ref) => (ref.days ? { ...ref, title: `${ref.title} (${interpolate(strings.days, { days: ref.days })})` } : ref))}
                                            more={0}
                                            strings={strings}
                                        />
                                    </div>
                                ))}
                            </div>
                            <Notes value={state.notes.learned ?? ''} onChange={(v) => setNote('learned', v)} strings={strings} />
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

Retro.layout = AppShell;
