import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PullRequestTimeline from '../components/PullRequestTimeline.jsx';
import { ApprovalPanel, PoBadge, StackedIcon, DecisionBadge, FindingCounts, JiraTitle, LEVEL_TONE, Markdown, RunningBadge, ScoreBar, StateBadge, fixCommandHref, resolveCommandHref, convertCommandHref, formatDate, formatDuration, usePullRequestLiveReload } from '../components/PullRequestParts.jsx';

// Detailseite eines Pull Requests: Metadaten, Beschreibung und alle Reviews
// (neuestes oben) mit Zusammenfassung und Befunden. Markdown rendert der Server.

export default function PullRequestShow({ pullRequest: pr, reviews, commitHistory, backUrl, strings }) {
    usePullRequestLiveReload(['pullRequest', 'reviews', 'commitHistory'], pr.id);
    const latestFinishedId = reviews.find((r) => r.status === 'finished')?.id;
    return (
        <>
            <Head><title>{`${pr.repository}#${pr.number} · ${strings.title}`}</title></Head>

            <PageBands
                header={
                    <div className="space-y-1">
                        <Link href={backUrl} className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">← {strings.back}</Link>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                            <StackedIcon pr={pr} strings={strings} className="h-5 w-5" />
                            <JiraTitle title={pr.title} />
                        </h2>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                            <StateBadge state={pr.state} strings={strings} />
                            <a href={pr.githubUrl} target="_blank" rel="noreferrer" className="font-mono hover:underline">{pr.repository}#{pr.number} ↗</a>
                            <span className="font-mono">{pr.branch} → {pr.targetBranch}</span>
                            {pr.author && <span>{pr.author}</span>}
                            <HideToggle pr={pr} strings={strings} />
                        </div>
                    </div>
                }
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-3">
                        <PullRequestTimeline pr={pr} reviews={reviews} commits={commitHistory} strings={strings} />
                    </div>
                    <div className="lg:col-span-2 space-y-6">
                        {pr.foreignChangesRequested?.map((cr) => <ForeignChangesRequested key={cr.login} cr={cr} pr={pr} strings={strings} />)}
                        {reviews.length === 0 ? (
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">{strings.no_reviews}</div>
                        ) : (
                            reviews.map((review) => <Review key={review.id} review={review} pr={pr} strings={strings} latest={review.id === latestFinishedId} />)
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

// PR in den eigenen Listen aus- bzw. wieder einblenden.
function HideToggle({ pr, strings }) {
    const toggle = () => router.post(pr.hideUrl, {}, { preserveScroll: true, preserveState: true });
    return (
        <button
            type="button"
            onClick={toggle}
            title={pr.hidden ? strings.unhide_hint : strings.hide_hint}
            aria-pressed={pr.hidden}
            className={`ml-auto inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium border shadow-sm ${pr.hidden
                ? 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300'
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'}`}
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                {pr.hidden ? <><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></> : <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M14.12 14.12a3 3 0 11-4.24-4.24M1 1l22 22" />}
            </svg>
            {pr.hidden ? strings.unhide : strings.hide}
        </button>
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

function ForeignChangesRequested({ cr, pr, strings }) {
    return (
        <section className="flex flex-wrap items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-5 py-3 text-sm dark:border-red-900/60 dark:bg-red-900/20">
            <span className="text-red-600 dark:text-red-400">✗</span>
            <span className="text-gray-900 dark:text-gray-100">
                {strings.foreign_changes_requested.replace(':name', cr.name)}
                {cr.submittedAt && <span className="text-gray-500 dark:text-gray-400"> · {formatDate(cr.submittedAt)}</span>}
            </span>
            <a
                href={convertCommandHref(pr, cr.login)}
                title={strings.convert_review_hint}
                className="ml-auto inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
            >
                {strings.convert_review}
            </a>
        </section>
    );
}

function Review({ review, pr, strings, latest }) {
    const [fixing, setFixing] = useState(false);
    if (review.status !== 'finished') {
        return (
            <section id={`review-${review.id}`} className="scroll-mt-4 bg-white dark:bg-gray-800 rounded-lg shadow px-5 py-3 flex flex-wrap items-center gap-3">
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
    // Dieselben Signale wie in der Liste: Konflikt, rote CI, offene Review-Threads.
    const resolveLabel = !latest || pr.state !== 'OPEN' ? null
        : pr.conflicting ? strings.resolve_conflict
        : pr.ciState === 'failed' ? strings.resolve_ci
        : pr.unresolvedThreads > 0 ? strings.resolve_threads
        : null;
    const resolvable = resolveLabel !== null;
    return (
        <section id={`review-${review.id}`} className="scroll-mt-4 bg-white dark:bg-gray-800 rounded-lg shadow">
            <header className="flex flex-wrap items-center gap-3 border-b border-gray-100 dark:border-gray-700 px-5 py-3">
                <DecisionBadge decision={review.decision} strings={strings} />
                <FindingCounts review={review} />
                <PoBadge count={review.poDecisions} strings={strings} />
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    {formatDate(review.reviewedAt)}{review.reviewer ? ` · ${review.reviewer}` : ''}
                    {review.commitSha ? ` · ${review.commitSha.slice(0, 7)}` : ''}
                    {duration ? ` · ${strings.duration} ${duration}` : ''}
                </span>
                {review.stale && <span className="text-xs text-amber-600 dark:text-amber-400">{strings.stale}</span>}
                {review.convertedFrom && <span className="text-xs text-gray-500 dark:text-gray-400">{strings.converted_from.replace(':login', review.convertedFrom)}</span>}
                <FixStatus fix={review.fix} pr={pr} strings={strings} />
                {(review.fixUrl || resolvable) && (
                    <div className="ml-auto flex items-center gap-2">
                        {resolvable && (
                            <a
                                href={resolveCommandHref(pr)}
                                title={strings.resolve_conflict_hint}
                                className="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500"
                            >
                                {resolveLabel}
                            </a>
                        )}
                        {review.fixUrl && (
                            <button
                                type="button"
                                onClick={() => setFixing(true)}
                                title={strings.fix_hint}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                            >
                                {strings.fix}
                            </button>
                        )}
                    </div>
                )}
            </header>
            {fixing && <FixDialog review={review} pr={pr} strings={strings} onClose={() => setFixing(false)} />}

            <div className="grid gap-4 sm:grid-cols-3 px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <Score label={strings.col_criticality} value={review.criticality} reason={review.criticalityReason} tone="risk" />
                <Score label={strings.col_ai_reviewability} value={review.aiReviewability} reason={review.aiReviewabilityReason} tone="ai" />
                <Score label={strings.col_po_review_likelihood} value={review.poReviewLikelihood} reason={review.poReviewLikelihoodReason} tone="po" />
            </div>

            <div className="px-5 py-4 space-y-3">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.findings}</h3>
                {review.findings.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_findings}</p>
                ) : (
                    review.findings.map((finding) => <Finding key={finding.id} finding={finding} pr={pr} strings={strings} />)
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

function Finding({ finding, pr, strings }) {
    const [fixing, setFixing] = useState(false);
    const adopt = (note) => router.post(finding.fixUrl, { decision: 'FIX', note, start: false }, { preserveScroll: true });
    return (
        <article id={`finding-${finding.id}`} className="scroll-mt-4 rounded-md border border-gray-200 dark:border-gray-700 p-3 target:ring-2 target:ring-indigo-500">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${LEVEL_TONE[finding.level]}`}>{strings[`level_${finding.level}`]}</span>
                {finding.requiresPoDecision && (
                    <span className="rounded px-1.5 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{strings.po_decision}</span>
                )}
                {finding.confidence !== null && (
                    <span title={finding.confidenceReason || undefined} className="rounded px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {strings.confidence} {finding.confidence} %
                    </span>
                )}
                <span className="font-medium text-gray-900 dark:text-gray-100">{finding.title}</span>
                {finding.fixUrl && (
                    <button
                        type="button"
                        onClick={() => setFixing(true)}
                        title={strings.fix_finding_hint}
                        className="ml-auto inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-500"
                    >
                        {strings.fix_finding}
                    </button>
                )}
            </div>
            {fixing && <FindingFixDialog finding={finding} pr={pr} strings={strings} onClose={() => setFixing(false)} />}
            <div className="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">
                {finding.file ? (
                    <a href={finding.fileUrl} target="_blank" rel="noreferrer" className="hover:underline">
                        {finding.file}{finding.line ? `:${finding.line}` : ''}
                    </a>
                ) : strings.summary_finding}
            </div>
            <div className="mt-2 text-sm"><Markdown html={finding.messageHtml} /></div>
            <Suggestions
                finding={finding}
                adopted={finding.fixDecision === 'FIX' ? finding.fixNote : null}
                strings={strings}
                onAdopt={finding.fixUrl ? adopt : null}
            />
            {finding.confidenceReason && (
                <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">{strings.confidence}: {finding.confidenceReason}</p>
            )}
            <FixDecision finding={finding} strings={strings} />
        </article>
    );
}

// Einzelvorschlag ohne Buchstaben hat den Schlüssel ONE.
const optionKey = (option) => option.letter ?? 'ONE';
const suggestionNote = (option) => `Vorschlag${option.letter ? ` ${option.letter}` : ''}: ${option.text}`;
const withLetter = (text, letter) => text.replace(':letter', letter ?? '').replace(/\s{2,}/g, ' ');

// Linke Spalte einer Optionsbox: Buchstabe und/oder Prozent; ohne beides keine Spalte.
const hasBadge = (option) => Boolean(option.letter) || option.percent !== null;
const hasPercent = (options) => options.some((o) => o.percent !== null);

function OptionBadge({ option }) {
    if (!hasBadge(option)) return null;
    return (
        <span className="flex flex-col items-center gap-0.5 pt-0.5">
            {option.letter && <span className="text-[26px] font-semibold leading-[30px] text-gray-500 dark:text-gray-400">{option.letter}</span>}
            {option.percent !== null && (
                <span className="whitespace-nowrap text-[13px] font-medium leading-[18px] text-gray-500 dark:text-gray-400">{option.percent} %</span>
            )}
        </span>
    );
}

// Vorschläge im A/B/C-Format als Optionen; das Icon übernimmt einen als Lösung.
function Suggestions({ finding, adopted, strings, onAdopt }) {
    if (!finding.suggestions?.length) return null;
    return (
        <div className="mt-3 space-y-2">
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{strings.suggestions}</span>
                {hasPercent(finding.suggestions) && <span className="text-xs text-gray-500 dark:text-gray-400">{strings.suggestions_percent_hint}</span>}
            </div>
            {finding.suggestions.map((option) => {
                const note = suggestionNote(option);
                const active = adopted?.trim() === note.trim();
                return (
                    <div
                        key={optionKey(option)}
                        className={`grid ${hasBadge(option) ? 'grid-cols-[3.5rem_1fr_auto] pl-3' : 'grid-cols-[1fr_auto] pl-4'} items-start gap-4 rounded-[10px] border-2 py-3 pr-2 ${active ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/40' : 'border-transparent bg-gray-100 dark:bg-gray-900/60'}`}
                    >
                        <OptionBadge option={option} />
                        <div className="flex min-w-0 flex-col gap-1 text-sm [&_code]:border [&_code]:border-gray-200 [&_code]:bg-white dark:[&_code]:border-gray-700 dark:[&_code]:bg-gray-800">
                            {option.titleHtml && <span className="font-semibold text-gray-900 dark:text-gray-100" dangerouslySetInnerHTML={{ __html: option.titleHtml }} />}
                            <Markdown html={option.html} />
                        </div>
                        {onAdopt ? (
                            <button
                                type="button"
                                onClick={() => !active && onAdopt(note)}
                                aria-pressed={active}
                                aria-label={withLetter(active ? strings.suggestion_adopted : strings.suggestion_adopt, option.letter)}
                                title={withLetter(active ? strings.suggestion_adopted : strings.suggestion_adopt, option.letter)}
                                className={`inline-flex h-9 w-9 items-center justify-center rounded-md ${active ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 hover:bg-white hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-indigo-400'}`}
                            >
                                {active ? (
                                    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                                ) : (
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9" /><path d="M8.5 12.5l2.5 2.5 4.5-5" /></svg>
                                )}
                            </button>
                        ) : <span />}
                    </div>
                );
            })}
            {finding.messageAfterHtml && <div className="text-sm"><Markdown html={finding.messageAfterHtml} /></div>}
        </div>
    );
}

const FIX_TONE = {
    FIX: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    SUGGESTED: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    SKIP: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    FIXED: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    SKIPPED: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

// Entscheidung aus dem Fix-Dialog und Ergebnis des Fix-Laufs.
function FixDecision({ finding, strings }) {
    if (!finding.fixDecision && !finding.fixOutcome) return null;
    return (
        <div className="mt-3 space-y-1 border-t border-gray-100 dark:border-gray-700 pt-2 text-sm">
            {finding.fixDecision && (
                <div className="flex flex-wrap items-baseline gap-2">
                    <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${FIX_TONE[finding.fixDecision]}`}>{strings[`fix_decision_${finding.fixDecision}`]}</span>
                    {finding.fixNote && (
                        <span className="whitespace-pre-wrap text-gray-700 dark:text-gray-300">
                            {finding.requiresPoDecision && <span className="font-medium">{strings.fix_po_note}: </span>}
                            {finding.fixNote}
                        </span>
                    )}
                </div>
            )}
            {finding.fixOutcome && (
                <div className="flex flex-wrap items-baseline gap-2">
                    <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${FIX_TONE[finding.fixOutcome]}`}>{strings[`fix_outcome_${finding.fixOutcome}`]}</span>
                    {finding.fixOutcomeNote && <span className="whitespace-pre-wrap text-gray-700 dark:text-gray-300">{finding.fixOutcomeNote}</span>}
                </div>
            )}
        </div>
    );
}

function FixStatus({ fix, pr, strings }) {
    if (!fix) return null;
    const text = {
        requested: strings.fix_status_requested.replace(':time', formatDate(fix.requestedAt)),
        running: strings.fix_status_running.replace(':time', formatDate(fix.startedAt)),
        finished: strings.fix_status_finished.replace(':time', formatDate(fix.finishedAt)),
    }[fix.status];
    const tone = fix.status === 'finished'
        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
        : 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300';
    return (
        <span className={`inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium ${tone}`}>
            {fix.status === 'running' && <span className="h-1.5 w-1.5 rounded-full bg-sky-500 animate-pulse" />}
            {text}
            {fix.requestedBy ? ` · ${fix.requestedBy}` : ''}
            {fix.commitSha ? ` · ${fix.commitSha.slice(0, 7)}` : ''}
            {/* Angefordert, aber nie abgeholt: der Start über claudetask: lief ins Leere. */}
            {fix.status === 'requested' && (
                <a href={fixCommandHref(pr)} title={strings.fix_relaunch} className="ml-1 hover:underline">▶</a>
            )}
        </span>
    );
}

// Ein Schritt je Befund (Entscheidung + Anmerkung), danach die Übersicht.
// „Fix starten“ startet nach dem Speichern per claudetask: den Fix-Lauf von ps-review.
function FixDialog({ review, pr, strings, onClose }) {
    const findings = review.findings;
    const [step, setStep] = useState(0);
    const form = useForm({
        findings: findings.map((f) => ({ id: f.id, ...initialChoice(f) })),
    });
    const overview = step === findings.length;

    const noteError = (index) => form.errors[`findings.${index}.note`];

    const submit = (start) => {
        const missing = start ? findings.findIndex((f, i) => missingNote(f, form.data.findings[i], false)) : -1;
        if (missing >= 0) {
            form.setError(`findings.${missing}.note`, strings.fix_po_note_required);
            setStep(missing);
            return;
        }
        form.transform((data) => ({ ...data, start }));
        form.post(review.fixUrl, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                if (start) window.location.href = fixCommandHref(pr);
            },
            onError: (errors) => {
                const first = findings.findIndex((_, i) => errors[`findings.${i}.note`]);
                if (first >= 0) setStep(first);
            },
        });
    };

    const button = 'inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold';
    const secondary = `${button} border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700`;
    const primary = `${button} bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50`;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" onClick={onClose}></div>
            <div role="dialog" aria-modal="true" aria-labelledby="fix-dialog-title" className="relative flex max-h-[90vh] w-full max-w-[1280px] flex-col rounded-lg bg-white dark:bg-gray-800 shadow-xl">
                <header className="border-b border-gray-100 dark:border-gray-700 px-6 py-4">
                    <h2 id="fix-dialog-title" className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.fix}</h2>
                    <ol className="mt-3 flex gap-1" aria-hidden="true">
                        {[...findings, null].map((f, i) => (
                            <li key={f?.id ?? 'overview'}>
                                <button
                                    type="button"
                                    tabIndex={-1}
                                    onClick={() => setStep(i)}
                                    className={`h-1.5 w-6 rounded ${i === step ? 'bg-indigo-600' : noteError(i) ? 'bg-red-400' : i < findings.length && form.data.findings[i].decision === 'SKIP' ? 'bg-gray-300 dark:bg-gray-600' : 'bg-indigo-200 dark:bg-indigo-900'}`}
                                />
                            </li>
                        ))}
                    </ol>
                </header>

                <div className="overflow-y-auto p-6">
                    {overview ? (
                        <FixOverview findings={findings} data={form.data.findings} strings={strings} onPick={setStep} />
                    ) : (
                        <FixStep
                            finding={findings[step]}
                            data={form.data.findings[step]}
                            error={noteError(step)}
                            label={strings.fix_step.replace(':n', step + 1).replace(':total', findings.length)}
                            strings={strings}
                            onData={(data) => form.setData('findings', form.data.findings.map((f, i) => (i === step ? { ...f, ...data } : f)))}
                        />
                    )}
                </div>

                <footer className="flex justify-between gap-3 border-t border-gray-100 dark:border-gray-700 px-6 py-4">
                    <button type="button" onClick={onClose} className={secondary}>{strings.fix_cancel}</button>
                    <div className="flex gap-3">
                        {step > 0 && <button type="button" onClick={() => setStep(step - 1)} className={secondary}>{strings.fix_back}</button>}
                        {overview ? (
                            <>
                                <button type="button" onClick={() => submit(false)} disabled={form.processing} className={secondary}>{strings.fix_save}</button>
                                <button type="button" onClick={() => submit(true)} disabled={form.processing} className={primary}>{strings.fix_submit}</button>
                            </>
                        ) : (
                            <button type="button" onClick={() => setStep(step + 1)} className={primary}>{strings.fix_next}</button>
                        )}
                    </div>
                </footer>
            </div>
        </div>
    );
}

// Gewählte Option aus Entscheidung + Anmerkung: Buchstabe eines Vorschlags, sonst die Entscheidung.
function readChoice(finding, data) {
    if (data.decision === 'FIX') {
        for (const option of finding.suggestions ?? []) {
            const prefix = suggestionNote(option);
            if (data.note.startsWith(prefix)) return { choice: optionKey(option), extra: data.note.slice(prefix.length).trim() };
        }
    }
    return { choice: data.decision, extra: data.decision === 'SUGGESTED' ? '' : data.note };
}

function writeChoice(finding, choice, extra) {
    const option = finding.suggestions?.find((o) => optionKey(o) === choice);
    if (option) return { decision: 'FIX', note: extra.trim() ? `${suggestionNote(option)}\n\n${extra}` : suggestionNote(option) };
    return { decision: choice, note: choice === 'SUGGESTED' ? '' : extra };
}

// Ohne gespeicherte Entscheidung ist Vorschlag A vorausgewählt.
function initialChoice(finding) {
    if (finding.fixDecision) return { decision: finding.fixDecision, note: finding.fixNote ?? '' };
    return finding.suggestions?.length ? writeChoice(finding, optionKey(finding.suggestions[0]), '') : { decision: 'FIX', note: '' };
}

// Pflichttext fehlt: PO-Entscheidung (außer „wie vorgeschlagen“) bzw. Begründung beim Verwerfen.
function missingNote(finding, data, skipNeedsReason) {
    const { choice, extra } = readChoice(finding, data);
    if (extra.trim()) return false;
    if (finding.requiresPoDecision) return choice !== 'SUGGESTED';
    return skipNeedsReason && choice === 'SKIP';
}

function Check() {
    return (
        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="invisible text-indigo-600 group-has-[:checked]:visible dark:text-indigo-400">
            <path d="M5 12.5l4.5 4.5L19 7.5" />
        </svg>
    );
}

const OPTION_BOX = 'group relative cursor-pointer rounded-[10px] border-2 border-transparent bg-gray-100 dark:bg-gray-900/60 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 dark:has-[:checked]:border-indigo-400 dark:has-[:checked]:bg-indigo-950/40 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-indigo-600';

function FixStep({ finding, data, error, label, strings, onData, skipNeedsReason = false }) {
    const po = finding.requiresPoDecision;
    const options = finding.suggestions ?? [];
    const { choice, extra } = readChoice(finding, data);
    const skip = choice === 'SKIP';
    const suggested = choice === 'SUGGESTED';
    const required = po ? !suggested : skipNeedsReason && skip;
    const name = `fix-choice-${finding.id}`;
    const pick = (value) => onData(writeChoice(finding, value, extra));
    const small = [...(options.length ? [] : ['SUGGESTED']), 'FIX', 'SKIP'];
    const [copied, setCopied] = useState(false);
    const path = finding.file ? `${finding.file}${finding.line ? `:${finding.line}` : ''}` : null;
    const copy = () => navigator.clipboard?.writeText(path).then(() => {
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    });

    return (
        <div className="grid items-start gap-x-10 gap-y-6 text-[15px] leading-6 text-gray-800 dark:text-gray-200 lg:grid-cols-2">
            <div className="min-w-0 space-y-5">
            <div className="space-y-2">
                {label && <div className="text-xs text-gray-500 dark:text-gray-400">{label}</div>}
                <div className="flex flex-wrap gap-2">
                    <span className={`rounded px-2 py-0.5 text-sm font-medium ${LEVEL_TONE[finding.level]}`}>{strings[`level_${finding.level}`]}</span>
                    {po && <span className="rounded px-2 py-0.5 text-sm font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{strings.po_decision}</span>}
                </div>
                <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">{finding.title}</h3>
                {path && (
                    <div className="flex items-center gap-1.5">
                        <span className="font-mono text-[13px] leading-5 text-gray-500 dark:text-gray-400 [overflow-wrap:anywhere]">{path}</span>
                        <button
                            type="button"
                            onClick={copy}
                            aria-label={copied ? strings.fix_path_copied : strings.fix_copy_path}
                            title={copied ? strings.fix_path_copied : strings.fix_copy_path}
                            className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        >
                            {copied ? (
                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                            ) : (
                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V6a2 2 0 0 1 2-2h9" /></svg>
                            )}
                        </button>
                    </div>
                )}
            </div>

                {finding.messageSections?.length ? (
                    finding.messageSections.map((section, i) => (
                        <section key={i}>
                            <h4 className="mb-1 font-bold text-gray-900 dark:text-gray-100">{section.label}</h4>
                            <Markdown html={section.html} />
                        </section>
                    ))
                ) : (
                    <Markdown html={finding.messageHtml} />
                )}
                {finding.messageAfterHtml && <Markdown html={finding.messageAfterHtml} />}
            </div>

            <div className="min-w-0 space-y-5">
            <fieldset className="min-w-0">
                <legend className="float-left font-semibold text-gray-900 dark:text-gray-100">{strings.fix_how}</legend>
                {hasPercent(options) && <span className="float-right pt-0.5 text-[13px] leading-5 text-gray-500 dark:text-gray-400">{strings.suggestions_percent_hint}</span>}
                <div className="clear-both space-y-2.5 pt-2.5">
                    {options.map((option) => (
                        <label key={optionKey(option)} className={`${OPTION_BOX} grid ${hasBadge(option) ? 'grid-cols-[56px_1fr_24px] pl-3' : 'grid-cols-[1fr_24px] pl-4'} items-start gap-4 py-3 pr-3.5`}>
                            <input type="radio" name={name} checked={choice === optionKey(option)} onChange={() => pick(optionKey(option))} className="sr-only" />
                            <OptionBadge option={option} />
                            <span className="flex min-w-0 flex-col gap-1 [&_code]:border [&_code]:border-gray-200 [&_code]:bg-white dark:[&_code]:border-gray-700 dark:[&_code]:bg-gray-800">
                                {option.titleHtml && <span className="font-semibold text-gray-900 dark:text-gray-100" dangerouslySetInnerHTML={{ __html: option.titleHtml }} />}
                                <Markdown html={option.html} />
                            </span>
                            <Check />
                        </label>
                    ))}
                    <div className={`grid gap-2.5 ${small.length === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'}`}>
                        {small.map((value) => (
                            <label key={value} className={`${OPTION_BOX} grid min-h-12 grid-cols-[1fr_24px] items-center gap-3 py-2.5 pl-4 pr-3.5`}>
                                <input type="radio" name={name} checked={choice === value} onChange={() => pick(value)} className="sr-only" />
                                <span className="font-semibold text-gray-900 dark:text-gray-100">{value === 'FIX' && options.length ? strings.fix_choice_other : strings[`fix_decision_${value}`]}</span>
                                <Check />
                            </label>
                        ))}
                    </div>
                </div>
            </fieldset>

            {!suggested && (
                <div className="space-y-1.5">
                    <div className="flex items-baseline justify-between gap-3">
                        <label htmlFor={`fix-note-${finding.id}`} className="font-semibold text-gray-900 dark:text-gray-100">
                            {po && !skip ? strings.fix_po_note : skip ? strings.fix_skip_reason : strings.fix_note}
                        </label>
                        <span className="text-[13px] leading-5 text-gray-500 dark:text-gray-400">{required ? strings.fix_required : strings.fix_optional}</span>
                    </div>
                    <textarea
                        id={`fix-note-${finding.id}`}
                        rows={4}
                        value={extra}
                        required={required}
                        onChange={(e) => onData(writeChoice(finding, choice, e.target.value))}
                        placeholder={po && !skip ? strings.fix_po_note_placeholder : skip ? strings.fix_skip_reason_placeholder : strings.fix_note_placeholder}
                        className="block w-full rounded-lg border-gray-400 px-3.5 py-2.5 text-[15px] leading-6 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                    {skipNeedsReason && skip && <p className="text-sm text-gray-600 dark:text-gray-400">{strings.fix_skip_hint}</p>}
                </div>
            )}
            </div>
        </div>
    );
}

// Einzelner Befund: „Fixen“ startet den Fix-Lauf per claudetask:, „Nicht fixen“
// schreibt die Begründung in den PR-Body und entfernt den Befund.
function FindingFixDialog({ finding, pr, strings, onClose }) {
    const form = useForm(initialChoice(finding));
    const skip = form.data.decision === 'SKIP';
    const { choice } = readChoice(finding, form.data);
    const blocked = missingNote(finding, form.data, true);

    const submit = () => form.post(finding.fixUrl, {
        preserveScroll: true,
        onSuccess: () => {
            onClose();
            if (!skip) window.location.href = fixCommandHref(pr);
        },
    });

    const button = 'inline-flex min-h-11 items-center rounded-lg px-5 text-[15px] font-semibold';
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" onClick={onClose}></div>
            <div role="dialog" aria-modal="true" aria-labelledby={`finding-fix-title-${finding.id}`} className="relative flex max-h-[90vh] w-full max-w-[1280px] flex-col rounded-lg bg-white dark:bg-gray-800 shadow-xl">
                <header className="border-b border-gray-200 dark:border-gray-700 px-6 py-5">
                    <h2 id={`finding-fix-title-${finding.id}`} className="text-xl font-medium text-gray-900 dark:text-gray-100">{strings.fix_finding_title}</h2>
                </header>
                <div className="overflow-y-auto p-6">
                    <FixStep
                        finding={finding}
                        data={form.data}
                        error={form.errors.note}
                        strings={strings}
                        skipNeedsReason
                        onData={(data) => form.setData({ ...form.data, ...data })}
                    />
                </div>
                <footer className="flex flex-wrap justify-between gap-3 border-t border-gray-200 dark:border-gray-700 px-6 py-5">
                    <button type="button" onClick={onClose} className={`${button} border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700`}>{strings.fix_cancel}</button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={form.processing || blocked}
                        className={`${button} text-white disabled:cursor-not-allowed disabled:opacity-45 ${skip ? 'bg-gray-700 hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500' : 'bg-indigo-600 hover:bg-indigo-500'}`}
                    >
                        {skip ? strings.fix_skip_submit : finding.suggestions?.find((o) => optionKey(o) === choice)?.letter ? strings.fix_submit_option.replace(':letter', choice) : strings.fix_submit}
                    </button>
                </footer>
            </div>
        </div>
    );
}

function FixOverview({ findings, data, strings, onPick }) {
    return (
        <div className="space-y-3">
            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.fix_overview}</h3>
            <ul className="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                {findings.map((f, i) => (
                    <li key={f.id} className="py-2">
                        <button type="button" onClick={() => onPick(i)} className="flex w-full flex-wrap items-baseline gap-2 text-left hover:underline">
                            <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${FIX_TONE[data[i].decision]}`}>{strings[`fix_decision_${data[i].decision}`]}</span>
                            <span className="text-gray-900 dark:text-gray-100">{f.title}</span>
                        </button>
                        <p className="mt-0.5 whitespace-pre-wrap text-xs text-gray-500 dark:text-gray-400">
                            {data[i].decision === 'SUGGESTED' ? '' : <>{f.requiresPoDecision && data[i].note ? `${strings.fix_po_note}: ` : ''}{data[i].note || strings.fix_no_note}</>}
                        </p>
                    </li>
                ))}
            </ul>
            <p className="text-sm text-gray-600 dark:text-gray-400">{strings.fix_overview_hint}</p>
        </div>
    );
}

PullRequestShow.layout = AppShell;
