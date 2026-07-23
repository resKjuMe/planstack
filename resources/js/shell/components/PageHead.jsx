import React, { useState } from 'react';

// Einheitlicher Seitenkopf (React-Pendant zu components/page-head.blade.php):
// H1 links, optional Meta + „?"-Hilfebutton rechts; die Infobox klappt darunter
// auf. Der frühere Alpine-Zustand (x-data help) ist jetzt React-State.
export default function PageHead({ title, toggleLabel, bullets = [], meta = null, children }) {
    const [help, setHelp] = useState(false);
    const hasHelp = (bullets && bullets.length > 0) || !!children;

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{title}</h1>
                <div className="flex items-center gap-3">
                    {meta}
                    {hasHelp && (
                        <button
                            type="button"
                            onClick={() => setHelp((v) => !v)}
                            aria-expanded={help}
                            title={toggleLabel}
                            className="text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400"
                        >
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                                <path d="M12 17h.01" />
                            </svg>
                        </button>
                    )}
                </div>
            </div>

            {hasHelp && help && (
                <div className="mt-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    {children || (
                        <ul className="list-disc space-y-1 ps-4">
                            {bullets.map((b, i) => (
                                <li key={i}>
                                    {b.strong && <span className="font-medium">{b.strong}</span>}
                                    {b.strong ? ': ' : ''}
                                    {b.text}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
