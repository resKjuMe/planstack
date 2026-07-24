import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Nutzer-Changelog (ehemals changelog.blade.php + Alpine + Inline-Script). Releases
// kommen aufbereitet als Props; neue Releases (Version > zuletzt gesehen) werden
// per localStorage hervorgehoben, danach wird die gesehene Version gespeichert.
const SEEN_KEY = 'changelog-seen-version';

function cmpVersion(a, b) {
    const pa = String(a || '0').split('.').map(Number);
    const pb = String(b || '0').split('.').map(Number);
    for (let i = 0; i < 3; i++) {
        const d = (pa[i] || 0) - (pb[i] || 0);
        if (d) return d < 0 ? -1 : 1;
    }
    return 0;
}

export default function Changelog({ releases, latestVersion, strings }) {
    const [seen, setSeen] = useState(null);

    useEffect(() => {
        try {
            setSeen(localStorage.getItem(SEEN_KEY));
            localStorage.setItem(SEEN_KEY, latestVersion);
        } catch {
            /* localStorage nicht verfügbar → keine Hervorhebung */
        }
    }, [latestVersion]);

    return (
        <>
            <Head><title>{strings.whatsNew}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.whatsNew}</h2>}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
                    <p className="text-sm text-gray-500 dark:text-gray-400">{strings.intro}</p>

                    {releases.length === 0 && (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-sm text-gray-500 dark:text-gray-400">{strings.noEntries}</div>
                    )}

                    {releases.map((release) => (
                        <ReleaseCard
                            key={release.version}
                            release={release}
                            isNew={seen != null && cmpVersion(release.version, seen) > 0}
                            strings={strings}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function ReleaseCard({ release, isNew, strings }) {
    const [open, setOpen] = useState(false);

    return (
        <div className={'bg-white dark:bg-gray-800 rounded-lg shadow ' + (isNew ? 'ring-2 ring-indigo-400' : '')}>
            <button type="button" onClick={() => setOpen((v) => !v)} className="flex w-full items-center gap-3 px-6 py-4 text-left">
                <span className="shrink-0 inline-flex items-center rounded-md bg-indigo-600 px-2 py-0.5 text-sm font-mono font-semibold text-white">v{release.version}</span>
                {isNew && <span className="shrink-0 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:text-indigo-400">{strings.new}</span>}

                <span className="min-w-0 flex-1 truncate text-sm">
                    {release.tldr.map((kw, i) => (
                        <React.Fragment key={i}>
                            {i > 0 && <span className="text-gray-500 dark:text-gray-400">&nbsp;·&nbsp;</span>}
                            <span className="font-bold text-gray-900 dark:text-gray-100">{kw}</span>
                        </React.Fragment>
                    ))}
                </span>

                {release.date && <span className="shrink-0 text-xs text-gray-400 dark:text-gray-500">{release.date}</span>}
                <svg className={'shrink-0 h-4 w-4 text-gray-400 dark:text-gray-500 transition-transform ' + (open ? 'rotate-180' : '')} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
            </button>

            {open && (
                <div className="border-t border-gray-100 dark:border-gray-700 px-6 py-4">
                    <ul className="space-y-2">
                        {release.changes.map((change, i) => (
                            <li key={i} className="flex gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <svg className="mt-0.5 h-4 w-4 shrink-0 text-indigo-500 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                                <span>
                                    {change.isNew && <span className="me-1.5 inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900/40 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-300 align-[1px]">{strings.new}</span>}
                                    {change.text}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

Changelog.layout = AppShell;
