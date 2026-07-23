import React, { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import Flash from '../components/Flash.jsx';

// Task-Detailseite (ehemals tasks/show.blade.php + Partials). Sämtliches Parsing
// und das Markdown-Rendering passiert serverseitig (TaskShowPresenter); hier wird
// nur gerendert plus die Interaktivität nachgebaut: optimistisches Abhaken der
// Checkliste, aufklappbare Sektionen und der „Entscheidung mit Claude"-Wizard.

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// --- kleine Bausteine ------------------------------------------------------

function Markdown({ html }) {
    if (!html) return null;
    return <div className="md-content" dangerouslySetInnerHTML={{ __html: html }} />;
}

const CheckIcon = ({ className = 'h-3.5 w-3.5' }) => (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path d="M5 12l5 5l10 -10" /></svg>
);
const AlertIcon = ({ className = 'h-3.5 w-3.5' }) => (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /></svg>
);

function StatusBadge({ status }) {
    return <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + status.cls}>{status.label}</span>;
}

// Empfehlungs-Badge (Approve grün / Request-Changes gelb / sonst neutral).
function recBadgeClass(kind) {
    return kind === 'approve'
        ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'
        : kind === 'changes'
            ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300'
            : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400';
}

function RecBadge({ rec }) {
    return (
        <span className={'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ' + recBadgeClass(rec.kind)}>
            {rec.kind === 'approve' && <CheckIcon />}
            {rec.kind === 'changes' && <AlertIcon />}
            {rec.label}
        </span>
    );
}

// Aufklapp-Sektion, per URL-Hash verlinkbar (Spiegel der Alpine-disclosure).
function useDisclosure(id) {
    const [open, setOpen] = useState(false);
    useEffect(() => {
        if (id && window.location.hash === '#' + id) setOpen(true);
    }, [id]);
    const toggle = () => {
        setOpen((v) => {
            const next = !v;
            if (id) {
                if (next) history.replaceState(null, '', '#' + id);
                else if (window.location.hash === '#' + id) history.replaceState(null, '', window.location.pathname + window.location.search);
            }
            return next;
        });
    };
    return [open, toggle];
}

// --- Meta-Chips ------------------------------------------------------------

function MetaChips({ chips }) {
    return (
        <div className="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            {chips.map((c, i) => (
                <span key={i} className="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-1">
                    {c.label && <span className="text-gray-400 dark:text-gray-500">{c.label}</span>}
                    {c.href ? (
                        <a href={c.href} target="_blank" rel="noopener" className={'font-medium text-indigo-700 dark:text-indigo-400 hover:underline' + (c.mono ? ' font-mono' : '')}>{c.value}</a>
                    ) : (
                        <span className={'font-medium text-gray-700 dark:text-gray-300' + (c.mono ? ' font-mono' : '')}>{c.value}</span>
                    )}
                </span>
            ))}
        </div>
    );
}

// --- Concern-Banner + Entscheidungs-Wizard ---------------------------------

