import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PullRequestTimeline from '../components/PullRequestTimeline.jsx';
import { ApprovalPanel, StackedIcon, DecisionBadge, JiraTitle, LEVEL_TONE, Markdown, RunningBadge, ScoreBar, StateBadge, fixCommandHref, resolveCommandHref, convertCommandHref, skillUpdateCommandHref, JIRA_URL, formatDate, formatDuration, usePullRequestLiveReload } from '../components/PullRequestParts.jsx';

// Detailseite eines Pull Requests: Kopf mit Status, aktuelles Review und Tabs
// (Befunde, Analyse, Beschreibung, frühere Reviews). Markdown rendert der Server.
// Es zählt nur das neueste abgeschlossene Review; frühere sind überholt.

const CARD = 'bg-white dark:bg-gray-800 rounded-lg shadow';
const EARLIER_PAGE = 5;
const chip = 'inline-flex items-center gap-1.5 rounded-md px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap';
const smallButton = 'inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold';

export default function PullRequestShow({ pullRequest: pr, reviews, commitHistory, backUrl, psReviewSkill, strings }) {
    usePullRequestLiveReload(['pullRequest', 'reviews', 'commitHistory'], pr.id);
    const currentIndex = reviews.findIndex((r) => r.status === 'finished');
    const current = currentIndex >= 0 ? reviews[currentIndex] : null;
    // Laufende/abgebrochene Reviews nach dem aktuellen stehen oben, alles davor ist überholt.
    const pending = current ? reviews.slice(0, currentIndex) : reviews;
    const earlier = current ? reviews.slice(currentIndex + 1) : [];
    const [tab, setTab] = useState('findings');

    const tabs = [
        { key: 'findings', label: strings.findings, count: current?.findings.length ?? 0 },
        { key: 'analysis', label: strings.tab_analysis },
        { key: 'description', label: strings.description },
        { key: 'earlier', label: strings.tab_earlier, count: earlier.length },
    ];

    return (
        <>
            <Head><title>{`${pr.repository}#${pr.number} · ${strings.title}`}</title></Head>

            <PageBands header={<PrHeader pr={pr} current={current} backUrl={backUrl} strings={strings} />} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {current?.skillOutdated && <SkillOutdatedNotice review={current} skill={psReviewSkill} strings={strings} />}
                    {pr.foreignChangesRequested?.map((cr) => <ForeignChangesRequested key={cr.login} cr={cr} pr={pr} strings={strings} />)}
                    {pending.map((review) => <PendingReview key={review.id} review={review} strings={strings} />)}
                    {current ? (
                        <CurrentReview review={current} pr={pr} strings={strings} />
                    ) : (
                        pending.length === 0 && <div className={`${CARD} p-8 text-center text-gray-500 dark:text-gray-400`}>{strings.no_reviews}</div>
                    )}

                    <div role="tablist" aria-label={strings.tabs_label} className="flex flex-wrap gap-x-7 border-b border-gray-300 dark:border-gray-600">
                        {tabs.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                role="tab"
                                id={`tab-${t.key}`}
                                aria-selected={tab === t.key}
                                aria-controls={`panel-${t.key}`}
                                onClick={() => setTab(t.key)}
                                className={`-mb-px inline-flex min-h-11 items-center gap-2 border-b-2 px-1 text-[15px] ${tab === t.key
                                    ? 'border-indigo-600 font-semibold text-gray-900 dark:border-indigo-400 dark:text-gray-100'
                                    : 'border-transparent font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'}`}
                            >
                                {t.label}
                                {t.count !== undefined && <span className="rounded-full bg-gray-200 px-2 text-xs font-semibold leading-[18px] text-gray-800 dark:bg-gray-700 dark:text-gray-200">{t.count}</span>}
                            </button>
                        ))}
                    </div>

                    <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
                        {tab === 'findings' && (current
                            ? <FindingsTab review={current} pr={pr} strings={strings} />
                            : <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_reviews}</p>)}
                        {tab === 'analysis' && (current
                            ? <AnalysisTab review={current} strings={strings} />
                            : <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_reviews}</p>)}
                        {tab === 'description' && <DescriptionTab pr={pr} strings={strings} />}
                        {tab === 'earlier' && <EarlierTab earlier={earlier} reviews={reviews} pr={pr} commits={commitHistory} strings={strings} />}
                    </div>
                </div>
            </div>
        </>
    );
}

