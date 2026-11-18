import React from 'react';

// Ersteller der PRs zu einem Jira-Ticket, je Person gruppiert: Jira-Avatar (sonst
// Initialen), Name und die PR-Nummern. `compact` lässt den Namen weg (Tooltip).
export default function PrAuthors({ prs, compact = false, className = '' }) {
    if (!prs?.length) return null;

    const authors = [];
    prs.forEach((pr) => {
        const key = pr.author ?? '';
        let entry = authors.find((a) => a.key === key);
        if (!entry) {
            entry = { key, author: pr.author, avatar: pr.avatar, initials: pr.initials, prs: [] };
            authors.push(entry);
        }
        entry.prs.push(pr);
    });

    return (
        <span className={`inline-flex flex-wrap items-center gap-x-3 gap-y-1 ${className}`}>
            {authors.map((entry) => (
                <span key={entry.key} className="inline-flex min-w-0 max-w-full flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-gray-600 dark:text-gray-400" title={entry.author ?? ''}>
                    {entry.avatar ? (
                        <img src={entry.avatar} alt="" className="h-5 w-5 shrink-0 rounded-full ring-1 ring-gray-200 dark:ring-gray-600" loading="lazy" />
                    ) : (
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300" aria-hidden="true">
                            {entry.initials}
                        </span>
                    )}
                    {!compact && <span className="font-medium">{entry.author}</span>}
                    {entry.prs.map((pr) => (
                        <a key={pr.url} href={pr.url} className="font-mono hover:text-indigo-600 hover:underline dark:hover:text-indigo-400">
                            #{pr.number}
                        </a>
                    ))}
                </span>
            ))}
        </span>
    );
}
