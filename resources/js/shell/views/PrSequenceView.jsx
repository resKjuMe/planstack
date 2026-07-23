import React, { useMemo, useState } from 'react';
import PageHead from '../components/PageHead.jsx';
import { useProjectData } from '../../data/useProjectData';
import { deriveSequence } from '../../prsequence/derive.js';
import { interpolate, transChoice } from '../../summary/i18n.js';
import { KpiTilesSkeleton, ChipsSkeleton, CardsSkeleton } from '../components/Skeleton.jsx';

// Inline-Icons (Tabler Outline, 24er-ViewBox), Spiegel des $ic-Helfers der Blade.
const ICONS = {
    list: '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M5 6v.01"/><path d="M5 12v.01"/><path d="M5 18v.01"/>',
    play: '<path d="M7 4v16l13 -8z"/>',
    lock: '<path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/>',
    alert: '<path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/>',
    hand: '<path d="M8 13v-7.5a1.5 1.5 0 0 1 3 0v6.5"/><path d="M11 5.5v-2a1.5 1.5 0 1 1 3 0v8.5"/><path d="M14 5.5a1.5 1.5 0 0 1 3 0v6.5"/><path d="M17 7.5a1.5 1.5 0 0 1 3 0v8.5a6 6 0 0 1 -6 6h-2h.208a6 6 0 0 1 -5.012 -2.7a69.74 69.74 0 0 1 -.196 -.3c-.312 -.479 -1.407 -2.388 -3.286 -5.728a1.5 1.5 0 0 1 .536 -2.022a1.867 1.867 0 0 1 2.28 .28l1.47 1.47"/>',
    flame: '<path d="M12 12c2 -2.96 0 -7 -1 -8c0 3.038 -1.773 4.741 -3 6c-1.226 1.26 -2 3.24 -2 5a6 6 0 1 0 12 0c0 -1.532 -1.056 -3.94 -2 -5c-1.786 3 -2.791 3 -4 2z"/>',
    check: '<path d="M5 12l5 5l10 -10"/>',
    clock: '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 7v5l3 3"/>',
    chart: '<path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/><path d="M4 20h14"/>',
    coin: '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1"/><path d="M12 7v10"/>',
    file: '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>',
    expand: '<path d="M16 4l4 0l0 4"/><path d="M14 10l6 -6"/><path d="M8 20l-4 0l0 -4"/><path d="M4 20l6 -6"/>',
    chevron: '<path d="M9 6l6 6l-6 6"/>',
};

const CLAUDE_LOGO =
    'M4.709 15.955l4.72-2.647.08-.23-.08-.128H9.2l-.79-.048-2.698-.073-2.339-.097-2.266-.122-.571-.121L0 11.784l.055-.352.48-.321.686.06 1.52.103 2.278.158 1.652.097 2.449.255h.389l.055-.157-.134-.098-.103-.097-2.358-1.596-2.552-1.688-1.336-.972-.724-.491-.364-.462-.158-1.008.656-.722.881.06.225.061.893.686 1.908 1.476 2.491 1.833.365.304.145-.103.019-.073-.164-.274-1.355-2.446-1.446-2.49-.644-1.032-.17-.619a2.97 2.97 0 01-.104-.729L6.283.134 6.696 0l.996.134.42.364.62 1.414 1.002 2.229 1.555 3.03.456.898.243.832.091.255h.158V9.01l.128-1.706.237-2.095.23-2.695.08-.76.376-.91.747-.492.583.28.48.685-.067.444-.286 1.851-.559 2.903-.364 1.942h.212l.243-.242.985-1.306 1.652-2.064.73-.82.85-.904.547-.431h1.033l.76 1.129-.34 1.166-1.064 1.347-.881 1.142-1.264 1.7-.79 1.36.073.11.188-.02 2.856-.606 1.543-.28 1.841-.315.833.388.091.395-.328.807-1.969.486-2.309.462-3.439.813-.042.03.049.061 1.549.146.662.036h1.622l3.02.225.79.522.474.638-.079.485-1.215.62-1.64-.389-3.829-.91-1.312-.329h-.182v.11l1.093 1.068 2.006 1.81 2.509 2.33.127.578-.322.455-.34-.049-2.205-1.657-.851-.747-1.926-1.62h-.128v.17l.444.649 2.345 3.521.122 1.08-.17.353-.608.213-.668-.122-1.374-1.925-1.415-2.167-1.143-1.943-.14.08-.674 7.254-.316.37-.729.28-.607-.461-.322-.747.322-1.476.389-1.924.315-1.53.286-1.9.17-.632-.012-.042-.14.018-1.434 1.967-2.18 2.945-1.726 1.845-.414.164-.717-.37.067-.662.401-.589 2.388-3.036 1.44-1.882.93-1.086-.006-.158h-.055L4.132 18.56l-1.13.146-.487-.456.061-.746.231-.243 1.908-1.312-.006.006z';

