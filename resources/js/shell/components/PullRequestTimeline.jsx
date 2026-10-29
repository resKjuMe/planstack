import React from 'react';
import { formatDate, formatDuration } from './PullRequestParts.jsx';

// Verlauf der letzten höchstens 30 Tage als divergierende Balken je Review: nach oben die offenen
// Befunde, nach unten die seit dem vorigen Review gefixten. Commits sitzen auf der Nulllinie.

const W = 1000;
const X0 = 60;
const X1 = W - 20;
const PLOT_TOP = 24;
const PLOT_BOTTOM = 250;
const AXIS_Y = 268;
const H = 292;
const BAR = 24;
const LABEL_GAP = 120;
const WINDOW = 30 * 86_400_000;

// Von der Nulllinie nach außen: oben Hinweis zuerst, unten Kritisch zuerst.
const LEVELS = [
    { key: 'critical', level: 'CRITICAL', fill: 'fill-red-500 dark:fill-red-400' },
    { key: 'important', level: 'IMPORTANT', fill: 'fill-amber-400 dark:fill-amber-500' },
    { key: 'hint', level: 'HINT', fill: 'fill-gray-400 dark:fill-gray-500' },
];

const time = (iso) => (iso ? new Date(iso).getTime() : null);
const shortDate = (ms) => new Date(ms).toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' });
const sum = (counts) => counts.critical + counts.important + counts.hint;

// Gefixt: was der Fix-Lauf am vorigen Review als behoben gemeldet hat; ohne Fix-Lauf
// der Rückgang je Stufe gegenüber dem vorigen Review.
function fixedSince(previous, current) {
    if (!previous) return { critical: 0, important: 0, hint: 0 };
    if (previous.findings.some((f) => f.fixOutcome)) {
        const fixed = previous.findings.filter((f) => f.fixOutcome === 'FIXED');
        return Object.fromEntries(LEVELS.map((l) => [l.key, fixed.filter((f) => f.level === l.level).length]));
    }
    return Object.fromEntries(LEVELS.map((l) => [l.key, Math.max(0, previous[l.key] - current[l.key])]));
}

function niceStep(max) {
    const raw = max / 3;
    const pow = 10 ** Math.floor(Math.log10(Math.max(raw, 1)));
    return [1, 2, 5, 10].map((m) => m * pow).find((s) => s >= raw) ?? 10 * pow;
}

