import React, { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import Flash from '../components/Flash.jsx';
import { useProjectsList } from '../../projects/useProjectsList.js';
import { deriveProjectCards } from '../../projects/derive.js';

// Projektliste (ehemals projects/index.blade.php) als React-Inertia-Seite:
// Kopfzeile, Suche und Filter-Pills sind React-State. Die Projekte kommen über
// GET /api/projects, die restlichen Infos (Tasks) über GET /api/tasks; die Karten
// werden clientseitig abgeleitet und per entity-changed live aktualisiert
// (Project insert/update/delete sowie Task-/Phasen-Änderungen). Diese Seite liefert
// nur statische Props + i18n-Templates.
export default function ProjectsIndex({ currentUserId, filters, flash, strings }) {
    const { errors } = usePage().props;
    const { projects, tasks, statusConfig, status, error } = useProjectsList();
    const [q, setQ] = useState('');
    const [filter, setFilter] = useState('all');

    const { cards, summaryLine } = useMemo(() => {
        if (status !== 'ready' || !statusConfig) return { cards: [], summaryLine: '' };
        return deriveProjectCards({
            projects,
            tasks,
            statusConfig,
            currentUserId,
            strings,
            locale: (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de-DE',
        });
    }, [projects, tasks, statusConfig, status, currentUserId, strings]);

    const query = q.trim().toLowerCase();
    const visible = useMemo(
        () =>
            cards.filter((c) => {
                if ((filter === 'archived') !== c.archived) return false;
                const catMatch =
                    filter === 'archived' ||
                    filter === 'all' ||
                    (filter === 'mine' && c.mine) ||
                    filter === c.category;
                if (!catMatch) return false;
                return query === '' || c.searchText.includes(query);
            }),
        [cards, filter, query],
    );

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title}</h2>
                        <a
                            href={strings.createUrl}
                            className="inline-flex items-center whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            + {strings.newProject}
                        </a>
                    </div>
                }
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Flash status={flash?.status} error={flash?.error} errors={errors} />

                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">{summaryLine}</p>
                        <div className="relative">
                            <svg className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clipRule="evenodd" />
                            </svg>
                            <input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={strings.searchProjects}
                                className="w-64 rounded-md border-0 bg-white dark:bg-gray-800 py-2 pl-9 pr-3 text-sm text-gray-700 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {filters.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                onClick={() => setFilter(f.key)}
                                className={
                                    'rounded-full px-4 py-1.5 text-sm font-medium transition ' +
                                    (filter === f.key
                                        ? 'bg-gray-900 text-white'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50')
                                }
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>

                    {status === 'loading' && cards.length === 0 && (
                        <p className="mt-6 text-sm text-gray-400 dark:text-gray-500">{strings.loading}</p>
                    )}
                    {status === 'error' && (
                        <p className="mt-6 text-sm text-red-600 dark:text-red-400">{error || 'Fehler'}</p>
                    )}
                    {status === 'ready' && cards.length === 0 && (
                        <div className="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">
                            {strings.noProjects}
                        </div>
                    )}
                    {cards.length > 0 && (
                        <div className="mt-6 grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {visible.map((card) => (
                                <ProjectCard key={card.alias} card={card} strings={strings} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function ProjectCard({ card, strings }) {
    const [hover, setHover] = useState(null);

    const openProject = (e) => {
        if (!e.target.closest('a')) router.visit(card.diagramUrl);
    };

    return (
        <div className="h-full">
            <div
                onClick={openProject}
                className="flex h-full cursor-pointer flex-col rounded-lg bg-white dark:bg-gray-800 p-6 shadow transition hover:shadow-md"
            >
                <div className="flex items-center justify-between">
                    <span className="inline-flex items-center rounded bg-gray-800 dark:bg-gray-700 px-2 py-0.5 font-mono text-xs font-semibold text-white">{card.alias}</span>
                    <span className={'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ' + card.badgeClass}>{card.categoryLabel}</span>
                </div>

                <h3 className="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                    <a href={card.diagramUrl} className="hover:underline">{card.name}</a>
                </h3>
                {card.description && (
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2 whitespace-pre-line">{card.description}</p>
                )}

                <div className="mt-auto pt-5">
                    <div>
                        <div className="flex items-center justify-between text-sm">
                            <span className={hover ? hover.text : 'text-gray-500 dark:text-gray-400'}>
                                {hover ? `${hover.label} · ${hover.count} / ${card.tasksCount} ${strings.tasks}` : strings.progress}
                            </span>
                            <span className={'font-semibold ' + (hover ? hover.text : 'text-gray-900 dark:text-gray-100')}>
                                {hover ? `${hover.pct} % SP` : `${card.pctLabel} %`}
                            </span>
                        </div>
                        <div className="relative mt-1.5">
                            <div className="flex h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                {card.segments.map((seg, i) => (
                                    <div key={i} className={'h-full ' + seg.bar} style={{ width: `${seg.width}%` }}></div>
                                ))}
                            </div>
                            <div className="absolute inset-x-0 top-1/2 flex h-9 -translate-y-1/2">
                                {card.segments.map((seg, i) => (
                                    <div
                                        key={i}
                                        className="h-full"
                                        style={{ width: `${seg.width}%` }}
                                        onMouseEnter={() => setHover({ pct: seg.pctLabel, count: seg.count, text: seg.text, label: seg.label })}
                                        onMouseLeave={() => setHover(null)}
                                        title={`${seg.label}: ${seg.count}`}
                                    ></div>
                                ))}
                            </div>
                        </div>

                        {card.segments.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
                                {card.segments.map((seg, i) => (
                                    <span key={i} className={'inline-flex items-center rounded-full px-2 py-0.5 font-medium ' + seg.badge}>{seg.count} {seg.label}</span>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-2">
                        <div className="flex min-w-0 items-center gap-2">
                            <span className={'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white ' + card.avatarClass}>{card.initials}</span>
                            <div className="min-w-0">
                                <div className="truncate text-sm leading-4 text-gray-700 dark:text-gray-300">{card.ownerName}</div>
                                {card.teams.length > 0 && (
                                    <div className="truncate text-xs leading-none text-gray-400 dark:text-gray-500" title={card.teams.join(', ')}>{card.teams.join(', ')}</div>
                                )}
                            </div>
                        </div>
                        <span className="shrink-0 whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">{card.tasksLabel} · {card.sp} SP</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

ProjectsIndex.layout = AppShell;