function Ic({ name, className = 'h-3.5 w-3.5' }) {
    return (
        <svg
            className={className + ' shrink-0'}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: ICONS[name] }}
        />
    );
}

function SeqRow({ row, strings, inCollapse }) {
    return (
        <div className={'relative px-4 py-3 ' + (row.isActive ? 'bg-[var(--seq-active-row)]' : '')} {...(inCollapse ? {} : {})}>
            <span aria-hidden="true" className={'absolute inset-y-0 left-0 w-[3px] ' + (row.rail || '')}></span>

            <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5 min-w-0">
                <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + (row.badge || '')}>{row.statusLabel}</span>

                <a href={row.url} className="font-mono text-sm font-medium text-[var(--seq-accent)] hover:underline">{row.name}</a>

                <a
                    href={row.claudeHref}
                    onClick={(e) => e.stopPropagation()}
                    title={interpolate(strings.workWithClaude, { name: row.name })}
                    className="inline-flex items-center justify-center rounded-full p-1 text-[#D97757] hover:bg-[var(--seq-amber-tint)]"
                >
                    <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" fill="currentColor" aria-hidden="true"><path d={CLAUDE_LOGO} /></svg>
                </a>

                <span className="text-[11px] text-[var(--seq-faint)]">
                    {row.pr && (
                        <>
                            {row.prUrl ? (
                                <a href={row.prUrl} target="_blank" rel="noopener" className="hover:underline">#{row.pr}</a>
                            ) : (
                                <>#{row.pr}</>
                            )}
                            {' · '}
                        </>
                    )}
                    {row.phaseShort} · {strings.pos} {row.seq}
                </span>

                {row.isBottleneck && (
                    <span
                        className="inline-flex items-center gap-1 rounded-full bg-[var(--seq-red-tint)] px-2 py-0.5 text-[11px] font-medium text-[var(--seq-red-text)]"
                        title={interpolate(strings.dependDirectly, { count: row.dependents })}
                    >
                        <Ic name="flame" className="h-3 w-3" /> {strings.bottleneck} · {transChoice(strings.blocksPrs, row.dependents, { count: row.dependents })}
                    </span>
                )}

                {row.big && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[var(--seq-amber-tint)] px-2 py-0.5 text-[11px] font-medium text-[var(--seq-amber-text)]">
                        <Ic name="expand" className="h-3 w-3" />{' '}
                        {row.big.isLargest ? strings.largestPr : strings.largePr}: {row.big.sp >= 10 ? row.big.sp + ' SP' : (row.big.files ?? 0) + ' ' + strings.files}
                    </span>
                )}
            </div>

            <p className="mt-1.5 text-sm text-[var(--seq-muted)] whitespace-normal break-words">{row.summary}</p>

            {row.claimer && (
                <p className="mt-1 text-xs text-[var(--seq-sky-text)]">
                    {strings.claimedBy} <span className="font-medium">{row.claimer}</span>
                    {row.claimedAgo ? ` · ${strings.since} ${row.claimedAgo}` : ''}
                </p>
            )}

            {row.reason && (
                <p className="mt-1 inline-flex items-start gap-1 text-sm text-[var(--seq-red-text)]"><Ic name="alert" className="mt-0.5 h-3.5 w-3.5" /> {row.reason}</p>
            )}

            <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5">
                {row.depOpen >= 1 && (
                    <>
                        <span className="text-xs text-[var(--seq-faint)]">{strings.waitingOn}</span>
                        {row.depItems.map((dep, i) =>
                            dep.met ? (
                                <span key={i} className="inline-flex items-center gap-1 rounded-md border-[0.5px] border-transparent bg-[var(--seq-green-tint)] px-1.5 py-0.5 font-mono text-xs text-[var(--seq-green-text)]"><Ic name="check" className="h-3 w-3" />{dep.name}</span>
                            ) : (
                                <span key={i} className="inline-flex items-center gap-1 rounded-md border-[0.5px] border-[var(--seq-border)] px-1.5 py-0.5 font-mono text-xs text-[var(--seq-muted)]"><Ic name="clock" className="h-3 w-3" />{dep.name}</span>
                            ),
                        )}
                    </>
                )}
                <span className="ml-auto inline-flex items-center gap-3 whitespace-nowrap text-xs text-[var(--seq-muted)]">
                    <span className="inline-flex items-center gap-1"><Ic name="chart" className="h-3.5 w-3.5" />{row.sp} SP</span>
                    <span className="inline-flex items-center gap-1"><Ic name="coin" className="h-3.5 w-3.5" />{row.tokens} {strings.tokens}</span>
                    <span className="inline-flex items-center gap-1"><Ic name="file" className="h-3.5 w-3.5" />{row.files ?? '—'} {strings.files}</span>
                </span>
            </div>
        </div>
    );
}

