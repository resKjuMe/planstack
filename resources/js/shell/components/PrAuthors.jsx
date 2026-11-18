import React from 'react';

// Ersteller der PRs zu einem Jira-Ticket: Jira-Avatar (sonst Initialen), Name
// und PR-Nummer. `compact` zeigt nur die Avatare (Name im Tooltip).
export default function PrAuthors({ prs, compact = false, className = '' }) {
    if (!prs?.length) return null;

    return (
        <span className={`inline-flex flex-wrap items-center gap-x-3 gap-y-1 ${className}`}>
            {prs.map((pr) => (
                <a
                    key={pr.url}
                    href={pr.url}
                    title={`${pr.author ?? ''} · #${pr.number}`}
                    className="inline-flex items-center gap-1.5 text-xs text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                >
                    {pr.avatar ? (
                        <img src={pr.avatar} alt="" className="h-5 w-5 shrink-0 rounded-full ring-1 ring-gray-200 dark:ring-gray-600" loading="lazy" />
                    ) : (
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300" aria-hidden="true">
                            {pr.initials}
                        </span>
                    )}
                    {!compact && <span className="font-medium">{pr.author}</span>}
                    <span className="font-mono">#{pr.number}</span>
                </a>
            ))}
        </span>
    );
}
