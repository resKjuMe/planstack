import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import { useNow } from '../../board/useNow.js';
import Flash from '../components/Flash.jsx';
import PageBands from '../components/PageBands.jsx';
import { ApprovalChips, ColumnIcon, StackedIcon, DecisionIcon, FindingCounts, PoBadge, RunningDot, ScoreBar, StateBadge, formatDate, formatRelative, ReviewStartLink } from '../components/PullRequestParts.jsx';

// Eigene Pull Requests aus ps-review. Filter (Status, Rolle) laufen über den
// Server, Aufteilung und Sortierung clientseitig. Gruppen: laufende Reviews, was
// ein Review braucht (keins, oder seit dem letzten gepusht), empfohlene, aber
// noch nicht von mir approvte, Reviewtes, schon ausreichend Approvtes. Default ist der Titel; gestapelte PRs hängen dann
// eingerückt unter ihrem Parent.

const byTitle = (a, b) => (a.title ?? '').localeCompare(b.title ?? '', undefined, { numeric: true, sensitivity: 'base' });

const SORTS = {
    title: (pr) => (pr.title ?? '').toLowerCase(),
    author: (pr) => (pr.author ?? '').toLowerCase(),
    size: (pr) => pr.additions + pr.deletions,
    // Offene Freigaben zuerst (aufsteigend): vollständig ans Ende, dann nach Zahl.
    approvals: (pr) => (pr.approvals.fulfilled ? 1000 : 0) + pr.approvals.approved - pr.approvals.changesRequested * 10,
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

// Parent ist der PR im selben Repo, dessen Branch das Ziel dieses PR ist (wie im
// Tampermonkey-Skript git-colors). Liegt der Parent nicht in der Liste, ist der
// PR selbst Wurzel. Ergebnis: Zeilen in Baumreihenfolge mit Einrücktiefe.
function asTree(list, dir) {
    const sign = dir === 'desc' ? -1 : 1;
    const byBranch = new Map(list.map((pr) => [`${pr.repository}:${pr.branch}`, pr]));
    const children = new Map(list.map((pr) => [pr.id, []]));
    const roots = [];

    list.forEach((pr) => {
        const parent = pr.targetBranch !== 'main' ? byBranch.get(`${pr.repository}:${pr.targetBranch}`) : null;
        if (parent && parent !== pr) children.get(parent.id).push(pr);
        else roots.push(pr);
    });

    const rows = [];
    const seen = new Set();
    const walk = (pr, level) => {
        if (seen.has(pr.id)) return; // Zyklus (A → B → A) nicht endlos verfolgen
        seen.add(pr.id);
        rows.push({ pr, level });
        children.get(pr.id).sort((a, b) => sign * byTitle(a, b)).forEach((child) => walk(child, level + 1));
    };
    roots.sort((a, b) => sign * byTitle(a, b)).forEach((pr) => walk(pr, 0));
    // PRs, die nur in einem Zyklus hängen, haben keine Wurzel: ans Ende.
    list.filter((pr) => !seen.has(pr.id)).sort(byTitle).forEach((pr) => walk(pr, 0));

    return rows;
}

function arrange(list, sort) {
    return sort.key === 'title'
        ? asTree(list, sort.dir)
        : [...list].sort((a, b) => compare(a, b, sort.key, sort.dir)).map((pr) => ({ pr, level: 0 }));
}

// Ein laufendes Review geht vor, danach ausreichend approvt: dann braucht es
// kein weiteres Review.
const section = (pr) => {
    if (pr.running) return 'running';
    if (pr.approvals.fulfilled) return 'approved';
    if (!pr.review || pr.review.stale) return 'needs_review';
    // Approve empfohlen, auf GitHub aber noch nichts von mir (eigene PRs ausgenommen).
    if (pr.review.decision === 'APPROVE' && !pr.mine.githubDecision && !pr.mine.isAuthor) return 'approve_pending';
    return 'reviewed';
};

export default function PullRequestsIndex({ filters, filterUrls, syncUrl, skillDownloadUrl, flash, pullRequests, strings }) {
    const [sort, setSort] = useState({ key: 'title', dir: 'asc' });
    const [syncing, setSyncing] = useState(false);
    const sync = () => router.post(syncUrl, {}, {
        preserveScroll: true,
        onStart: () => setSyncing(true),
        onFinish: () => setSyncing(false),
    });
    const sections = useMemo(() => [
        ...['running', 'needs_review', 'approve_pending', 'reviewed', 'approved'].map((key) => ({
            key,
            rows: arrange(pullRequests.filter((pr) => section(pr) === key), sort),
        })),
    ].filter((section) => section.rows.length > 0), [pullRequests, sort]);

    const toggleSort = (key) => setSort((current) => (
        current.key === key
            ? { key, dir: current.dir === 'desc' ? 'asc' : 'desc' }
            : { key, dir: ['title', 'author', 'aiReviewability', 'approvals'].includes(key) ? 'asc' : 'desc' }
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
                        <div className="flex flex-wrap items-center gap-2">
                            <Pills current={filters.role} urls={filterUrls.role} label={(r) => (r === filters.role ? <>{strings[`role_${r}`]} <span className="opacity-75">({pullRequests.length})</span></> : strings[`role_${r}`])} />
                            <Pills current={filters.state} urls={filterUrls.state} label={(s) => strings[`state_${s}`]} />
                            <button
                                type="button"
                                onClick={sync}
                                disabled={syncing}
                                title={strings[`sync_hint_${filters.role}`]}
                                className="inline-flex items-center gap-1.5 rounded-md bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-60 disabled:cursor-wait"
                            >
                                <svg className={`h-3.5 w-3.5 text-gray-500 dark:text-gray-400 ${syncing ? 'animate-spin' : ''}`} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.311h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clipRule="evenodd" />
                                </svg>
                                {syncing ? strings.syncing : strings.sync}
                            </button>
                            {/* Kein Inertia-Link: der Download braucht eine normale Navigation. */}
                            <a
                                href={skillDownloadUrl}
                                title={strings.skill_download_hint}
                                className="inline-flex items-center gap-1.5 rounded-md bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <svg className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <path d="M12 3v12M7 10l5 5 5-5M5 21h14" />
                                </svg>
                                {strings.skill_download}
                            </a>
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Flash status={flash?.status} error={flash?.error} />
                    {pullRequests.length === 0 ? (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">
                            {strings.empty}
                        </div>
                    ) : (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/40 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <SortHeader id="title" sort={sort} onSort={toggleSort}>{strings.col_pr}</SortHeader>
                                        <SortHeader id="author" sort={sort} onSort={toggleSort}>{strings.col_author}</SortHeader>
                                        <SortHeader id="size" sort={sort} onSort={toggleSort} align="right">{strings.col_size}</SortHeader>
                                        <SortHeader id="approvals" sort={sort} onSort={toggleSort} title={strings.approvals_hint}>{strings.approvals}</SortHeader>
                                        <SortHeader id="decision" sort={sort} onSort={toggleSort} title={strings.col_decision}><ColumnIcon name="decision" /></SortHeader>
                                        <SortHeader id="findings" sort={sort} onSort={toggleSort} title={`${strings.findings}: ${strings.level_CRITICAL} / ${strings.level_IMPORTANT} / ${strings.level_HINT}`}><ColumnIcon name="findings" /></SortHeader>
                                        <SortHeader id="criticality" sort={sort} onSort={toggleSort} title={strings.col_criticality}><ColumnIcon name="criticality" /></SortHeader>
                                        <SortHeader id="aiReviewability" sort={sort} onSort={toggleSort} title={strings.col_ai_reviewability}><ColumnIcon name="aiReviewability" /></SortHeader>
                                        <SortHeader id="need" sort={sort} onSort={toggleSort} title={`${strings.col_need}: ${strings.need_hint}`} align="right"><ColumnIcon name="need" /></SortHeader>
                                        <SortHeader id="reviewedAt" sort={sort} onSort={toggleSort} title={strings.col_reviewed_at}><ColumnIcon name="reviewedAt" /></SortHeader>
                                    </tr>
                                </thead>
                                {sections.map((section) => (
                                    <tbody key={section.key} className="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr className="bg-gray-50/60 dark:bg-gray-900/20">
                                            <th colSpan={10} className="px-2 pt-4 pb-1.5 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">
                                                {strings[`section_${section.key}`]} <span className="font-normal text-gray-400">({section.rows.length})</span>
                                            </th>
                                        </tr>
                                        {section.rows.map(({ pr, level }) => <Row key={pr.id} pr={pr} level={level} canShare={filters.role === 'authored'} strings={strings} />)}
                                    </tbody>
                                ))}
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
        <th className={`px-2 py-2 font-medium whitespace-nowrap ${align === 'right' ? 'text-right' : ''}`} title={title}>
            <button type="button" onClick={() => onSort(id)} className={`uppercase tracking-wide ${active ? 'text-gray-900 dark:text-gray-100' : ''}`}>
                {children}{active ? (sort.dir === 'desc' ? ' ↓' : ' ↑') : ''}
            </button>
        </th>
    );
}

// Eine Zeile je PR; Nebeninfos (Repo, Dateien, Begründungen) stehen im Tooltip.
function Row({ pr, level, canShare, strings }) {
    const review = pr.review;
    const now = useNow();
    const cell = 'px-2 py-1.5 whitespace-nowrap';
    return (
        <tr className="hover:bg-gray-50 dark:hover:bg-gray-700/40">
            <td className={`${cell} max-w-xs`} style={level > 0 ? { paddingLeft: `calc(0.5rem + ${level * 30}px)` } : undefined}>
                <div className="flex items-center gap-2 min-w-0">
                    {canShare && <ShareToggle pr={pr} strings={strings} />}
                    <a href={pr.githubUrl} target="_blank" rel="noreferrer" className="shrink-0 font-mono text-xs text-gray-500 dark:text-gray-400 hover:underline" title={`${pr.repository}#${pr.number}`}>{pr.number}</a>
                    <StackedIcon pr={pr} strings={strings} />
                    <Link href={pr.showUrl} title={pr.title} className="truncate font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                        {pr.title}
                    </Link>
                    <PoBadge count={pr.review?.poDecisions} strings={strings} />
                    {pr.state !== 'OPEN' && <StateBadge state={pr.state} strings={strings} />}
                    {pr.running && <RunningDot running={pr.running} strings={strings} />}
                </div>
            </td>
            <td className={`${cell} text-gray-700 dark:text-gray-300`}>{pr.author ?? '—'}</td>
            <td className={`${cell} text-right font-mono text-[11px] leading-tight`} title={`${pr.changedFiles} ${strings.files}`}>
                <div className="text-green-600 dark:text-green-400">+{pr.additions}</div>
                <div className="text-red-600 dark:text-red-400">−{pr.deletions}</div>
            </td>
            <td className={cell}><ApprovalChips approvals={pr.approvals} strings={strings} /></td>
            {review ? (
                <>
                    <td className={cell}>
                        <span className="inline-flex items-center gap-1.5">
                            <DecisionIcon decision={review.decision} strings={strings} />
                            <ReviewStartLink pr={pr} strings={strings} />
                        </span>
                    </td>
                    <td className={cell}><FindingCounts review={review} /></td>
                    <td className={cell}><ScoreBar value={review.criticality} title={review.criticalityReason} tone="risk" /></td>
                    <td className={cell}><ScoreBar value={review.aiReviewability} title={review.aiReviewabilityReason} tone="ai" /></td>
                    <td className={`${cell} text-right font-mono text-xs text-gray-700 dark:text-gray-300`} title={strings.need_hint}>{Math.round(review.needScore / 100)}</td>
                    <td className={`${cell} text-xs text-gray-500 dark:text-gray-400`} title={formatDate(review.reviewedAt)}>{formatRelative(review.reviewedAt, now, strings)}</td>
                </>
            ) : (
                <td colSpan={6} className={`${cell} text-xs text-gray-400 dark:text-gray-500`}>
                    <span className="inline-flex items-center gap-1.5">
                        <ReviewStartLink pr={pr} strings={strings} />
                        {pr.running ? '' : strings.not_reviewed}
                    </span>
                </td>
            )}
        </tr>
    );
}

// Eigenen PR als „Shared Review“ für die Organisation markieren / aufheben.
function ShareToggle({ pr, strings }) {
    const toggle = () => router.post(pr.shareUrl, {}, { preserveScroll: true, preserveState: true });
    return (
        <button
            type="button"
            onClick={toggle}
            title={pr.shared ? strings.share_on : strings.share_off}
            aria-pressed={pr.shared}
            className={`shrink-0 inline-flex h-5 w-5 items-center justify-center rounded ${pr.shared
                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                : 'text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400'}`}
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
                <path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
            </svg>
        </button>
    );
}

PullRequestsIndex.layout = AppShell;