function ConcernBanner({ concern, strings, claudeLogoPath }) {
    const [wizardOpen, setWizardOpen] = useState(false);

    const destroy = () => {
        if (window.confirm(strings.removeConcern)) router.delete(concern.destroyUrl);
    };

    return (
        <div className="rounded-lg border border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-900/30 p-5">
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400">
                    <AlertIcon className="h-5 w-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="font-semibold text-orange-900 dark:text-orange-200">{concern.summary}</p>
                            <p className="text-xs text-orange-700/80 dark:text-orange-300/80">{concern.byName}</p>
                        </div>
                        {concern.canUpdate && (
                            <div className="flex items-center gap-3 text-sm">
                                <a href={concern.editUrl} className="font-medium text-orange-800 dark:text-orange-300 hover:underline">{strings.edit}</a>
                                <button type="button" onClick={destroy} className="font-medium text-red-600 dark:text-red-400 hover:underline">{strings.remove}</button>
                            </div>
                        )}
                    </div>

                    {concern.details.length > 0 && (
                        <div className="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
                            {concern.details.map((d) => (
                                <div key={d.key} className={d.wide ? 'sm:col-span-2' : ''}>
                                    <dt className="text-xs font-semibold uppercase tracking-wide text-orange-700/70 dark:text-orange-300/70">{d.label}</dt>
                                    <dd className="text-orange-900/90 dark:text-orange-200/90"><Markdown html={d.html} /></dd>
                                </div>
                            ))}
                        </div>
                    )}

                    {concern.decisions.length > 0 && (
                        <div className="mt-4">
                            <div className="mb-2 flex items-center justify-between gap-2">
                                <h4 className="text-xs font-semibold uppercase tracking-wide text-orange-700/70 dark:text-orange-300/70">{strings.openDecisions}</h4>
                                <button
                                    type="button"
                                    onClick={() => setWizardOpen(true)}
                                    className="inline-flex items-center gap-2 rounded-md bg-[#D97757] px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-[#c96544] focus:outline-none focus:ring-2 focus:ring-[#D97757] focus:ring-offset-1"
                                >
                                    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="currentColor" aria-hidden="true"><path d={claudeLogoPath} /></svg>
                                    {strings.findDecisionWithClaude}
                                </button>
                            </div>
                            <ul className="space-y-2">
                                {concern.decisions.map((d, i) => (
                                    <li key={i} className="text-sm">
                                        <span className="font-medium text-orange-900 dark:text-orange-200">{d.question}</span>
                                        {d.options.length > 0 && (
                                            <ul className="ms-4 list-disc text-orange-800/80 dark:text-orange-300/80">
                                                {d.options.map((o, j) => <li key={j}>{o}</li>)}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            {wizardOpen && (
                <DecisionsWizard config={concern.claudeConfig} strings={strings} claudeLogoPath={claudeLogoPath} onClose={() => setWizardOpen(false)} />
            )}
        </div>
    );
}

function DecisionsWizard({ config, strings, claudeLogoPath, onClose }) {
    const decisions = config.decisions;
    const total = decisions.length;
    const [step, setStep] = useState(0);
    const [answers, setAnswers] = useState({});
    const [custom, setCustom] = useState({});

    const value = (i) => {
        const c = (custom[i] || '').trim();
        if (c) return c;
        return answers[i] ?? null;
    };
    const done = step >= total;
    const current = decisions[step] || { question: '', options: [] };
    const answered = decisions.filter((d, i) => value(i) !== null).length;
    const canProceed = value(step) !== null;

    const choose = (opt) => {
        setAnswers((a) => ({ ...a, [step]: opt }));
        setCustom((c) => ({ ...c, [step]: '' }));
    };
    const next = () => { if (canProceed && step < total) setStep((s) => s + 1); };
    const prev = () => { if (step > 0) setStep((s) => s - 1); };

    const launch = () => {
        const lines = [
            strings.concernDecisionsIntro.replace(':ticket', config.alias + '/' + config.taskName),
            '',
            'Concern: ' + config.summary,
            'Ticket: ' + config.ticketUrl,
            '',
            strings.decisionsMade,
        ];
        decisions.forEach((d, i) => {
            lines.push((i + 1) + '. ' + d.question);
            lines.push('   → ' + (value(i) || strings.notSpecified));
        });
        lines.push('');
        lines.push(strings.implementTheseDecisions);

        const prompt = lines.join('\n');
        if (navigator.clipboard) navigator.clipboard.writeText(prompt).catch(() => {});
        window.location.href = 'claudetask:' + encodeURIComponent(prompt);
        onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
            <div className="w-full max-w-lg rounded-lg bg-white dark:bg-gray-800 shadow-xl">
                <div className="p-6">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D97757]/10">
                            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="#D97757" aria-hidden="true"><path d={claudeLogoPath} /></svg>
                        </span>
                        <div className="min-w-0">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.findDecisionWithClaude}</h3>
                            <p className="truncate text-xs text-gray-500 dark:text-gray-400">{config.summary}</p>
                        </div>
                    </div>

                    {!done ? (
                        <div>
                            <div className="mb-1 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                                <span>{strings.decision} {step + 1} {strings.of} {total}</span>
                                <span>{answered} {strings.answered}</span>
                            </div>
                            <div className="mb-5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div className="h-full rounded-full bg-[#D97757] transition-all" style={{ width: ((step + 1) / total * 100) + '%' }}></div>
                            </div>

                            <p className="mb-3 font-medium text-gray-900 dark:text-gray-100">{current.question}</p>

                            <div className="space-y-2">
                                {current.options.map((opt, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => choose(opt)}
                                        className={'flex w-full items-start gap-2 rounded-md border px-3 py-2 text-left text-sm transition ' +
                                            (answers[step] === opt
                                                ? 'border-[#D97757] bg-[#D97757]/5 text-gray-900 dark:text-gray-100 ring-1 ring-[#D97757]'
                                                : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50')}
                                    >
                                        <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-500 dark:text-gray-400">{String.fromCharCode(65 + i)}</span>
                                        <span>{opt}</span>
                                    </button>
                                ))}
                            </div>

                            <div className="mt-3">
                                <label className="mb-1 block text-xs text-gray-400 dark:text-gray-500">{current.options.length ? strings.orYourOwnAnswer : strings.answer}</label>
                                <input
                                    type="text"
                                    value={custom[step] || ''}
                                    onChange={(e) => { setCustom((c) => ({ ...c, [step]: e.target.value })); setAnswers((a) => ({ ...a, [step]: undefined })); }}
                                    placeholder={strings.enterYourOwnDecision}
                                    className="block w-full rounded-md border-gray-300 dark:border-gray-600 text-sm shadow-sm focus:border-[#D97757] focus:ring-[#D97757]"
                                />
                            </div>

                            <div className="mt-6 flex items-center justify-between">
                                {step > 0 ? (
                                    <button type="button" onClick={prev} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.back}</button>
                                ) : <span />}
                                <button type="button" onClick={next} disabled={!canProceed} className="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40">
                                    {step + 1 === total ? strings.toSummary : strings.next}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <p className="mb-3 text-sm text-gray-500 dark:text-gray-400">{strings.decisionsMadeReview}</p>
                            <ol className="mb-5 space-y-3">
                                {decisions.map((d, i) => (
                                    <li key={i} className="text-sm">
                                        <p className="font-medium text-gray-800 dark:text-gray-100">{(i + 1) + '. ' + d.question}</p>
                                        <div className="ms-4 flex items-center justify-between gap-3">
                                            <span className="text-[#a8492e]">→ {value(i) || strings.notSpecified}</span>
                                            <button type="button" className="shrink-0 text-xs text-indigo-600 dark:text-indigo-400 hover:underline" onClick={() => setStep(i)}>{strings.change}</button>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                            <div className="flex items-center justify-between">
                                <button type="button" onClick={() => setStep(total - 1)} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.back}</button>
                                <button type="button" onClick={launch} className="inline-flex items-center gap-2 rounded-md bg-[#D97757] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#c96544]">
                                    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="currentColor" aria-hidden="true"><path d={claudeLogoPath} /></svg>
                                    {strings.implementWithClaude}
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

// --- Beschreibung ----------------------------------------------------------

function Description({ description, strings }) {
    const [open, toggle] = useDisclosure('beschreibung');
    if (!description.html) return null;

    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-6" id={description.long ? 'beschreibung' : undefined}>
            <h3 className="mb-2 font-semibold text-gray-900 dark:text-gray-100">{strings.description}</h3>
            {description.long ? (
                <>
                    <div className={open ? '' : 'line-clamp-[8]'}><Markdown html={description.html} /></div>
                    <button type="button" onClick={toggle} className="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                        {open ? strings.showLess : strings.showMore}
                    </button>
                </>
            ) : (
                <Markdown html={description.html} />
            )}
        </section>
    );
}

// --- IST/SOLL --------------------------------------------------------------

function TargetActual({ ta, strings }) {
    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-3">{strings.actualTargetComparison}</h3>
            {ta.fallback ? (
                <Markdown html={ta.fallback} />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
                        <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-500 dark:text-gray-400">
                            <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v4" /><path d="M12 16h.01" /></svg>
                            {strings.actual} <span className="font-normal text-gray-400 dark:text-gray-500">{strings.before}</span>
                        </div>
                        <div className="text-sm text-gray-800 dark:text-gray-100">
                            {ta.ist ? <Markdown html={ta.ist} /> : <span className="text-gray-400 dark:text-gray-500">—</span>}
                        </div>
                    </div>
                    <div className="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 p-4">
                        <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-300">
                            <CheckIcon className="h-4 w-4" />
                            {strings.target} <span className="font-normal text-green-600/70 dark:text-green-400/70">{strings.after}</span>
                        </div>
                        <div className="text-sm text-gray-800 dark:text-gray-100">
                            {ta.soll ? <Markdown html={ta.soll} /> : <span className="text-gray-400 dark:text-gray-500">—</span>}
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}

// --- Checkliste ------------------------------------------------------------

function Checklist({ list, strings }) {
    if (!list.hasContent) return null;
    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            {list.mode === 'items' ? <ChecklistItems list={list} strings={strings} /> : <ChecklistProse list={list} strings={strings} />}
        </section>
    );
}

function ChecklistItems({ list, strings }) {
    const initChecked = () => {
        const m = {};
        list.items.forEach((i) => { if (i.checkable) m[i.id] = i.checked; });
        return m;
    };
    const [checked, setChecked] = useState(initChecked);
    const [done, setDone] = useState(list.done);
    const [flash, setFlash] = useState(null); // 'saved' | 'err'
    const timer = useRef(null);

    const ping = (kind) => {
        setFlash(kind);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => setFlash(null), kind === 'saved' ? 1500 : 3000);
    };

    const toggle = async (item) => {
        if (!list.canUpdate) return;
        const next = !checked[item.id];
        setChecked((c) => ({ ...c, [item.id]: next }));
        setDone((d) => d + (next ? 1 : -1));
        try {
            const res = await fetch(item.toggleUrl, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF(), 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ checked: next }),
            });
            if (!res.ok) throw new Error();
            ping('saved');
        } catch {
            setChecked((c) => ({ ...c, [item.id]: !next }));
            setDone((d) => d - (next ? 1 : -1));
            ping('err');
        }
    };

    const sectionLabels = { scope: strings.scope, done_when: strings.doneWhen, contract: strings.contract };
    let lastSection = null;
    let stepNo = 0;
    const rows = [];
    list.items.forEach((i) => {
        const isSection = Object.prototype.hasOwnProperty.call(sectionLabels, i.role);
        if (isSection && i.role !== lastSection) {
            rows.push({ type: 'section', label: sectionLabels[i.role], key: 's-' + i.role });
            lastSection = i.role;
        }
        if (i.checkable) {
            let num = null;
            let eye = false;
            if (list.kind === 'test' && i.role === 'expectation') eye = true;
            else if (list.kind === 'test') num = ++stepNo;
            rows.push({ type: 'check', item: i, num, eye, key: i.id });
        } else {
            rows.push({ type: 'ro', item: i, key: i.id });
        }
    });

    return (
        <div>
            <div className="mb-4 flex items-center justify-between gap-3">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{list.title}</h3>
                <div className="flex items-center gap-2 text-xs">
                    {flash === 'saved' && <span className="text-green-600 dark:text-green-400">{strings.saved}</span>}
                    {flash === 'err' && <span className="text-red-600 dark:text-red-400">{strings.errorNotSaved}</span>}
                    <span className="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-1 font-medium text-gray-600 dark:text-gray-400">
                        {done}/{list.total}{list.unit ? <>&nbsp;{list.unit}</> : null}
                    </span>
                </div>
            </div>

            <ul className="space-y-1.5">
                {rows.map((r) => {
                    if (r.type === 'section') {
                        return <li key={r.key} className="pt-2 first:pt-0 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{r.label}</li>;
                    }
                    if (r.type === 'ro') {
                        return (
                            <li key={r.key} className="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                <span className="min-w-0">{r.item.text}</span>
                            </li>
                        );
                    }
                    const it = r.item;
                    const isChecked = !!checked[it.id];
                    return (
                        <li key={r.key}>
                            <label className={'flex items-start gap-2.5 text-sm' + (list.canUpdate ? ' cursor-pointer' : '')}>
                                {r.eye && (
                                    <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300" title={strings.verificationStep}>
                                        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7s10 7 10 7s-3.5 7-10 7s-10-7-10-7z" /><circle cx="12" cy="12" r="2.5" /></svg>
                                    </span>
                                )}
                                {r.num != null && (
                                    <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-500 dark:text-gray-400">{r.num}</span>
                                )}
                                <input
                                    type="checkbox"
                                    checked={isChecked}
                                    disabled={!list.canUpdate}
                                    onChange={() => toggle(it)}
                                    className="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500"
                                />
                                <span className={'min-w-0 ' + (isChecked ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-800 dark:text-gray-100')}>{it.text}</span>
                            </label>
                        </li>
                    );
                })}
            </ul>

            {list.hints.length > 0 && (
                <div className="mt-4 space-y-1 border-t border-gray-100 dark:border-gray-700 pt-3">
                    {list.hints.map((h, i) => (
                        <p key={i} className="flex items-start gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span className="font-semibold text-gray-400 dark:text-gray-500">{strings.note}</span>
                            <span>{h}</span>
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}

function ChecklistProse({ list, strings }) {
    const convert = () => router.post(list.convertUrl, { kind: list.kind });

    return (
        <>
            <div className="mb-3 flex items-center justify-between gap-3">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{list.title}</h3>
                {list.canUpdate && list.parsed.length > 0 && (
                    <button type="button" onClick={convert} className="rounded-md bg-white dark:bg-gray-800 px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-200 dark:ring-indigo-900/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">{strings.convertToChecklist}</button>
                )}
            </div>
            {list.parsed.length > 0 ? (
                <ul className="space-y-1.5">
                    {list.parsed.map((p, i) => (
                        <li key={i} className="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                            <span className="min-w-0">{p}</span>
                        </li>
                    ))}
                </ul>
            ) : (
                <Markdown html={list.proseHtml} />
            )}
        </>
    );
}

// --- Review ----------------------------------------------------------------

function ReviewSection({ review, strings }) {
    const [open, toggle] = useDisclosure('review-analyse');
    const callout = review.kind === 'approve'
        ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 text-green-900 dark:text-green-200'
        : review.kind === 'changes'
            ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200'
            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300';

    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-purple-100 dark:border-purple-900/50">
            <div className="mb-3 flex items-center justify-between gap-3">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.review}</h3>
                <span className={'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ' + recBadgeClass(review.kind)}>
                    {review.kind === 'approve' && <CheckIcon />}
                    {review.kind === 'changes' && <AlertIcon />}
                    {review.label}
                </span>
            </div>

            {(review.tldr || review.hasRec) && (
                <div className={'rounded-lg border p-4 ' + callout}>
                    {review.tldr ? (
                        <p className="text-sm"><span className="font-semibold">{strings.tldr}</span> {review.tldr}</p>
                    ) : (
                        <p className="text-sm font-semibold">{review.label}</p>
                    )}
                </div>
            )}

            <dl className="mt-3 grid gap-4 sm:grid-cols-2 text-sm">
                <div><dt className="text-gray-400 dark:text-gray-500">{strings.reviewer}</dt><dd className="text-gray-800 dark:text-gray-100">{review.reviewer}</dd></div>
                <div><dt className="text-gray-400 dark:text-gray-500">{strings.lastReviewed}</dt><dd className="text-gray-800 dark:text-gray-100">{review.lastReviewed}</dd></div>
            </dl>

            {review.config && <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">{review.config}</p>}

            {review.summaryHtml && (
                <div className="mt-3" id="review-analyse">
                    <button type="button" onClick={toggle} className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                        {open ? strings.hideAnalysis : strings.showDetailedAnalysis}
                    </button>
                    {open && (
                        <div className="mt-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                            <Markdown html={review.summaryHtml} />
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}

// --- Timeline --------------------------------------------------------------

function Timeline({ items, strings }) {
    if (items.length === 0) return null;
    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-4">{strings.history}</h3>
            <ol className="relative space-y-4 border-l border-gray-200 dark:border-gray-700 pl-5">
                {items.map((e, i) => (
                    <li key={i} className="relative">
                        <span className="absolute -left-[1.4rem] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-white dark:border-gray-800 bg-gray-300 dark:bg-gray-600"></span>
                        <div className="flex flex-wrap items-baseline gap-x-2">
                            <span className="text-sm font-medium text-gray-800 dark:text-gray-100">{e.title}</span>
                            <span className="text-xs text-gray-400 dark:text-gray-500">{e.when}</span>
                        </div>
                        {e.body && <p className="text-sm text-gray-600 dark:text-gray-400">{e.body}</p>}
                    </li>
                ))}
            </ol>
        </section>
    );
}

// --- Sidebar: Voraussetzungen + Blockiert ----------------------------------

function Requirements({ requirements, strings }) {
    const Card = ({ title, entries, kind }) => (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
            <h3 className="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
            {entries.length === 0 ? (
                <p className="text-xs text-gray-400 dark:text-gray-500">{strings.none}</p>
            ) : (
                entries.map((e, i) => (
                    <a key={i} href={e.url} className="group flex items-start gap-2 py-1">
                        {kind === 'pre' ? (
                            e.done ? (
                                <CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-green-500 dark:text-green-400" />
                            ) : (
                                <span className="mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 border-gray-300 dark:border-gray-600"></span>
                            )
                        ) : (
                            <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                        )}
                        <span className="min-w-0">
                            <span className="font-mono text-xs font-semibold text-indigo-700 dark:text-indigo-400 group-hover:underline">{e.name}</span>
                            <span className="block truncate text-xs text-gray-500 dark:text-gray-400">{e.summary}</span>
                        </span>
                    </a>
                ))
            )}
        </div>
    );

    return (
        <>
            <Card title={strings.prerequisites} entries={requirements.prerequisites} kind="pre" />
            <Card title={strings.blocks} entries={requirements.dependents} kind="dep" />
        </>
    );
}

// --- Seite -----------------------------------------------------------------

export default function TaskShow(props) {
    const { project, task, header, metaChips, concern, concernCreateUrl, canUpdate, description, targetActual, checklists, review, timeline, requirements, claudeLogoPath, strings, flash } = props;
    const [tip, setTip] = useState(false);

    const claim = () => router.post(header.claimUrl);

    const pageHeader = (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <a href={project.showUrl} className="font-mono text-sm text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">{project.alias}</a>
                <span className="text-gray-300 dark:text-gray-600">/</span>
                <span className="rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-0.5 font-mono text-sm font-semibold text-gray-800 dark:text-gray-100">{task.name}</span>
                <StatusBadge status={task.status} />
                {task.recommendation && <RecBadge rec={task.recommendation} />}
                {task.criticality && (
                    <span title={strings.criticality} className={'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ' + task.criticality.badgeClasses}>
                        <AlertIcon />
                        {task.criticality.label}
                    </span>
                )}
            </div>
            <div className="flex items-center gap-2">
                {header.canClaim && (
                    header.releaseBlocked ? (
                        <span className="relative inline-block" onMouseEnter={() => setTip(true)} onMouseLeave={() => setTip(false)}>
                            <button type="button" disabled className="cursor-not-allowed rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-400 dark:text-gray-500 opacity-60 ring-1 ring-gray-300 dark:ring-gray-600">{strings.release}</button>
                            {tip && (
                                <span className="absolute right-0 top-full z-10 mt-1 w-56 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg">{strings.cannotReleaseWithConcern}</span>
                            )}
                        </span>
                    ) : (
                        <button type="button" onClick={claim} className="rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            {task.claimed ? strings.release : strings.claim}
                        </button>
                    )
                )}
                {header.canUpdate && (
                    <a href={header.editUrl} className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{strings.edit}</a>
                )}
            </div>
        </div>
    );

    return (
        <>
            <Head><title>{`${project.alias}/${task.name} · ${task.title}`}</title></Head>
            <PageBands header={pageHeader} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <Flash status={flash?.status} error={flash?.error} />

                    <div className="space-y-3">
                        <div>
                            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{task.title}</h1>
                            {task.subtitle && <p className="mt-0.5 font-mono text-sm text-gray-400 dark:text-gray-500">{task.subtitle}</p>}
                        </div>
                        <MetaChips chips={metaChips} />
                    </div>

                    {concern && <ConcernBanner concern={concern} strings={strings} claudeLogoPath={claudeLogoPath} />}

                    <div className="grid gap-6 lg:grid-cols-12">
                        <div className="space-y-6 lg:col-span-8">
                            <Description description={description} strings={strings} />
                            {targetActual && <TargetActual ta={targetActual} strings={strings} />}
                            {checklists.map((list) => <Checklist key={list.kind} list={list} strings={strings} />)}
                            {review && <ReviewSection review={review} strings={strings} />}
                            <Timeline items={timeline} strings={strings} />
                        </div>

                        <div className="space-y-4 self-start lg:col-span-4 lg:sticky lg:top-6">
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-5 space-y-3 text-sm">
                                <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.overview}</h3>
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-400 dark:text-gray-500">{strings.status}</span>
                                    <StatusBadge status={task.status} />
                                </div>
                                {task.criticality && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-400 dark:text-gray-500">{strings.criticality}</span>
                                        <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' + task.criticality.badgeClasses}>{task.criticality.label}</span>
                                    </div>
                                )}
                                {task.hasReview && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-400 dark:text-gray-500">{strings.review}</span>
                                        <span className={'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' + recBadgeClass(task.recommendation?.kind ?? 'other')}>
                                            {task.recommendation?.label ?? strings.pending}
                                        </span>
                                    </div>
                                )}
                                {task.spLabel && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-gray-400 dark:text-gray-500">{strings.effort}</span>
                                        <span className="text-gray-700 dark:text-gray-300">{task.spLabel}</span>
                                    </div>
                                )}
                            </div>

                            <Requirements requirements={requirements} strings={strings} />

                            {!task.concernOpen && (
                                <div className="flex items-center justify-between rounded-lg bg-white dark:bg-gray-800 px-5 py-3 text-xs text-gray-500 dark:text-gray-400 shadow">
                                    <span>{strings.noConcern}</span>
                                    {canUpdate && (
                                        <a href={concernCreateUrl} className="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{strings.create}</a>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

TaskShow.layout = AppShell;
