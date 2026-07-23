import React, { useState } from 'react';
import PageHead from '../components/PageHead.jsx';
import { useChangelog } from '../../changelog/useChangelog.js';
import { interpolate } from '../../summary/i18n.js';

// Ein Headline-Segment (text / tag / status-Badge / Zitat).
function Segment({ seg }) {
    switch (seg.t) {
        case 'tag':
            return <span className="font-mono font-medium text-indigo-600 dark:text-indigo-400">{seg.v}</span>;
        case 'status':
            return <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + (seg.cls || '')}>{seg.v}</span>;
        case 'quote':
            return <span className="text-gray-500 dark:text-gray-400">„{seg.v}"</span>;
        default:
            return <>{seg.v}</>;
    }
}

// Eine Diff-Sektion mit sichtbaren + (aufklappbaren) weiteren Feldern.
function Section({ section, strings }) {
    const [moreOpen, setMoreOpen] = useState(false);
    const rows = moreOpen ? [...section.visible, ...section.hidden] : section.visible;

    return (
        <div>
            {section.label && <div className="mb-1 text-xs font-medium text-gray-400 dark:text-gray-500">{section.label}</div>}
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-xs text-gray-400 dark:text-gray-500">
                        <th className="pr-4 py-1 font-medium">{strings.field}</th>
                        <th className="pr-4 py-1 font-medium">{strings.before}</th>
                        <th className="py-1 font-medium">{strings.after}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i} className="align-top">
                            <td className="pr-4 py-1 whitespace-nowrap text-gray-500 dark:text-gray-400">{row.field}</td>
                            <td className="pr-4 py-1 text-gray-600 dark:text-gray-400">{row.old ?? '—'}</td>
                            <td className="py-1 font-medium text-gray-800 dark:text-gray-100">{row.new ?? '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {section.hidden.length > 0 && (
                <button type="button" onClick={() => setMoreOpen((v) => !v)} className="mt-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                    {moreOpen ? strings.showLess : interpolate(strings.countMoreFields, { count: section.hidden.length })}
                </button>
            )}
        </div>
    );
}

// Ein Changelog-Eintrag: aufklappbare Karte (Kopfzeile + Diff-Sektionen).
function Entry({ entry, strings }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-xl bg-white dark:bg-gray-800 p-3 ring-1 ring-gray-200 dark:ring-gray-700">
            <button type="button" onClick={() => setOpen((v) => !v)} className="flex w-full items-center gap-3 text-left">
                <span className="w-10 shrink-0 text-xs text-gray-400 dark:text-gray-500">{entry.timeLabel}</span>
                <span className="flex-1 text-sm text-gray-800 dark:text-gray-100">
                    {entry.headline.map((seg, i) => (
                        <Segment key={i} seg={seg} />
                    ))}
                </span>
                <span className="shrink-0 text-xs text-gray-400 dark:text-gray-500">{entry.causerShort}</span>
                <svg className={'h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500 transition-transform ' + (open ? 'rotate-90' : '')} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6" /></svg>
            </button>

            {open && (
                <div className="mt-3 space-y-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                    {entry.sections.map((section, i) => (
                        <Section key={i} section={section} strings={strings} />
                    ))}
                </div>
            )}
        </div>
    );
}

// Changelog als Teilansicht des ProjectWorkspace. Feed kommt gecacht + paginiert
// über GET /api/projects/{alias}/changelog (useChangelog); Live-Refresh von Seite 1
// bei entity-changed. Aufklappen/Mehr-laden sind clientseitiger React-State.
export default function ChangelogView({ project, strings }) {
    const { items, status, error, hasMore, loadingMore, loadMore } = useChangelog(project.alias);

    let lastDate = null;

    return (
        <div className="space-y-4">
            <PageHead title={strings.title} toggleLabel={strings.showHideExplanation} bullets={strings.helpBullets} />

            {status === 'loading' && <p className="text-sm text-gray-400 dark:text-gray-500">{strings.loading}</p>}
            {status === 'error' && <p className="text-sm text-red-600 dark:text-red-400">{error || 'Fehler'}</p>}

            {status === 'ready' && items.length === 0 && (
                <p className="p-6 text-sm text-gray-400 dark:text-gray-500">{strings.noChanges}</p>
            )}

            {items.length > 0 && (
                <div className="space-y-2">
                    {items.map((entry, i) => {
                        const showDate = entry.dateLabel !== lastDate;
                        lastDate = entry.dateLabel;
                        return (
                            <React.Fragment key={i}>
                                {showDate && (
                                    <div className={(i === 0 ? '' : 'pt-4 ') + 'text-xs font-medium text-gray-400 dark:text-gray-500'}>{entry.dateLabel}</div>
                                )}
                                <Entry entry={entry} strings={strings} />
                            </React.Fragment>
                        );
                    })}
                </div>
            )}

            {hasMore && (
                <div className="pt-2">
                    <button
                        type="button"
                        onClick={loadMore}
                        disabled={loadingMore}
                        className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 disabled:opacity-50"
                    >
                        {strings.loadMore}
                    </button>
                </div>
            )}
        </div>
    );
}
