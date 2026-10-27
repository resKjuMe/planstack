import React, { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { DecisionBadge, FindingCounts, RunningBadge, ScoreBar, StateBadge, formatDate } from '../components/PullRequestParts.jsx';

// Eigene Pull Requests aus ps-review. Filter (Status, Rolle) laufen über den
// Server, die Sortierung clientseitig; Default ist der Handlungsbedarf.

const SORTS = {
    pr: (pr) => `${pr.repository}#${String(pr.number).padStart(8, '0')}`,
    author: (pr) => (pr.author ?? '').toLowerCase(),
    size: (pr) => pr.additions + pr.deletions,
    decision: (pr) => pr.review?.decision ?? null,
    findings: (pr) => (pr.review ? pr.review.critical * 10000 + pr.review.important * 100 + pr.review.hint : null),
    criticality: (pr) => pr.review?.criticality ?? null,
    aiReviewability: (pr) => pr.review?.aiReviewability ?? null,
    need: (pr) => pr.review?.needScore ?? null,
    reviewedAt: (pr) => pr.review?.reviewedAt ?? null,
};

// Ohne Review (null) immer ans Ende, egal in welche Richtung sortiert wird.
function compare(a, b, key, dir) {
    const va = SORTS[key](a);
    const vb = SORTS[key](b);
    if (va === null && vb === null) return 0;
    if (va === null) return 1;
    if (vb === null) return -1;
    const result = va < vb ? -1 : va > vb ? 1 : 0;
    return dir === 'asc' ? result : -result;
}

export default function PullRequestsIndex({ filters, filterUrls, pullRequests, strings }) {
    const [sort, setSort] = useState({ key: 'need', dir: 'desc' });
    const rows = useMemo(
        () => [...pullRequests].sort((a, b) => compare(a, b, sort.key, sort.dir)),
        [pullRequests, sort],
    );

    const toggleSort = (key) => setSort((current) => (
        current.key === key
            ? { key, dir: current.dir === 'desc' ? 'asc' : 'desc' }
            : { key, dir: ['pr', 'author', 'aiReviewability'].includes(key) ? 'asc' : 'desc' }
    ));

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title}</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">{strings.subtitle}</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Pills current={filters.role} urls={filterUrls.role} label={(r) => strings[`role_${r}`]} />
                            <Pills current={filters.state} urls={filterUrls.state} label={(s) => strings[`state_${s}`]} />
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {rows.length === 0 ? (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">
                            {strings.empty}
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/40 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <SortHeader id="pr" sort={sort} onSort={toggleSort}>{strings.col_pr}</SortHeader>
                                        <SortHeader id="author" sort={sort} onSort={toggleSort}>{strings.col_author}</SortHeader>
                                        <SortHeader id="size" sort={sort} onSort={toggleSort} align="right">{strings.col_size}</SortHeader>
                                        <SortHeader id="decision" sort={sort} onSort={toggleSort}>{strings.col_decision}</SortHeader>
                                        <SortHeader id="findings" sort={sort} onSort={toggleSort}>{strings.col_findings}</SortHeader>
                                        <SortHeader id="criticality" sort={sort} onSort={toggleSort}>{strings.col_criticality}</SortHeader>
                                        <SortHeader id="aiReviewability" sort={sort} onSort={toggleSort}>{strings.col_ai_reviewability}</SortHeader>
                                        <SortHeader id="need" sort={sort} onSort={toggleSort} title={strings.need_hint} align="right">{strings.col_need}</SortHeader>
                                        <SortHeader id="reviewedAt" sort={sort} onSort={toggleSort}>{strings.col_reviewed_at}</SortHeader>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {rows.map((pr) => <Row key={pr.id} pr={pr} strings={strings} />)}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function Pills({ current, urls, label }) {
    return (
        <div className="inline-flex rounded-md shadow-sm" role="group">
            {Object.entries(urls).map(([key, url], i, all) => (
                <Link
                    key={key}
                    href={url}
                    preserveScroll
                    className={[
                        'px-3 py-1.5 text-xs font-medium border border-gray-200 dark:border-gray-600',
                        i === 0 ? 'rounded-l-md' : '-ml-px',
                        i === all.length - 1 ? 'rounded-r-md' : '',
                        key === current
                            ? 'bg-indigo-600 border-indigo-600 text-white'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                    ].join(' ')}
                >
                    {label(key)}
                </Link>
            ))}
        </div>
    );
}

function SortHeader({ id, sort, onSort, children, title, align = 'left' }) {
    const active = sort.key === id;
    return (
        <th className={`px-3 py-2 font-medium whitespace-nowrap ${align === 'right' ? 'text-right' : ''}`} title={title}>
            <button type="button" onClick={() => onSort(id)} className={`uppercase tracking-wide ${active ? 'text-gray-900 dark:text-gray-100' : ''}`}>
                {children}{active ? (sort.dir === 'desc' ? ' ↓' : ' ↑') : ''}
            </button>
        </th>
    );
}

function Row({ pr, strings }) {
    const review = pr.review;
    return (
        <tr className="hover:bg-gray-50 dark:hover:bg-gray-700/40 align-top">
            <td className="px-3 py-2 max-w-md">
                <Link href={pr.showUrl} className="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                    {pr.title}
                </Link>
                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                    <a href={pr.githubUrl} target="_blank" rel="noreferrer" className="font-mono hover:underline">{pr.repository}#{pr.number}</a>
                    <span className="font-mono truncate max-w-[16rem]" title={`${pr.branch} → ${pr.targetBranch}`}>{pr.branch} → {pr.targetBranch}</span>
                    {pr.state !== 'OPEN' && <StateBadge state={pr.state} strings={strings} />}
                    {pr.running && <RunningBadge running={pr.running} strings={strings} />}
                </div>
            </td>
            <td className="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">{pr.author ?? '—'}</td>
            <td className="px-3 py-2 whitespace-nowrap text-right font-mono text-xs">
                <span className="text-green-600 dark:text-green-400">+{pr.additions}</span>{' '}
                <span className="text-red-600 dark:text-red-400">−{pr.deletions}</span>
                <div className="text-gray-400">{pr.changedFiles} {strings.files}</div>
            </td>
            {review ? (
                <>
                    <td className="px-3 py-2 whitespace-nowrap">
                        <DecisionBadge decision={review.decision} strings={strings} />
                        {review.stale && <div className="mt-0.5 text-xs text-amber-600 dark:text-amber-400">{strings.stale}</div>}
                    </td>
                    <td className="px-3 py-2 whitespace-nowrap"><FindingCounts review={review} /></td>
                    <td className="px-3 py-2"><ScoreBar value={review.criticality} title={review.criticalityReason} tone="risk" /></td>
                    <td className="px-3 py-2"><ScoreBar value={review.aiReviewability} title={review.aiReviewabilityReason} tone="ai" /></td>
                    <td className="px-3 py-2 text-right font-mono text-xs text-gray-700 dark:text-gray-300" title={strings.need_hint}>{Math.round(review.needScore / 100)}</td>
                    <td className="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{formatDate(review.reviewedAt)}</td>
                </>
            ) : (
                <td colSpan={6} className="px-3 py-2 text-xs text-gray-400 dark:text-gray-500">{pr.running ? '' : strings.not_reviewed}</td>
            )}
        </tr>
    );
}

PullRequestsIndex.layout = AppShell;
