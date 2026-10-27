import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { ApprovalPanel, StackedIcon, DecisionBadge, FindingCounts, LEVEL_TONE, Markdown, RunningBadge, ScoreBar, StateBadge, formatDate, formatDuration } from '../components/PullRequestParts.jsx';

// Detailseite eines Pull Requests: Metadaten, Beschreibung und alle Reviews
// (neuestes oben) mit Zusammenfassung und Befunden. Markdown rendert der Server.

export default function PullRequestShow({ pullRequest: pr, reviews, backUrl, strings }) {
    return (
        <>
            <Head><title>{`${pr.repository}#${pr.number} · ${strings.title}`}</title></Head>

            <PageBands
                header={
                    <div className="space-y-1">
                        <Link href={backUrl} className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">← {strings.back}</Link>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                            <StackedIcon pr={pr} strings={strings} className="h-5 w-5" />
                            {pr.title}
                        </h2>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                            <StateBadge state={pr.state} strings={strings} />
                            <a href={pr.githubUrl} target="_blank" rel="noreferrer" className="font-mono hover:underline">{pr.repository}#{pr.number} ↗</a>
                            <span className="font-mono">{pr.branch} → {pr.targetBranch}</span>
                            {pr.author && <span>{pr.author}</span>}
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 space-y-6">
                        {reviews.length === 0 ? (
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">{strings.no_reviews}</div>
                        ) : (
                            reviews.map((review) => <Review key={review.id} review={review} strings={strings} />)
                        )}
                    </div>

                    <aside className="space-y-6">
                        <ApprovalPanel approvals={pr.approvals} strings={strings} />

                        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <Fact label={strings.files} value={pr.changedFiles} />
                                <Fact label={strings.col_size} value={<><span className="text-green-600 dark:text-green-400">+{pr.additions}</span> <span className="text-red-600 dark:text-red-400">−{pr.deletions}</span></>} />
                                <Fact label={strings.commits} value={pr.commits} />
                                <Fact label={strings.comments} value={pr.comments} />
                                <Fact label={strings.review_comments} value={pr.reviewComments} />
                                <Fact label={strings.commit} value={<span className="font-mono">{pr.lastCommit?.slice(0, 7) ?? '—'}</span>} />
                                <Fact label={strings.opened} value={formatDate(pr.openedAt)} />
                                {pr.mergedAt && <Fact label={strings.merged} value={formatDate(pr.mergedAt)} />}
                                {pr.closedAt && !pr.mergedAt && <Fact label={strings.closed} value={formatDate(pr.closedAt)} />}
                            </dl>
                        </section>

                        {pr.descriptionHtml && (
                            <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                                <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">{strings.description}</h3>
                                <div className="text-sm max-h-[32rem] overflow-y-auto"><Markdown html={pr.descriptionHtml} /></div>
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

function Fact({ label, value }) {
    return (
        <>
            <dt className="text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="text-gray-900 dark:text-gray-100 text-right">{value}</dd>
        </>
    );
}

function Review({ review, strings }) {
    if (review.status !== 'finished') {
        return (
            <section className="bg-white dark:bg-gray-800 rounded-lg shadow px-5 py-3 flex flex-wrap items-center gap-3">
                {review.status === 'running' ? (
                    <RunningBadge running={review} strings={strings} />
                ) : (
                    <span className="rounded px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{strings.review_abandoned}</span>
                )}
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    {strings.started} {formatDate(review.startedAt)}{review.reviewer ? ` · ${review.reviewer}` : ''}
                    {review.commitSha ? ` · ${review.commitSha.slice(0, 7)}` : ''}
                </span>
            </section>
        );
    }

    const duration = formatDuration(review.startedAt, review.finishedAt);
    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow">
            <header className="flex flex-wrap items-center gap-3 border-b border-gray-100 dark:border-gray-700 px-5 py-3">
                <DecisionBadge decision={review.decision} strings={strings} />
                <FindingCounts review={review} />
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    {formatDate(review.reviewedAt)}{review.reviewer ? ` · ${review.reviewer}` : ''}
                    {review.commitSha ? ` · ${review.commitSha.slice(0, 7)}` : ''}
                    {duration ? ` · ${strings.duration} ${duration}` : ''}
                </span>
                {review.stale && <span className="text-xs text-amber-600 dark:text-amber-400">{strings.stale}</span>}
            </header>

            <div className="grid gap-4 sm:grid-cols-2 px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <Score label={strings.col_criticality} value={review.criticality} reason={review.criticalityReason} tone="risk" />
                <Score label={strings.col_ai_reviewability} value={review.aiReviewability} reason={review.aiReviewabilityReason} tone="ai" />
            </div>

            <div className="px-5 py-4 space-y-3">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.findings}</h3>
                {review.findings.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_findings}</p>
                ) : (
                    review.findings.map((finding) => <Finding key={finding.id} finding={finding} strings={strings} />)
                )}
            </div>

            {review.summaryHtml && (
                <details className="px-5 py-4 border-t border-gray-100 dark:border-gray-700" open>
                    <summary className="cursor-pointer font-semibold text-gray-900 dark:text-gray-100">{strings.summary}</summary>
                    <div className="mt-3 text-sm"><Markdown html={review.summaryHtml} /></div>
                </details>
            )}

            {(review.model || review.strictness || review.thoroughness) && (
                <footer className="px-5 py-2 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">
                    {[
                        review.model && `${strings.model}: ${review.model}`,
                        review.strictness && `${strings.strictness}: ${review.strictness}`,
                        review.thoroughness && `${strings.thoroughness}: ${review.thoroughness}`,
                    ].filter(Boolean).join(' · ')}
                </footer>
            )}
        </section>
    );
}

function Score({ label, value, reason, tone }) {
    return (
        <div>
            <div className="text-xs text-gray-500 dark:text-gray-400 mb-1">{label}</div>
            <ScoreBar value={value} tone={tone} />
            {reason && <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">{reason}</p>}
        </div>
    );
}

function Finding({ finding, strings }) {
    return (
        <article className="rounded-md border border-gray-200 dark:border-gray-700 p-3">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${LEVEL_TONE[finding.level]}`}>{strings[`level_${finding.level}`]}</span>
                {finding.requiresPoDecision && (
                    <span className="rounded px-1.5 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{strings.po_decision}</span>
                )}
                <span className="font-medium text-gray-900 dark:text-gray-100">{finding.title}</span>
            </div>
            <div className="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">
                {finding.file ? (
                    <a href={finding.fileUrl} target="_blank" rel="noreferrer" className="hover:underline">
                        {finding.file}{finding.line ? `:${finding.line}` : ''}
                    </a>
                ) : strings.summary_finding}
            </div>
            <div className="mt-2 text-sm"><Markdown html={finding.messageHtml} /></div>
        </article>
    );
}

PullRequestShow.layout = AppShell;