// PR-Sequenz als Teilansicht des ProjectWorkspace. Daten aus dem geteilten Store,
// clientseitig abgeleitet (prsequence/derive.js) und live über entity-changed
// aktualisiert. Filter/Einklappen sind rein clientseitiger React-State.
export default function PrSequenceView({ project, strings }) {
    const { tasks, statusConfig, status, error } = useProjectData(project.alias);

    const data = useMemo(() => {
        if (status !== 'ready' || !statusConfig) return null;
        return deriveSequence({
            tasks,
            statusConfig,
            taskUrlTemplate: project.taskUrlTemplate,
            locale: (typeof document !== 'undefined' && document.documentElement.getAttribute('lang')) || 'de',
        });
    }, [tasks, statusConfig, status, project.taskUrlTemplate]);

    const [filter, setFilter] = useState('all');
    const readPref = (store, key) => {
        try {
            return store.getItem(key) === '1';
        } catch {
            return false;
        }
    };
    const [doneOpen, setDoneOpen] = useState(() => readPref(window.localStorage, 'ps-seq-done-open'));
    const [blockedOpen, setBlockedOpen] = useState(() => readPref(window.sessionStorage, 'ps-seq-blocked-open'));

    const persist = (store, key, value, setter) => {
        setter(value);
        try {
            store.setItem(key, value ? '1' : '0');
        } catch {
            /* ignore */
        }
    };

    const chips = data
        ? [
              { key: 'all', label: strings.all, icon: 'list', count: data.counts.all },
              { key: 'pickable', label: strings.pickable, icon: 'play', count: data.counts.pickable },
              { key: 'blocked', label: strings.blocks, icon: 'lock', count: data.counts.blocked },
              { key: 'concerned', label: strings.concerns, icon: 'alert', count: data.counts.concerned },
              { key: 'claimed', label: strings.claimed, icon: 'hand', count: data.counts.claimed },
          ]
        : [];

    const rowVisible = (cat) => filter === 'all' || filter === cat;
    const collapsedVisible = (filter === 'all' && blockedOpen) || filter === 'blocked';

    return (
        <div className="space-y-4">
            <PageHead title={strings.title} toggleLabel={strings.showHideExplanation} bullets={strings.helpBullets} />

            {status !== 'ready' && status !== 'error' && (
                <>
                    <KpiTilesSkeleton count={4} />
                    <ChipsSkeleton count={5} />
                    <CardsSkeleton count={5} cols={1} />
                </>
            )}
            {status === 'error' && <p className="text-sm text-red-600 dark:text-red-400">{error || 'Fehler'}</p>}

            {data && (
                <>
                    {/* Kennzahlen */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.openPrs}</div>
                            <div className="mt-1 text-[22px] font-semibold leading-tight text-gray-900 dark:text-gray-100">{data.metrics.openCount}</div>
                        </div>
                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.totalStoryPoints}</div>
                            <div className="mt-1 text-[22px] font-semibold leading-tight text-gray-900 dark:text-gray-100">{data.metrics.totalSp}</div>
                        </div>
                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.blocks}</div>
                            <div className="mt-1 text-[22px] font-semibold leading-tight text-red-600 dark:text-red-400">{data.metrics.blockedCount}</div>
                        </div>
                        <div className="rounded-lg bg-white dark:bg-gray-800 p-4 ring-1 ring-gray-200 dark:ring-gray-700">
                            <div className="text-xs font-medium text-gray-400 dark:text-gray-500">{strings.criticalPath}</div>
                            <div className="mt-1 break-words font-mono text-[15px] font-medium leading-snug text-gray-900 dark:text-gray-100">{data.metrics.criticalPath}</div>
                        </div>
                    </div>

                    {/* Filter-Pills */}
                    <div className="inline-flex flex-wrap items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 p-1">
                        {chips.map((chip) => {
                            const active = filter === chip.key;
                            return (
                                <button
                                    key={chip.key}
                                    type="button"
                                    onClick={() => setFilter(chip.key)}
                                    className={
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium transition-colors ' +
                                        (active
                                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                                    }
                                >
                                    <Ic name={chip.icon} className="h-3.5 w-3.5" />
                                    {chip.label}
                                    <span className={'inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1 text-[11px] font-medium ' + (active ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300' : 'bg-white text-gray-400 dark:bg-gray-700 dark:text-gray-400')}>{chip.count}</span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Liste */}
                    <div className="divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                        {data.main.length === 0 && (
                            <p className="px-4 py-6 text-sm text-[var(--seq-faint)]">{strings.noOpenPrs}</p>
                        )}
                        {data.main.filter((r) => rowVisible(r.cat)).map((r) => (
                            <SeqRow key={r.id} row={r} strings={strings} />
                        ))}

                        {data.collapseBlocked && filter === 'all' && (
                            <button
                                type="button"
                                onClick={() => persist(window.sessionStorage, 'ps-seq-blocked-open', !blockedOpen, setBlockedOpen)}
                                className="flex w-full items-center gap-2 px-4 py-3 text-xs font-medium text-[var(--seq-muted)] hover:text-[var(--seq-text)]"
                            >
                                <svg className={'h-3.5 w-3.5 shrink-0 transition-transform ' + (blockedOpen ? 'rotate-90' : '')} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6" /></svg>
                                {blockedOpen
                                    ? interpolate(strings.hideMoreBlocked, { count: data.blockedPlainCount })
                                    : interpolate(strings.showMoreBlocked, { count: data.blockedPlainCount })}
                            </button>
                        )}
                        {collapsedVisible && data.blockedCollapsed.map((r) => (
                            <SeqRow key={r.id} row={r} strings={strings} inCollapse />
                        ))}

                        {data.counts.all > 0 && filter !== 'all' && data.counts[filter] === 0 && (
                            <p className="px-4 py-6 text-sm text-[var(--seq-faint)]">{strings.noPrsInFilter}</p>
                        )}
                    </div>

                    {/* Abgeschlossene PRs */}
                    {data.completed.length > 0 && filter === 'all' && (
                        <div>
                            <button
                                type="button"
                                onClick={() => persist(window.localStorage, 'ps-seq-done-open', !doneOpen, setDoneOpen)}
                                className="flex items-center gap-2 text-sm font-medium text-[var(--seq-muted)] hover:text-[var(--seq-text)]"
                            >
                                <svg className={'h-4 w-4 transition-transform ' + (doneOpen ? 'rotate-90' : '')} viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" /></svg>
                                {transChoice(strings.completedPrs, data.completed.length, { count: data.completed.length })}
                            </button>
                            {doneOpen && (
                                <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1.5">
                                    {data.completed.map((t) => (
                                        <a key={t.name} href={t.url} className="inline-flex items-center gap-1 font-mono text-xs text-[var(--seq-muted)] hover:text-[var(--seq-text)] hover:underline">
                                            {t.name}
                                            {t.pr && <span className="text-[var(--seq-faint)]">#{t.pr}</span>}
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