export default function PullRequestTimeline({ pr, reviews, commits, strings }) {
    const end = time(pr.mergedAt) ?? time(pr.closedAt) ?? Date.now();
    const start = end - WINDOW;
    // Gefixt wird vor dem Zuschneiden berechnet, damit das erste sichtbare Review seinen Vorgänger kennt.
    const allRuns = reviews
        .filter((r) => r.status === 'finished' && r.reviewedAt)
        .map((r) => ({ ...r, t: time(r.reviewedAt) }))
        .sort((a, b) => a.t - b.t)
        .map((r, i, all) => ({ ...r, first: i === 0, latest: i === all.length - 1, fixed: fixedSince(all[i - 1], r) }));
    const runs = allRuns.filter((r) => r.t >= start);
    const points = (commits ?? [])
        .map((c) => ({ ...c, t: time(c.committedAt) }))
        .filter((c) => c.t >= start)
        .sort((a, b) => a.t - b.t);

    const stamps = [Math.max(time(pr.openedAt) ?? start, start), end, ...runs.map((r) => r.t), ...points.map((c) => c.t)];
    let from = Math.min(...stamps);
    let to = Math.max(...stamps);
    if (to - from < 3_600_000) {
        from -= 1_800_000;
        to += 1_800_000;
    }
    const pad = (to - from) * 0.03;
    from -= pad;
    to += pad;
    const x = (t) => X0 + ((t - from) / (to - from)) * (X1 - X0);

    // Unter der Nulllinie bleibt immer Platz für die Gefixt-Beschriftung.
    const up = Math.max(1, ...runs.map(sum));
    const down = Math.max(up / 3, 1, ...runs.map((r) => sum(r.fixed)));
    const unit = (PLOT_BOTTOM - PLOT_TOP) / (up + down);
    const zero = PLOT_TOP + up * unit;
    const step = niceStep(up);
    const grid = [
        ...Array.from({ length: Math.floor(up / step) }, (_, i) => (i + 1) * step),
        ...Array.from({ length: Math.floor(down / step) }, (_, i) => -(i + 1) * step),
    ];

    const ticks = Array.from({ length: 5 }, (_, i) => from + ((to - from) * i) / 4);
    const tickLabel = (t) => (to - from < 2 * 86_400_000
        ? new Date(t).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
        : shortDate(t));

    // Beschriftung nur, wenn sie nicht in die vorige ragt; die Details stehen im Tooltip.
    let lastRun = -Infinity;
    const runLabels = runs.map((r) => {
        const show = x(r.t) - lastRun >= LABEL_GAP;
        if (show) lastRun = x(r.t);
        return show;
    });
    let lastCommit = -Infinity;
    const commitLabels = points.map((c) => {
        const show = x(c.t) - lastCommit >= 70 && !runs.some((r) => Math.abs(x(r.t) - x(c.t)) < 60);
        if (show) lastCommit = x(c.t);
        return show;
    });
    const anchorEnd = (t) => x(t) > X1 - LABEL_GAP;
    const breakdown = (counts) => LEVELS.filter((l) => counts[l.key])
        .map((l) => `${counts[l.key]} ${strings[`level_${l.level}`].toLocaleLowerCase()}`)
        .join(' · ');

    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.timeline_title}</h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{strings.timeline_hint}</p>
                </div>
                <div className="flex gap-3 text-xs text-gray-600 dark:text-gray-300">
                    {LEVELS.map((l) => (
                        <span key={l.key} className="inline-flex items-center gap-1.5">
                            <svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" rx="2" className={l.fill} /></svg>
                            {strings[`level_${l.level}`]}
                        </span>
                    ))}
                    <span className="inline-flex items-center gap-1.5 ml-2">
                        <svg width="12" height="12" aria-hidden="true"><circle cx="6" cy="6" r="4.5" strokeWidth="2" className="fill-white stroke-indigo-600 dark:fill-gray-800 dark:stroke-indigo-400" /></svg>
                        {strings.timeline_commit}
                    </span>
                </div>
            </div>

            <div className="mt-3 overflow-x-auto">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className="w-full min-w-[640px] text-gray-700 dark:text-gray-300"
                    role="img"
                    aria-label={strings.timeline_title}
                >
                    <text x={X0} y="12" fontSize="11" fontWeight="500" className="fill-gray-500 dark:fill-gray-400">{strings.timeline_open_axis}</text>
                    <text x={X0} y={PLOT_BOTTOM + 4} fontSize="11" fontWeight="500" className="fill-gray-500 dark:fill-gray-400">{strings.timeline_fixed_axis}</text>

                    <g strokeDasharray="2 4" className="stroke-gray-200 dark:stroke-gray-700">
                        {grid.map((v) => <line key={v} x1={X0} x2={X1} y1={zero - v * unit} y2={zero - v * unit} />)}
                    </g>
                    <g fontSize="11" textAnchor="end" className="fill-gray-500 dark:fill-gray-400">
                        {grid.map((v) => <text key={v} x={X0 - 10} y={zero - v * unit + 4}>{Math.abs(v)}</text>)}
                    </g>

                    {runs.map((r, i) => {
                        const left = x(r.t) - BAR / 2;
                        const open = sum(r);
                        const fixed = sum(r.fixed);
                        const end = anchorEnd(r.t);
                        const lx = end ? left - 8 : left + BAR + 8;
                        const anchor = end ? 'end' : 'start';
                        let yUp = zero;
                        let yDown = zero;
                        const note = r.first ? strings.timeline_first : r.latest ? strings.timeline_latest : null;
                        return (
                            <g key={r.id}>
                                <title>
                                    {`${formatDate(r.reviewedAt)}${r.commitSha ? ` · ${r.commitSha.slice(0, 7)}` : ''}${formatDuration(r.startedAt, r.finishedAt) ? ` · ${formatDuration(r.startedAt, r.finishedAt)}` : ''}\n`
                                        + `${strings.timeline_open.replace(':count', open)}${open ? `: ${breakdown(r)}` : ''}\n`
                                        + `${strings.timeline_fixed.replace(':count', fixed)}${fixed ? `: ${breakdown(r.fixed)}` : ''}`}
                                </title>
                                {[...LEVELS].reverse().map((l) => {
                                    const h = r[l.key] * unit;
                                    if (!h) return null;
                                    yUp -= h;
                                    return <rect key={`o-${l.key}`} x={left} y={yUp} width={BAR} height={h} className={l.fill} />;
                                })}
                                {LEVELS.map((l) => {
                                    const h = r.fixed[l.key] * unit;
                                    if (!h) return null;
                                    yDown += h;
                                    return <rect key={`f-${l.key}`} x={left} y={yDown - h} width={BAR} height={h} opacity="0.4" className={l.fill} />;
                                })}
                                {open === 0 && <rect x={left} y={zero - 3} width={BAR} height="3" rx="1.5" className="fill-green-500 dark:fill-green-400" />}
                                {runLabels[i] && (
                                    <>
                                        <text x={lx} y={Math.max(PLOT_TOP, yUp) + 10} fontSize="11" textAnchor={anchor}>
                                            <tspan fontWeight="600" className="fill-current">{strings.timeline_open.replace(':count', open)}</tspan>
                                            <tspan x={lx} dy="14" fontSize="10" className="fill-gray-500 dark:fill-gray-400">
                                                {note ? `${shortDate(r.t)} · ${note}` : shortDate(r.t)}
                                            </tspan>
                                        </text>
                                        {fixed > 0 && (
                                            <text x={lx} y={zero + 14} fontSize="11" textAnchor={anchor}>
                                                <tspan fontWeight="600" className="fill-green-700 dark:fill-green-400">{strings.timeline_fixed.replace(':count', fixed)}</tspan>
                                                <tspan x={lx} dy="14" fontSize="10" className="fill-gray-500 dark:fill-gray-400">{breakdown(r.fixed)}</tspan>
                                            </text>
                                        )}
                                    </>
                                )}
                            </g>
                        );
                    })}

                    <line x1={X0} x2={X1} y1={zero} y2={zero} strokeWidth="1.5" className="stroke-gray-700 dark:stroke-gray-300" />

                    {!commits && (
                        <text x={X1} y={zero - 8} fontSize="11" textAnchor="end" className="fill-gray-400 dark:fill-gray-500">{strings.timeline_loading}</text>
                    )}
                    {points.map((c, i) => (
                        <g key={c.sha}>
                            <title>{`${c.sha.slice(0, 7)} · ${formatDate(c.committedAt)} · +${c.additions} −${c.deletions}\n${c.message}`}</title>
                            <circle cx={x(c.t)} cy={zero} r="6" strokeWidth="2" className="fill-white stroke-indigo-600 dark:fill-gray-800 dark:stroke-indigo-400" />
                            {commitLabels[i] && (
                                <text
                                    x={anchorEnd(c.t) ? x(c.t) - 10 : x(c.t) + 10}
                                    y={zero - 6}
                                    fontSize="10"
                                    fontFamily="ui-monospace, Menlo, monospace"
                                    textAnchor={anchorEnd(c.t) ? 'end' : 'start'}
                                    className="fill-indigo-600 dark:fill-indigo-400"
                                >
                                    {c.sha.slice(0, 7)}
                                </text>
                            )}
                        </g>
                    ))}

                    <line x1={X0} x2={X1} y1={AXIS_Y} y2={AXIS_Y} className="stroke-gray-300 dark:stroke-gray-600" />
                    {ticks.map((t, i) => (
                        <text
                            key={t}
                            x={x(t)}
                            y={H - 4}
                            fontSize="11"
                            textAnchor={i === 0 ? 'start' : i === ticks.length - 1 ? 'end' : 'middle'}
                            className="fill-gray-500 dark:fill-gray-400"
                        >
                            {tickLabel(t)}
                        </text>
                    ))}
                </svg>
            </div>
        </section>
    );
}
