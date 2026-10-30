import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PullRequestTimeline from '../components/PullRequestTimeline.jsx';
import { ApprovalPanel, PoBadge, StackedIcon, DecisionBadge, FindingCounts, JiraTitle, LEVEL_TONE, Markdown, RunningBadge, ScoreBar, StateBadge, fixCommandHref, formatDate, formatDuration, usePullRequestLiveReload } from '../components/PullRequestParts.jsx';

// Detailseite eines Pull Requests: Metadaten, Beschreibung und alle Reviews
// (neuestes oben) mit Zusammenfassung und Befunden. Markdown rendert der Server.

export default function PullRequestShow({ pullRequest: pr, reviews, commitHistory, backUrl, strings }) {
    usePullRequestLiveReload(['pullRequest', 'reviews', 'commitHistory'], pr.id);
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
                        {reviews.length === 0 ? (
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">{strings.no_reviews}</div>
                        ) : (
                            reviews.map((review) => <Review key={review.id} review={review} pr={pr} strings={strings} />)
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

function Review({ review, pr, strings }) {
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
                <FixStatus fix={review.fix} pr={pr} strings={strings} />
                {review.fixUrl && (
                    <button
                        type="button"
                        onClick={() => setFixing(true)}
                        title={strings.fix_hint}
                        className="ml-auto inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                    >
                        {strings.fix}
                    </button>
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
    return (
        <article className="rounded-md border border-gray-200 dark:border-gray-700 p-3">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${LEVEL_TONE[finding.level]}`}>{strings[`level_${finding.level}`]}</span>
                {finding.requiresPoDecision && (
                    <span className="rounded px-1.5 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{strings.po_decision}</span>
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
            <FixDecision finding={finding} strings={strings} />
        </article>
    );
}

const FIX_TONE = {
    FIX: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
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
// Nach dem Speichern startet claudetask: den Fix-Lauf von ps-review.
function FixDialog({ review, pr, strings, onClose }) {
    const findings = review.findings;
    const [step, setStep] = useState(0);
    const form = useForm({
        findings: findings.map((f) => ({ id: f.id, decision: f.fixDecision ?? 'FIX', note: f.fixNote ?? '' })),
    });
    const overview = step === findings.length;

    const set = (index, key, value) => form.setData('findings', form.data.findings.map((f, i) => (i === index ? { ...f, [key]: value } : f)));
    const noteError = (index) => form.errors[`findings.${index}.note`];

    const submit = () => form.post(review.fixUrl, {
        preserveScroll: true,
        onSuccess: () => {
            onClose();
            window.location.href = fixCommandHref(pr);
        },
        onError: (errors) => {
            const first = findings.findIndex((_, i) => errors[`findings.${i}.note`]);
            if (first >= 0) setStep(first);
        },
    });

    const button = 'inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold';
    const secondary = `${button} border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700`;
    const primary = `${button} bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50`;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" onClick={onClose}></div>
            <div role="dialog" aria-modal="true" aria-labelledby="fix-dialog-title" className="relative flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg bg-white dark:bg-gray-800 shadow-xl">
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

                <div className="overflow-y-auto px-6 py-4">
                    {overview ? (
                        <FixOverview findings={findings} data={form.data.findings} strings={strings} onPick={setStep} />
                    ) : (
                        <FixStep
                            finding={findings[step]}
                            data={form.data.findings[step]}
                            error={noteError(step)}
                            label={strings.fix_step.replace(':n', step + 1).replace(':total', findings.length)}
                            strings={strings}
                            onChange={(key, value) => set(step, key, value)}
                        />
                    )}
                </div>

                <footer className="flex justify-between gap-3 border-t border-gray-100 dark:border-gray-700 px-6 py-4">
                    <button type="button" onClick={onClose} className={secondary}>{strings.fix_cancel}</button>
                    <div className="flex gap-3">
                        {step > 0 && <button type="button" onClick={() => setStep(step - 1)} className={secondary}>{strings.fix_back}</button>}
                        {overview ? (
                            <button type="button" onClick={submit} disabled={form.processing} className={primary}>{strings.fix_submit}</button>
                        ) : (
                            <button type="button" onClick={() => setStep(step + 1)} className={primary}>{strings.fix_next}</button>
                        )}
                    </div>
                </footer>
            </div>
        </div>
    );
}

function FixStep({ finding, data, error, label, strings, onChange, skipNeedsReason = false }) {
    const po = finding.requiresPoDecision;
    const reason = skipNeedsReason && !po && data.decision === 'SKIP';
    return (
        <div className="space-y-4">
            <div>
                {label && <div className="text-xs text-gray-500 dark:text-gray-400">{label}</div>}
                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                    <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${LEVEL_TONE[finding.level]}`}>{strings[`level_${finding.level}`]}</span>
                    {po && <span className="rounded px-1.5 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{strings.po_decision}</span>}
                    <span className="font-medium text-gray-900 dark:text-gray-100">{finding.title}</span>
                </div>
                {finding.file && (
                    <div className="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">{finding.file}{finding.line ? `:${finding.line}` : ''}</div>
                )}
                <div className="mt-2 max-h-64 overflow-y-auto rounded-md bg-gray-50 dark:bg-gray-900/40 p-3 text-sm"><Markdown html={finding.messageHtml} /></div>
            </div>

            <div className="flex gap-4">
                {['FIX', 'SKIP'].map((value) => (
                    <label key={value} className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="radio"
                            name={`fix-decision-${finding.id}`}
                            value={value}
                            checked={data.decision === value}
                            onChange={() => onChange('decision', value)}
                            className="text-indigo-600 focus:ring-indigo-500"
                        />
                        {strings[`fix_decision_${value}`]}
                    </label>
                ))}
            </div>

            <div>
                <label htmlFor={`fix-note-${finding.id}`} className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {po ? strings.fix_po_note : reason ? strings.fix_skip_reason : strings.fix_note}
                </label>
                <textarea
                    id={`fix-note-${finding.id}`}
                    rows={4}
                    value={data.note}
                    onChange={(e) => onChange('note', e.target.value)}
                    placeholder={po ? strings.fix_po_note_placeholder : reason ? strings.fix_skip_reason_placeholder : strings.fix_note_placeholder}
                    className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                {error && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>}
            </div>
            {skipNeedsReason && data.decision === 'SKIP' && <p className="text-sm text-gray-600 dark:text-gray-400">{strings.fix_skip_hint}</p>}
        </div>
    );
}

// Einzelner Befund: „Fixen“ startet den Fix-Lauf per claudetask:, „Nicht fixen“
// schreibt die Begründung in den PR-Body und entfernt den Befund.
function FindingFixDialog({ finding, pr, strings, onClose }) {
    const form = useForm({ decision: 'FIX', note: finding.fixNote ?? '' });
    const skip = form.data.decision === 'SKIP';

    const submit = () => form.post(finding.fixUrl, {
        preserveScroll: true,
        onSuccess: () => {
            onClose();
            if (!skip) window.location.href = fixCommandHref(pr);
        },
    });

    const button = 'inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold';
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" onClick={onClose}></div>
            <div role="dialog" aria-modal="true" aria-labelledby={`finding-fix-title-${finding.id}`} className="relative flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg bg-white dark:bg-gray-800 shadow-xl">
                <header className="border-b border-gray-100 dark:border-gray-700 px-6 py-4">
                    <h2 id={`finding-fix-title-${finding.id}`} className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.fix_finding_title}</h2>
                </header>
                <div className="overflow-y-auto px-6 py-4">
                    <FixStep
                        finding={finding}
                        data={form.data}
                        error={form.errors.note}
                        strings={strings}
                        skipNeedsReason
                        onChange={(key, value) => form.setData(key, value)}
                    />
                </div>
                <footer className="flex justify-between gap-3 border-t border-gray-100 dark:border-gray-700 px-6 py-4">
                    <button type="button" onClick={onClose} className={`${button} border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700`}>{strings.fix_cancel}</button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={form.processing}
                        className={`${button} text-white disabled:opacity-50 ${skip ? 'bg-gray-700 hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500' : 'bg-indigo-600 hover:bg-indigo-500'}`}
                    >
                        {skip ? strings.fix_skip_submit : strings.fix_submit}
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
                            {f.requiresPoDecision && data[i].note ? `${strings.fix_po_note}: ` : ''}{data[i].note || strings.fix_no_note}
                        </p>
                    </li>
                ))}
            </ul>
            <p className="text-sm text-gray-600 dark:text-gray-400">{strings.fix_overview_hint}</p>
        </div>
    );
}

PullRequestShow.layout = AppShell;