function PrHeader({ pr, current, backUrl, strings }) {
    const open = pr.state === 'OPEN';
    const a = pr.approvals;
    const openFindings = current ? current.findings.filter((f) => !f.fixOutcome) : [];
    const count = (pred) => openFindings.filter(pred).length;
    const parts = current ? [
        [count((f) => f.level === 'CRITICAL'), strings.status_critical],
        [count((f) => f.level === 'IMPORTANT'), strings.status_important],
        [count((f) => f.requiresPoDecision), strings.status_po],
    ].filter(([n]) => n > 0).map(([n, text]) => text.replace(':n', n)) : [];
    const ci = open ? pr.ciState : null;
    const ciTone = {
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        running: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        success: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        waiting: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }[ci];
    // „CI fixen“ neben dem CI-Status; Konflikt/offene Threads als eigener Button.
    const resolveLabel = !open ? null
        : ci === 'failed' ? null
        : pr.conflicting ? strings.resolve_conflict
        : pr.unresolvedThreads > 0 ? strings.resolve_threads
        : null;
    const plural = (n, one, many) => (n === 1 ? one : many).replace(':n', n);
    const facts = [
        plural(pr.changedFiles, strings.meta_file, strings.meta_files),
        <><span className="text-green-700 dark:text-green-400">+{pr.additions}</span> <span className="text-red-700 dark:text-red-400">−{pr.deletions}</span></>,
        plural(pr.commits, strings.meta_commit, strings.meta_commits),
        plural(pr.comments, strings.meta_comment, strings.meta_comments),
        strings.meta_opened.replace(':date', formatDate(pr.openedAt)),
        pr.mergedAt && strings.meta_merged.replace(':date', formatDate(pr.mergedAt)),
        pr.closedAt && !pr.mergedAt && strings.meta_closed.replace(':date', formatDate(pr.closedAt)),
    ].filter(Boolean);

    return (
        <div className="space-y-2">
            <Link href={backUrl} className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">← {strings.back}</Link>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                    <StackedIcon pr={pr} strings={strings} className="h-5 w-5" />
                    <JiraTitle title={pr.title} />
                </h2>
                <HideToggle pr={pr} strings={strings} />
            </div>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <StateBadge state={pr.state} strings={strings} />
                <a href={pr.githubUrl} target="_blank" rel="noreferrer" className="font-mono hover:underline">{pr.repository}#{pr.number} ↗</a>
                <span className="font-mono [overflow-wrap:anywhere]">{pr.branch} → {pr.targetBranch}</span>
                {pr.author && <span>{pr.author}</span>}
                <span>{facts.map((f, i) => <React.Fragment key={i}>{i > 0 && ' · '}{f}</React.Fragment>)}</span>
            </div>
            <div className="flex flex-wrap items-center gap-2 pt-1">
                <span className={`${chip} ${a.fulfilled
                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'}`}
                >
                    {a.fulfilled ? strings.approvals_fulfilled : strings.approvals_missing} · {a.approved} / {a.required}
                </span>
                {ciTone && <span className={`${chip} ${ciTone}`}>{strings[`status_ci_${ci}`]}</span>}
                {ci === 'failed' && (
                    <a href={resolveCommandHref(pr)} title={strings.resolve_conflict_hint} className={`${smallButton} bg-red-600 text-white hover:bg-red-500`}>{strings.resolve_ci}</a>
                )}
                {current && (
                    <span className={`${chip} ${openFindings.length
                        ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                        : 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'}`}
                    >
                        {[openFindings.length
                            ? plural(openFindings.length, strings.status_open_finding, strings.status_open_findings)
                            : strings.status_no_open, ...parts].join(' · ')}
                    </span>
                )}
                {resolveLabel && (
                    <a href={resolveCommandHref(pr)} title={strings.resolve_conflict_hint} className={`${smallButton} bg-red-600 text-white hover:bg-red-500`}>{resolveLabel}</a>
                )}
            </div>
        </div>
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

// Neuestes Review mit älterem ps-review-Skill: Update-Aufforderung.
function SkillOutdatedNotice({ review, skill, strings }) {
    const text = (review.skillVersion ? strings.skill_outdated : strings.skill_outdated_unknown)
        .replace(':used', review.skillVersion ?? '')
        .replace(':current', skill.version);
    return (
        <div role="status" className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-5 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
            <p>{text}</p>
            <a href={skillUpdateCommandHref()} title={strings.skill_update_hint} className="inline-flex shrink-0 items-center rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">
                {strings.skill_update}
            </a>
        </div>
    );
}

function Chevron({ open }) {
    return (
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className={`mt-1 flex-none text-gray-500 transition-transform dark:text-gray-400 ${open ? 'rotate-90' : ''}`}>
            <path d="M9 6l6 6-6 6" />
        </svg>
    );
}

function ReviewMeta({ review, strings }) {
    const duration = formatDuration(review.startedAt, review.finishedAt);
    return (
        <span className="text-sm text-gray-500 dark:text-gray-400">
            {[
                formatDate(review.reviewedAt),
                review.commitSha?.slice(0, 7),
                review.reviewer,
                duration && `${strings.duration} ${duration}`,
            ].filter(Boolean).join(' · ')}
        </span>
    );
}

function Verdict({ review, strings }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="text-sm text-gray-600 dark:text-gray-400">{strings.verdict}</span>
            <DecisionBadge decision={review.decision} strings={strings} />
        </span>
    );
}

function PendingReview({ review, strings }) {
    return (
        <section id={`review-${review.id}`} className={`scroll-mt-4 ${CARD} px-5 py-3 flex flex-wrap items-center gap-3`}>
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

function InlineScores({ review, strings }) {
    return (
        <div className="flex flex-wrap items-center gap-x-8 gap-y-2">
            {[
                [strings.col_criticality, review.criticality, 'risk'],
                [strings.col_ai_reviewability, review.aiReviewability, 'ai'],
                [strings.col_po_review_likelihood, review.poReviewLikelihood, 'po'],
            ].map(([label, value, tone]) => (
                <span key={label} className="inline-flex items-center gap-2">
                    <span className="text-xs font-semibold text-gray-900 dark:text-gray-100">{label}</span>
                    <ScoreBar value={value} tone={tone} />
                </span>
            ))}
        </div>
    );
}

// Der Ticket-Abgleich steht in der Review-Karte statt unter „Analyse“.
const isTicketMatch = (section) => /^Ticket-Abgleich/i.test(section.title);

// Kennzahl als Zeile mit Zahl rechts und vollem Balken (Farblogik wie ScoreBar).
function ScoreMeter({ label, value, tone }) {
    const bad = tone === 'risk' ? value : 100 - value;
    const color = tone === 'po'
        ? (value >= 67 ? 'bg-indigo-600' : value >= 34 ? 'bg-indigo-400' : 'bg-indigo-200')
        : bad >= 67 ? 'bg-red-500' : bad >= 34 ? 'bg-amber-500' : 'bg-green-500';
    return (
        <div className="space-y-1">
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-[13.5px] font-semibold leading-5 text-gray-900 dark:text-gray-100">{label}</span>
                <span className="text-[15px] font-bold leading-5 text-gray-900 dark:text-gray-100">{value ?? '–'}</span>
            </div>
            {value !== null && value !== undefined && (
                <div role="img" aria-label={`${label} ${value}`} className="flex h-1.5 rounded bg-gray-200 dark:bg-gray-700">
                    <div className={`rounded ${color}`} style={{ width: `${value}%` }} />
                </div>
            )}
        </div>
    );
}

function CurrentReview({ review, pr, strings }) {
    const ticket = review.summarySections.find(isTicketMatch);
    const approve = review.decision === 'APPROVE';
    return (
        <>
            <section id={`review-${review.id}`} className={`scroll-mt-4 ${CARD} flex flex-wrap items-start justify-between gap-x-12 gap-y-5 px-6 pb-5 pt-4`}>
                <div className="min-w-0 max-w-[820px] flex-[999_1_30rem] space-y-3.5">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <h2 className="text-base font-bold text-gray-900 dark:text-gray-100">{strings.current_review}</h2>
                        <ReviewMeta review={review} strings={strings} />
                        {review.stale && <span className="text-xs text-amber-600 dark:text-amber-400">{strings.stale}</span>}
                        {review.convertedFrom && <span className="text-xs text-gray-500 dark:text-gray-400">{strings.converted_from.replace(':login', review.convertedFrom)}</span>}
                        <FixStatus fix={review.fix} pr={pr} strings={strings} />
                    </div>
                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span className={`rounded-md px-2.5 text-sm font-semibold leading-6 ${approve
                                ? 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-300'
                                : 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-300'}`}
                            >
                                {strings[`decision_${review.decision}`] ?? review.decision}
                            </span>
                            {review.tldrHeadlineHtml && <span className="text-lg font-semibold leading-7 text-gray-900 dark:text-gray-100" dangerouslySetInnerHTML={{ __html: review.tldrHeadlineHtml }} />}
                        </div>
                        {review.tldrHtml && <div className="text-[15px] leading-6"><Markdown html={review.tldrHtml} /></div>}
                    </div>
                </div>
                <div className="min-w-0 max-w-[300px] flex-[1_1_14rem] space-y-3.5 rounded-[10px] border border-gray-200 bg-gray-50 px-4 pb-4 pt-3.5 dark:border-gray-700 dark:bg-gray-900/40">
                    <ScoreMeter label={strings.col_criticality} value={review.criticality} tone="risk" />
                    <ScoreMeter label={strings.col_ai_reviewability} value={review.aiReviewability} tone="ai" />
                    <ScoreMeter label={strings.col_po_review_likelihood} value={review.poReviewLikelihood} tone="po" />
                </div>
            </section>
            {ticket && <TicketMatchCard section={ticket} strings={strings} />}
        </>
    );
}

// Ticket-Abgleich: „Weniger“ (−) und „Mehr als verlangt“ (+) nebeneinander in einem Kasten,
// Punkte mit Kennung fett darüber.
function TicketMatchCard({ section, strings }) {
    const t = section.ticket;
    return (
        <section className={`${CARD} space-y-3 px-6 pb-5 pt-4 text-[15px] leading-6`}>
            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 className="text-base font-bold text-gray-900 dark:text-gray-100">{t ? strings.ticket_match : section.title}</h2>
                {t?.key && (
                    <a href={JIRA_URL + t.key} target="_blank" rel="noreferrer" className="font-mono text-[13px] text-indigo-700 hover:underline dark:text-indigo-300">{t.key} ↗</a>
                )}
            </div>
            {t ? (
                <div className="flex flex-wrap items-start gap-x-12 gap-y-5 rounded-[10px] border border-gray-200 bg-gray-50 px-5 pb-[18px] pt-4 text-sm leading-[22px] dark:border-gray-700 dark:bg-gray-900/40 [&_code]:rounded [&_code]:border [&_code]:border-gray-200 [&_code]:bg-white [&_code]:px-1 [&_code]:font-mono [&_code]:text-[12.5px] dark:[&_code]:border-gray-700 dark:[&_code]:bg-gray-800">
                    <TicketMatchGroup kind="less" title={strings.ticket_less} items={t.less} className="flex-[3_1_28rem]" strings={strings} />
                    <TicketMatchGroup kind="more" title={strings.ticket_more} items={t.more} className="flex-[2_1_21rem]" strings={strings} />
                </div>
            ) : (
                <div className="max-w-[820px]"><Markdown html={section.html} /></div>
            )}
        </section>
    );
}

const TICKET_ICON = {
    less: { path: 'M7 12h10', tone: 'text-amber-700 dark:text-amber-400', circle: 'bg-amber-100 dark:bg-amber-900/40' },
    more: { path: 'M7 12h10M12 7v10', tone: 'text-blue-700 dark:text-blue-400', circle: 'bg-blue-100 dark:bg-blue-900/40' },
};

function TicketIcon({ kind, strokeWidth }) {
    return (
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={strokeWidth} strokeLinecap="round" className="flex-none">
            <path d={TICKET_ICON[kind].path} />
        </svg>
    );
}

function TicketMatchGroup({ kind, title, items, className, strings }) {
    const icon = TICKET_ICON[kind];
    return (
        <div className={`min-w-0 space-y-3 ${className}`}>
            <div className="flex items-center gap-2">
                <span className={`inline-flex h-6 w-6 items-center justify-center rounded-full ${icon.circle} ${icon.tone}`}><TicketIcon kind={kind} strokeWidth={3} /></span>
                <h3 className="font-bold text-gray-900 dark:text-gray-100">{title}</h3>
                <span className="rounded-full bg-gray-200 px-2 text-[12.5px] font-semibold leading-5 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{items.length}</span>
            </div>
            {items.length === 0 ? (
                <p className="pl-8 text-gray-500 dark:text-gray-400">{strings.ticket_nothing}</p>
            ) : (
                <ul className="space-y-2.5">
                    {items.map((item, i) => (
                        <li key={i} className="grid grid-cols-[24px_1fr] items-start gap-2">
                            <span className={`inline-flex justify-center pt-[3px] ${icon.tone}`}><TicketIcon kind={kind} strokeWidth={2.5} /></span>
                            <div className="min-w-0">
                                {item.leadHtml && <div className="font-semibold text-gray-900 dark:text-gray-100" dangerouslySetInnerHTML={{ __html: item.leadHtml }} />}
                                <div dangerouslySetInnerHTML={{ __html: item.html }} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

const FILTERS = [
    { key: 'all', label: 'filter_all', test: () => true },
    { key: 'CRITICAL', label: 'level_CRITICAL', test: (f) => f.level === 'CRITICAL' },
    { key: 'IMPORTANT', label: 'level_IMPORTANT', test: (f) => f.level === 'IMPORTANT' },
    { key: 'HINT', label: 'level_HINT', test: (f) => f.level === 'HINT' },
    { key: 'PO', label: 'filter_po', test: (f) => f.requiresPoDecision },
];

function FindingsTab({ review, pr, strings }) {
    const findings = review.findings;
    const [filter, setFilter] = useState('all');
    const [open, setOpen] = useState(() => new Set());
    const [fixing, setFixing] = useState(false);
    const visible = findings.filter(FILTERS.find((f) => f.key === filter).test);
    const allOpen = visible.length > 0 && visible.every((f) => open.has(f.id));
    const done = findings.filter((f) => f.fixOutcome).length;

    const toggle = (id) => setOpen((prev) => {
        const next = new Set(prev);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });
    const toggleAll = () => setOpen((prev) => {
        const next = new Set(prev);
        visible.forEach((f) => (allOpen ? next.delete(f.id) : next.add(f.id)));
        return next;
    });

    if (findings.length === 0) {
        return <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_findings}</p>;
    }

    return (
        <div className="space-y-2.5">
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2.5">
                <div className="flex flex-wrap gap-2">
                    {FILTERS.map((f) => {
                        const n = findings.filter(f.test).length;
                        if (f.key !== 'all' && n === 0) return null;
                        const active = filter === f.key;
                        return (
                            <button
                                key={f.key}
                                type="button"
                                aria-pressed={active}
                                onClick={() => setFilter(f.key)}
                                className={`min-h-9 rounded-full border px-3 text-sm font-medium ${active
                                    ? 'border-indigo-600 bg-indigo-50 text-indigo-800 dark:border-indigo-400 dark:bg-indigo-950/40 dark:text-indigo-200'
                                    : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'}`}
                            >
                                {strings[f.label]} <span className="font-bold">{n}</span>
                            </button>
                        );
                    })}
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span className="text-sm text-gray-600 dark:text-gray-400">{strings.findings_done.replace(':done', done).replace(':total', findings.length)}</span>
                    <button type="button" onClick={toggleAll} className="min-h-9 rounded-md border border-gray-300 bg-white px-3.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        {allOpen ? strings.collapse_all : strings.expand_all}
                    </button>
                    {review.fixUrl && (
                        <button type="button" onClick={() => setFixing(true)} title={strings.fix_hint} className="min-h-9 rounded-md bg-indigo-600 px-3.5 text-sm font-semibold text-white hover:bg-indigo-500">
                            {strings.fix}
                        </button>
                    )}
                </div>
            </div>
            {fixing && <FixDialog review={review} pr={pr} strings={strings} onClose={() => setFixing(false)} />}
            {visible.map((finding) => (
                <FindingRow key={finding.id} finding={finding} pr={pr} strings={strings} open={open.has(finding.id)} onToggle={() => toggle(finding.id)} />
            ))}
        </div>
    );
}

// Aufklappbare Befundzeile; readOnly bei überholten Reviews (ohne Fix-Buttons).
function FindingRow({ finding, pr, strings, open, onToggle, readOnly = false }) {
    const [fixing, setFixing] = useState(false);
    const fixable = !readOnly && finding.fixUrl;
    const place = finding.file ? `${finding.file.split('/').pop()}${finding.line ? `:${finding.line}` : ''}` : strings.summary_finding;
    return (
        <article id={`finding-${finding.id}`} className="relative scroll-mt-4 rounded-lg border border-gray-200 bg-white target:ring-2 target:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                className={`flex w-full flex-wrap items-start gap-x-3 gap-y-1 rounded-lg py-3.5 pl-4 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 ${fixable ? 'pr-28' : 'pr-4'}`}
            >
                <Chevron open={open} />
                <span className="w-[4.5rem] flex-none"><span className={`rounded px-2 py-0.5 text-[13px] font-medium ${LEVEL_TONE[finding.level]}`}>{strings[`level_${finding.level}`]}</span></span>
                <span className="flex min-w-0 flex-[1_1_16rem] flex-col gap-0.5">
                    <span className="font-semibold text-gray-900 dark:text-gray-100">{finding.title}</span>
                    <span className="font-mono text-xs text-gray-500 dark:text-gray-400 [overflow-wrap:anywhere]" title={finding.file ?? undefined}>{place}</span>
                </span>
                <span className="flex flex-none flex-wrap items-center gap-2.5 pt-px">
                    {finding.fixOutcome && <span className={`rounded px-2 py-0.5 text-[13px] font-medium ${FIX_TONE[finding.fixOutcome]}`}>{strings[`fix_outcome_${finding.fixOutcome}`]}</span>}
                    {finding.requiresPoDecision && <span className="rounded px-2 py-0.5 text-[13px] font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">{strings.filter_po}</span>}
                    {finding.confidence !== null && (
                        <span title={finding.confidenceReason || undefined} className="whitespace-nowrap text-[13px] text-gray-500 dark:text-gray-400">{strings.confidence} {finding.confidence} %</span>
                    )}
                </span>
            </button>
            {fixable && (
                <button type="button" onClick={() => setFixing(true)} title={strings.fix_finding_hint} className="absolute right-3.5 top-3 min-h-9 rounded-md bg-indigo-600 px-3.5 text-sm font-semibold text-white hover:bg-indigo-500">
                    {strings.fix_finding}
                </button>
            )}
            {fixing && <FindingFixDialog finding={finding} pr={pr} strings={strings} onClose={() => setFixing(false)} />}
            {open && (
                <div className="pb-5 pl-4 pr-5 sm:pl-12">
                    <FindingBody finding={finding} strings={strings} readOnly={!fixable} />
                    {finding.file && (
                        <a href={finding.fileUrl} target="_blank" rel="noreferrer" className="mt-3 inline-block font-mono text-xs text-gray-500 hover:underline dark:text-gray-400 [overflow-wrap:anywhere]">{finding.file}{finding.line ? `:${finding.line}` : ''} ↗</a>
                    )}
                    <FixDecision finding={finding} strings={strings} />
                </div>
            )}
        </article>
    );
}

// Links Was/Warum falsch/Fehlszenario, rechts die Vorschläge; schmal untereinander.
function FindingBody({ finding, strings, readOnly }) {
    const adopt = (note) => router.post(finding.fixUrl, { decision: 'FIX', note, start: false }, { preserveScroll: true });
    const hasSuggestions = finding.suggestions?.length > 0;
    const reason = finding.confidenceReason && (
        <p className="text-[13px] leading-5 text-gray-500 dark:text-gray-400">{strings.confidence}: {finding.confidenceReason}</p>
    );
    return (
        <div className={`grid items-start gap-x-10 gap-y-6 text-[15px] leading-6 ${hasSuggestions ? 'lg:grid-cols-2' : ''}`}>
            <div className="min-w-0 space-y-4">
                <FindingText finding={finding} />
                {!hasSuggestions && reason}
            </div>
            {hasSuggestions && (
                <div className="min-w-0 space-y-2.5">
                    <Suggestions
                        finding={finding}
                        adopted={finding.fixDecision === 'FIX' ? finding.fixNote : null}
                        strings={strings}
                        onAdopt={readOnly ? null : adopt}
                    />
                    {reason}
                </div>
            )}
        </div>
    );
}

function FindingText({ finding }) {
    return (
        <>
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
        </>
    );
}

function SummarySections({ review, skip = () => false }) {
    return review.summarySections.filter((section) => !skip(section)).map((section, i) => (
        <details key={i} className={`group ${CARD}`}>
            <summary className="flex cursor-pointer list-none items-start gap-3 rounded-lg px-4 py-3.5 font-semibold text-gray-900 marker:hidden dark:text-gray-100 [&::-webkit-details-marker]:hidden">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mt-1 flex-none text-gray-500 transition-transform group-open:rotate-90 dark:text-gray-400"><path d="M9 6l6 6-6 6" /></svg>
                <span>{section.title || '—'}</span>
            </summary>
            <div className="pb-4 pl-11 pr-5 text-sm"><Markdown html={section.html} /></div>
        </details>
    ));
}

function AnalysisTab({ review, strings }) {
    return (
        <div className="space-y-2.5">
            <section className={`${CARD} px-5 py-4`}>
                <h3 className="mb-3 font-bold text-gray-900 dark:text-gray-100">{strings.scores}</h3>
                <div className="grid gap-x-8 gap-y-4 sm:grid-cols-3">
                    {[
                        [strings.col_criticality, review.criticality, review.criticalityReason, 'risk'],
                        [strings.col_ai_reviewability, review.aiReviewability, review.aiReviewabilityReason, 'ai'],
                        [strings.col_po_review_likelihood, review.poReviewLikelihood, review.poReviewLikelihoodReason, 'po'],
                    ].map(([label, value, reason, tone]) => (
                        <div key={label} className="space-y-1.5">
                            <div className="flex items-center gap-2.5">
                                <span className="text-xs font-semibold text-gray-900 dark:text-gray-100">{label}</span>
                                <ScoreBar value={value} tone={tone} />
                            </div>
                            {reason && <p className="text-sm text-gray-600 dark:text-gray-300">{reason}</p>}
                        </div>
                    ))}
                </div>
            </section>
            <SummarySections review={review} skip={isTicketMatch} />
            {(review.model || review.strictness || review.thoroughness) && (
                <p className="px-1 text-xs text-gray-500 dark:text-gray-400">
                    {[
                        review.model && `${strings.model}: ${review.model}`,
                        review.strictness && `${strings.strictness}: ${review.strictness}`,
                        review.thoroughness && `${strings.thoroughness}: ${review.thoroughness}`,
                    ].filter(Boolean).join(' · ')}
                </p>
            )}
        </div>
    );
}

function DescriptionTab({ pr, strings }) {
    return (
        <div className="grid items-start gap-4 lg:grid-cols-3">
            <section className={`${CARD} p-5 lg:col-span-2`}>
                <h3 className="mb-2 font-bold text-gray-900 dark:text-gray-100">{strings.description}</h3>
                {pr.descriptionHtml
                    ? <div className="text-sm"><Markdown html={pr.descriptionHtml} /></div>
                    : <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_description}</p>}
            </section>
            <div className="space-y-4">
                <ApprovalPanel approvals={pr.approvals} strings={strings} />
                <section className={`${CARD} p-5`}>
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
            </div>
        </div>
    );
}

function EarlierTab({ earlier, reviews, pr, commits, strings }) {
    const [all, setAll] = useState(false);
    const shown = all ? earlier : earlier.slice(0, EARLIER_PAGE);
    return (
        <div className="space-y-2.5">
            <p className="text-sm text-gray-600 dark:text-gray-400">{strings.earlier_hint}</p>
            <PullRequestTimeline pr={pr} reviews={reviews} commits={commits} strings={strings} />
            {earlier.length === 0 && <p className="text-sm text-gray-500 dark:text-gray-400">{strings.no_earlier_reviews}</p>}
            {shown.map((review) => (review.status === 'finished'
                ? <EarlierReview key={review.id} review={review} pr={pr} strings={strings} />
                : <PendingReview key={review.id} review={review} strings={strings} />))}
            {!all && earlier.length > EARLIER_PAGE && (
                <button type="button" onClick={() => setAll(true)} className="min-h-9 rounded-md border border-gray-300 bg-white px-3.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    {strings.show_all_earlier.replace(':n', earlier.length)}
                </button>
            )}
        </div>
    );
}

function EarlierReview({ review, pr, strings }) {
    const [open, setOpen] = useState(false);
    const [openFindings, setOpenFindings] = useState(() => new Set());
    const toggle = (id) => setOpenFindings((prev) => {
        const next = new Set(prev);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });
    return (
        <section id={`review-${review.id}`} className={`scroll-mt-4 ${CARD}`}>
            <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full flex-wrap items-start gap-x-3 gap-y-1.5 rounded-lg py-3.5 pl-4 pr-5 text-left">
                <Chevron open={open} />
                <span className="font-semibold text-gray-900 dark:text-gray-100">{formatDate(review.reviewedAt)}</span>
                <Verdict review={review} strings={strings} />
                <span className="text-sm text-gray-500 dark:text-gray-400">
                    {[review.commitSha?.slice(0, 7), review.reviewer, formatDuration(review.startedAt, review.finishedAt) && `${strings.duration} ${formatDuration(review.startedAt, review.finishedAt)}`].filter(Boolean).join(' · ')}
                </span>
                <span className="ml-auto text-sm font-medium text-gray-800 dark:text-gray-200">{(review.findings.length === 1 ? strings.earlier_count_one : strings.earlier_count).replace(':n', review.findings.length)}</span>
            </button>
            {open && (
                <div className="space-y-3 pb-5 pl-4 pr-5 sm:pl-11">
                    {review.tldrHtml && <div className="text-sm"><Markdown html={review.tldrHtml} /></div>}
                    <InlineScores review={review} strings={strings} />
                    {review.findings.map((finding) => (
                        <FindingRow key={finding.id} finding={finding} pr={pr} strings={strings} open={openFindings.has(finding.id)} onToggle={() => toggle(finding.id)} readOnly />
                    ))}
                    <SummarySections review={review} />
                </div>
            )}
        </section>
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

// Vorschläge als Boxen; „So fixen“ speichert einen als Lösung (ohne Fix-Lauf).
function Suggestions({ finding, adopted, strings, onAdopt }) {
    if (!finding.suggestions?.length) return null;
    return (
        <div className="space-y-2.5">
            <div className="flex items-baseline justify-between gap-3">
                <h4 className="font-bold text-gray-900 dark:text-gray-100">{finding.suggestions.length > 1 ? strings.suggestions_heading : strings.suggestions}</h4>
                {hasPercent(finding.suggestions) && <span className="text-[13px] leading-5 text-gray-500 dark:text-gray-400">{strings.suggestions_percent_hint}</span>}
            </div>
            {finding.suggestions.map((option) => {
                const note = suggestionNote(option);
                const active = adopted?.trim() === note.trim();
                return (
                    <div
                        key={optionKey(option)}
                        className={`grid ${hasBadge(option) ? 'grid-cols-[3.5rem_1fr_auto] pl-3.5' : 'grid-cols-[1fr_auto] pl-4'} items-start gap-4 rounded-[10px] border-2 py-3.5 pr-3.5 ${active ? 'border-indigo-600 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/40' : 'border-transparent bg-gray-100 dark:bg-gray-900/60'}`}
                    >
                        <OptionBadge option={option} />
                        <div className="flex min-w-0 flex-col gap-1 [&_code]:border [&_code]:border-gray-200 [&_code]:bg-white dark:[&_code]:border-gray-700 dark:[&_code]:bg-gray-800">
                            {option.titleHtml && <span className="font-semibold text-gray-900 dark:text-gray-100" dangerouslySetInnerHTML={{ __html: option.titleHtml }} />}
                            <Markdown html={option.html} />
                        </div>
                        {onAdopt ? (
                            <button
                                type="button"
                                onClick={() => !active && onAdopt(note)}
                                aria-pressed={active}
                                title={withLetter(active ? strings.suggestion_adopted : strings.suggestion_adopt, option.letter)}
                                className={`inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-md border px-3.5 text-sm font-semibold ${active
                                    ? 'border-indigo-600 bg-white text-indigo-700 dark:border-indigo-400 dark:bg-gray-800 dark:text-indigo-300'
                                    : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'}`}
                            >
                                {active && <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>}
                                {active ? strings.suggestion_chosen : strings.suggestion_fix_this}
                            </button>
                        ) : <span />}
                    </div>
                );
            })}
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

// Vorausgewählt ist der Vorschlag mit der höchsten Konfidenz; ein altes „wie vorgeschlagen“ ebenso.
function initialChoice(finding) {
    if (finding.fixDecision && finding.fixDecision !== 'SUGGESTED') return { decision: finding.fixDecision, note: finding.fixNote ?? '' };
    const options = finding.suggestions ?? [];
    if (!options.length) return { decision: 'FIX', note: '' };
    const best = options.reduce((a, b) => ((b.percent ?? -1) > (a.percent ?? -1) ? b : a));
    return writeChoice(finding, optionKey(best), '');
}

// Pflicht nur, wo der Server Text verlangt: „Anders fixen“ bei PO-Befunden, „Nicht fixen“ mit Begründung.
function noteRequired(finding, choice, skipNeedsReason) {
    if (choice === 'FIX') return finding.requiresPoDecision;
    if (choice === 'SKIP') return finding.requiresPoDecision || skipNeedsReason;
    return false;
}

function missingNote(finding, data, skipNeedsReason) {
    const { choice, extra } = readChoice(finding, data);
    return !extra.trim() && noteRequired(finding, choice, skipNeedsReason);
}

// Bezeichner im Titel (bill_email, foo(), A::b, x.json) als Code.
function CodeTitle({ text }) {
    return text.split(/(\s+)/).map((part, i) => {
        const m = part.match(/^([„"'(]*)(.+?)([“"'),.;:!?]*)$/);
        const word = m?.[2] ?? part;
        return /^[\w$.\/:-]*(?:_\w|\(\)|::|\.(?:json|php|jsx?|tsx?|md|ya?ml))[\w$.\/:()-]*$/.test(word)
            ? <React.Fragment key={i}>{m[1]}<code className="rounded bg-gray-100 px-1 font-mono text-[14px] font-medium dark:bg-gray-700">{word}</code>{m[3]}</React.Fragment>
            : part;
    });
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
    const option = options.some((o) => optionKey(o) === choice);
    const required = noteRequired(finding, choice, skipNeedsReason);
    const name = `fix-choice-${finding.id}`;
    const pick = (value) => onData(writeChoice(finding, value, extra));
    const small = ['FIX', 'SKIP'];
    const noteLabel = option ? (po ? strings.fix_po_note : strings.fix_note) : skip ? strings.fix_skip_reason : strings.fix_instruction;
    const notePlaceholder = option ? (po ? strings.fix_po_note_placeholder : strings.fix_note_placeholder) : skip ? strings.fix_skip_reason_placeholder : strings.fix_instruction_placeholder;
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
                <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100"><CodeTitle text={finding.title} /></h3>
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
                    <div className="grid gap-2.5 sm:grid-cols-2">
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

            {choice !== 'SUGGESTED' && (
                <div className="space-y-1.5">
                    <div className="flex items-baseline justify-between gap-3">
                        <label htmlFor={`fix-note-${finding.id}`} className="font-semibold text-gray-900 dark:text-gray-100">
                            {noteLabel}
                        </label>
                        <span className="text-[13px] leading-5 text-gray-500 dark:text-gray-400">{required ? strings.fix_required : strings.fix_optional}</span>
                    </div>
                    <textarea
                        id={`fix-note-${finding.id}`}
                        rows={4}
                        value={extra}
                        required={required}
                        onChange={(e) => onData(writeChoice(finding, choice, e.target.value))}
                        placeholder={notePlaceholder}
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
