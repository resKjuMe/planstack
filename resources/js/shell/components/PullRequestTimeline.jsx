import React, { useState } from 'react';
import { formatDuration } from './PullRequestParts.jsx';

// Verlauf als divergierende Balken auf einer Ereignis-Achse: ein Slot je Review (offen nach
// oben, seit dem vorigen Review gefixt nach unten), Tage ohne Review gestaucht, Commits
// gebündelt in eigener Spur mit summierten Zeilen; Merges aus dem Ziel-Branch grau ohne Zahlen.

const W = 1000;
const X0 = 70;
const X1 = W - 16;
const PLOT_TOP = 44;
const PLOT_BOTTOM = 222;
const LANE_TOP = 236;
const LANE_H = 48;
const AXIS_Y = 300;
const H = 342;
const RANGES = [7, 30, null];
const DAY = 86_400_000;

// Slot-Breiten in Einheiten, auf die Plotbreite skaliert.
const UNITS = { review: 1, day: 0.6, gap: 0.45, tail: 0.5 };

// Von der Nulllinie nach außen: oben Hinweis zuerst, unten PO-Entscheidung zuerst.
// Eine PO-Entscheidung zählt vor der Stufe des Befunds.
const LEVELS = [
    { key: 'po', label: 'timeline_po', fill: 'fill-blue-500 dark:fill-blue-400' },
    { key: 'critical', level: 'CRITICAL', label: 'level_CRITICAL', fill: 'fill-red-500 dark:fill-red-400' },
    { key: 'important', level: 'IMPORTANT', label: 'level_IMPORTANT', fill: 'fill-amber-400 dark:fill-amber-500' },
    { key: 'hint', level: 'HINT', label: 'level_HINT', fill: 'fill-gray-400 dark:fill-gray-500' },
];

const time = (iso) => (iso ? new Date(iso).getTime() : null);
const dayStart = (ms) => new Date(ms).setHours(0, 0, 0, 0);
const dayDiff = (a, b) => Math.round((dayStart(b) - dayStart(a)) / DAY);
const shortDate = (ms) => new Date(ms).toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' });
const clock = (ms) => new Date(ms).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
const sum = (counts) => LEVELS.reduce((n, l) => n + counts[l.key], 0);
const countFindings = (findings) => Object.fromEntries(LEVELS.map((l) => [
    l.key,
    findings.filter((f) => (l.key === 'po' ? f.requiresPoDecision : !f.requiresPoDecision && f.level === l.level)).length,
]));

// Gefixt: was der Fix-Lauf am vorigen Review als behoben gemeldet hat; ohne Fix-Lauf
// der Rückgang je Stufe gegenüber dem vorigen Review.
function fixedSince(previous, current) {
    if (!previous) return countFindings([]);
    if (previous.findings.some((f) => f.fixOutcome)) {
        return countFindings(previous.findings.filter((f) => f.fixOutcome === 'FIXED'));
    }
    return Object.fromEntries(LEVELS.map((l) => [l.key, Math.max(0, previous[l.key] - current[l.key])]));
}

function niceStep(max) {
    const raw = max / 3;
    const pow = 10 ** Math.floor(Math.log10(Math.max(raw, 1)));
    return [1, 2, 5, 10].map((m) => m * pow).find((s) => s >= raw) ?? 10 * pow;
}

// Slots von links nach rechts: Commit-Tage vor dem ersten Review, Lücken, Reviews,
// Commits nach dem letzten Review. Commits zwischen zwei Reviews hängen als Bündel davor.
function layout(runs, points) {
    const slots = [];
    const first = runs[0]?.t ?? Infinity;
    const gap = (from, to, bundle = null) => {
        const days = dayDiff(from, to) - 1;
        if (days > 0 || bundle) slots.push({ kind: 'gap', days: Math.max(0, days), bundle });
    };

    let lastDay = null;
    for (const c of points.filter((p) => p.t < first)) {
        if (lastDay !== null && dayStart(c.t) === lastDay) {
            slots[slots.length - 1].bundle.push(c);
            continue;
        }
        if (lastDay !== null) gap(lastDay, c.t);
        slots.push({ kind: 'day', t: c.t, bundle: [c] });
        lastDay = dayStart(c.t);
    }
    if (lastDay !== null && runs.length) gap(lastDay, first);

    runs.forEach((r, i) => {
        if (i > 0) {
            const between = points.filter((p) => p.t >= runs[i - 1].t && p.t < r.t);
            if (dayDiff(runs[i - 1].t, r.t) > 1) gap(runs[i - 1].t, r.t, between);
            else if (between.length) slots.push({ kind: 'boundary', bundle: between });
        }
        slots.push({ kind: 'review', run: r });
    });

    const after = runs.length ? points.filter((p) => p.t >= runs[runs.length - 1].t) : [];
    if (after.length) slots.push({ kind: 'tail', bundle: after });

    const total = slots.reduce((s, slot) => s + (UNITS[slot.kind] ?? 0), 0) || 1;
    const scale = (X1 - X0) / total;
    let cursor = X0;
    for (const slot of slots) {
        const w = (UNITS[slot.kind] ?? 0) * scale;
        slot.x = cursor;
        slot.w = w;
        slot.cx = cursor + w / 2;
        cursor += w;
    }

    return { slots, unitWidth: scale };
}

export default function PullRequestTimeline({ pr, reviews, commits, strings }) {
    const [range, setRange] = useState(30);
    const [tip, setTip] = useState(null);

    const end = time(pr.mergedAt) ?? time(pr.closedAt) ?? Date.now();
    const start = range ? end - range * DAY : -Infinity;
    // Gefixt wird vor dem Zuschneiden berechnet, damit das erste sichtbare Review seinen Vorgänger kennt.
    const allRuns = reviews
        .filter((r) => r.status === 'finished' && r.reviewedAt)
        .map((r) => ({ ...r, ...countFindings(r.findings), t: time(r.reviewedAt) }))
        .sort((a, b) => a.t - b.t)
        .map((r, i, all) => ({ ...r, first: i === 0, latest: i === all.length - 1, previous: all[i - 1] ?? null, fixed: fixedSince(all[i - 1], r) }));
    const runs = allRuns.filter((r) => r.t >= start);
    const points = (commits ?? [])
        .map((c) => ({ ...c, t: time(c.committedAt) }))
        .filter((c) => c.t >= start)
        .sort((a, b) => a.t - b.t);

    const { slots, unitWidth } = layout(runs, points);
    const bar = Math.min(32, unitWidth * 0.36);

    // Unter der Nulllinie bleibt immer Platz für die Gefixt-Beschriftung.
    const up = Math.max(1, ...runs.map(sum));
    const down = Math.max(up / 3, 1, ...runs.map((r) => sum(r.fixed)));
    const unit = (PLOT_BOTTOM - PLOT_TOP - 20) / (up + down);
    const zero = PLOT_TOP + up * unit;
    const step = niceStep(up);
    const grid = [
        ...Array.from({ length: Math.floor(up / step) }, (_, i) => (i + 1) * step),
        ...Array.from({ length: Math.floor(down / step) }, (_, i) => -(i + 1) * step),
    ];

    const reviewSlots = slots.filter((s) => s.kind === 'review');
    const firstReview = reviewSlots[0];
    const latestSlot = reviewSlots.find((s) => s.run.latest);
    // Datum je Tag einmal, mittig über dessen Reviews.
    const reviewDays = Object.values(reviewSlots.reduce((days, s) => {
        const key = dayStart(s.run.t);
        (days[key] ??= { t: s.run.t, xs: [] }).xs.push(s.cx);
        return days;
    }, {}));

    const levelText = (counts) => LEVELS.map((l) => `${counts[l.key]} ${strings[l.label].toLocaleLowerCase()}`).join(' · ');
    const reviewTip = (s) => {
        const r = s.run;
        const duration = formatDuration(r.startedAt, r.finishedAt);
        const lines = [
            { text: `${strings.timeline_review_tip.replace(':time', `${shortDate(r.t)} ${clock(r.t)}`)}${duration ? ` · ${duration}` : ''}`, strong: true },
            { text: levelText(r) },
        ];
        if (r.previous) {
            const by = points.filter((p) => !p.merge && p.t >= r.previous.t && p.t < r.t).length;
            lines.push({
                text: strings.timeline_fixed_by.replace(':fixed', sum(r.fixed)).replace(':commits', by).replace(':time', clock(r.previous.t)),
                tone: 'good',
            });
        }
        return { x: s.cx, y: Math.min(zero + 100, H - 4), lines };
    };
    const bundleTip = (x, bundle) => ({
        x,
        y: LANE_TOP - 6,
        lines: [
            ...bundle.slice(0, 8).map((c) => ({
                text: `${c.sha.slice(0, 7)}  ${c.merge ? `${target} ↓` : `+${c.additions} −${c.deletions}`}  ${clock(c.t)}`,
                mono: true,
            })),
            ...(bundle.length > 8 ? [{ text: strings.timeline_more.replace(':count', bundle.length - 8) }] : []),
        ],
    });

    const jump = (id) => document.getElementById(`review-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const bundleX = (s) => (s.kind === 'boundary' ? s.x : s.cx);
    const bundles = slots.filter((s) => s.bundle?.length);
    const target = pr.targetBranch || 'main';

    const marker = (key, x, list, merge) => {
        const cy = LANE_TOP + 16;
        const tone = merge
            ? 'fill-gray-100 stroke-gray-400 dark:fill-gray-700 dark:stroke-gray-500'
            : 'fill-white stroke-indigo-600 dark:fill-gray-800 dark:stroke-indigo-400';
        const tipFor = () => setTip(bundleTip(x, list));
        return (
            <g key={key} tabIndex={0} className="outline-none" onMouseEnter={tipFor} onFocus={tipFor} onBlur={() => setTip(null)}>
                {list.length === 1 ? (
                    <circle cx={x} cy={cy} r="7" strokeWidth="1.5" className={tone} />
                ) : (
                    <>
                        <rect x={x - 19} y={cy - 10} width="38" height="20" rx="10" strokeWidth="1.5" className={tone} />
                        <text x={x} y={cy + 4} fontSize="11" fontWeight="600" textAnchor="middle" className={merge ? 'fill-gray-600 dark:fill-gray-300' : 'fill-indigo-800 dark:fill-indigo-300'}>{list.length}</text>
                    </>
                )}
                <text x={x} y={LANE_TOP + 40} fontSize="10" fontFamily="ui-monospace, Menlo, monospace" textAnchor="middle">
                    {merge ? (
                        <tspan className="fill-gray-500 dark:fill-gray-400">{`${target} ↓`}</tspan>
                    ) : (
                        <>
                            <tspan className="fill-green-700 dark:fill-green-400">{`+${list.reduce((n, c) => n + c.additions, 0)}`}</tspan>
                            <tspan className="fill-red-700 dark:fill-red-400">{` −${list.reduce((n, c) => n + c.deletions, 0)}`}</tspan>
                        </>
                    )}
                </text>
            </g>
        );
    };

    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.timeline_title}</h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{strings.timeline_hint}</p>
                </div>
                <div className="flex flex-wrap items-center gap-4">
                    <div role="group" aria-label={strings.timeline_range} className="inline-flex overflow-hidden rounded-md border border-gray-300 dark:border-gray-600 text-xs">
                        {RANGES.map((days, i) => (
                            <button
                                key={days ?? 'all'}
                                type="button"
                                aria-pressed={range === days}
                                onClick={() => setRange(days)}
                                className={`px-2.5 py-1.5 ${i ? 'border-l border-gray-300 dark:border-gray-600' : ''} ${range === days
                                    ? 'bg-indigo-50 text-indigo-800 font-medium dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'}`}
                            >
                                {days ? strings.timeline_days.replace(':count', days) : strings.timeline_since_open}
                            </button>
                        ))}
                    </div>
                    <div className="flex gap-3 text-xs text-gray-600 dark:text-gray-300">
                        {LEVELS.map((l) => (
                            <span key={l.key} className="inline-flex items-center gap-1.5">
                                <svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" rx="2" className={l.fill} /></svg>
                                {strings[l.label]}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            <div className="mt-3 overflow-x-auto">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className="w-full min-w-[640px] text-gray-700 dark:text-gray-300"
                    role="img"
                    aria-label={strings.timeline_title}
                    onMouseLeave={() => setTip(null)}
                >
                    {firstReview && (
                        <rect x={firstReview.x} y="8" width={X1 - firstReview.x} height={PLOT_BOTTOM + 4} rx="6" className="fill-gray-50 dark:fill-gray-900/40" />
                    )}
                    {latestSlot && (
                        <rect x={latestSlot.x + 2} y="8" width={latestSlot.w - 4} height={PLOT_BOTTOM + 4} rx="6" className="fill-indigo-50 dark:fill-indigo-900/30" />
                    )}

                    <g strokeDasharray="2 4" className="stroke-gray-200 dark:stroke-gray-700">
                        {grid.map((v) => <line key={v} x1={X0} x2={X1} y1={zero - v * unit} y2={zero - v * unit} />)}
                    </g>
                    <g fontSize="11" textAnchor="end" className="fill-gray-500 dark:fill-gray-400">
                        {grid.map((v) => <text key={v} x={X0 - 8} y={zero - v * unit + 4}>{Math.abs(v)}</text>)}
                    </g>
                    <g fontSize="10" fontWeight="600" className="fill-gray-500 dark:fill-gray-400">
                        <text x="4" y={PLOT_TOP + 12}>{strings.timeline_open_axis}</text>
                        <text x="4" y={PLOT_BOTTOM - 8}>{strings.timeline_fixed_axis}</text>
                        <text x="4" y={LANE_TOP + 20}>{strings.timeline_commits_axis}</text>
                        <text x="4" y={LANE_TOP + 34} fontWeight="400">{strings.timeline_without.replace(':branch', target)}</text>
                    </g>

                    {slots.filter((s) => s.kind === 'gap').map((s) => (
                        <g key={`gap-${s.x}`} textAnchor="middle" className="fill-gray-400 dark:fill-gray-500">
                            <text x={s.cx} y={zero + 5} fontSize="14">⋯</text>
                            {s.days > 0 && <text x={s.cx} y={zero + 20} fontSize="10">{strings.timeline_days.replace(':count', s.days)}</text>}
                        </g>
                    ))}

                    {!runs.length && (
                        <text x={(X0 + X1) / 2} y={zero - 10} fontSize="12" textAnchor="middle" className="fill-gray-400 dark:fill-gray-500">{strings.timeline_empty}</text>
                    )}

                    {reviewSlots.map((s) => {
                        const r = s.run;
                        const left = s.cx - bar / 2;
                        const open = sum(r);
                        const fixed = sum(r.fixed);
                        let yUp = zero;
                        let yDown = zero;
                        return (
                            <g
                                key={r.id}
                                role="link"
                                tabIndex={0}
                                aria-label={reviewTip(s).lines.map((l) => l.text).join(', ')}
                                className="cursor-pointer outline-none"
                                onClick={() => jump(r.id)}
                                onKeyDown={(e) => {
                                    if (e.key !== 'Enter' && e.key !== ' ') return;
                                    e.preventDefault();
                                    jump(r.id);
                                }}
                                onMouseEnter={() => setTip(reviewTip(s))}
                                onFocus={() => setTip(reviewTip(s))}
                                onBlur={() => setTip(null)}
                            >
                                <rect x={s.x} y="8" width={s.w} height={PLOT_BOTTOM + 4} fill="transparent" />
                                {[...LEVELS].reverse().map((l) => {
                                    const h = r[l.key] * unit;
                                    if (!h) return null;
                                    yUp -= h;
                                    return <rect key={`o-${l.key}`} x={left} y={yUp} width={bar} height={h} rx="2" className={l.fill} />;
                                })}
                                {LEVELS.map((l) => {
                                    const h = r.fixed[l.key] * unit;
                                    if (!h) return null;
                                    yDown += h;
                                    return <rect key={`f-${l.key}`} x={left} y={yDown - h} width={bar} height={h} rx="2" opacity="0.4" className={l.fill} />;
                                })}
                                {open === 0 && <rect x={left} y={zero - 3} width={bar} height="3" rx="1.5" className="fill-green-500 dark:fill-green-400" />}
                                <text x={s.cx} y={yUp - 8} fontSize="11" fontWeight="600" textAnchor="middle" className="fill-current">{open}</text>
                                {r.first && !r.latest && (
                                    <text x={s.cx} y={yUp - 22} fontSize="10" textAnchor="middle" className="fill-gray-500 dark:fill-gray-400">{strings.timeline_first}</text>
                                )}
                                {fixed > 0 && (
                                    <text x={s.cx} y={yDown + 14} fontSize="11" fontWeight="600" textAnchor="middle" className="fill-green-700 dark:fill-green-400">{`−${fixed}`}</text>
                                )}
                                {r.latest && (
                                    <g>
                                        <rect x={s.cx - 37} y="12" width="74" height="16" rx="8" className="fill-indigo-600 dark:fill-indigo-500" />
                                        <text x={s.cx} y="23.5" fontSize="10" fontWeight="600" textAnchor="middle" fill="#fff">{strings.timeline_latest}</text>
                                    </g>
                                )}
                            </g>
                        );
                    })}

                    <line x1={X0} x2={X1} y1={zero} y2={zero} strokeWidth="1.5" className="stroke-gray-700 dark:stroke-gray-300" />

                    <rect x={X0} y={LANE_TOP} width={X1 - X0} height={LANE_H} rx="6" className="fill-gray-100 dark:fill-gray-900/60" />
                    {!commits && (
                        <text x={X0 + 10} y={LANE_TOP + 20} fontSize="11" className="fill-gray-400 dark:fill-gray-500">{strings.timeline_loading}</text>
                    )}
                    {bundles.flatMap((s) => {
                        const x = bundleX(s);
                        const regular = s.bundle.filter((c) => !c.merge);
                        const merges = s.bundle.filter((c) => c.merge);
                        // Beides im selben Abschnitt: nebeneinander statt übereinander.
                        const shift = regular.length && merges.length ? 30 : 0;
                        return [
                            regular.length > 0 && marker(`b-${regular[0].sha}`, x - shift, regular, false),
                            merges.length > 0 && marker(`m-${merges[0].sha}`, x + shift, merges, true),
                        ].filter(Boolean);
                    })}

                    <line x1={X0} x2={X1} y1={AXIS_Y} y2={AXIS_Y} className="stroke-gray-300 dark:stroke-gray-600" />
                    {firstReview && <line x1={firstReview.x} x2={X1} y1={AXIS_Y + 4} y2={AXIS_Y + 4} className="stroke-gray-400 dark:stroke-gray-500" />}
                    <g fontSize="11" fontWeight="500" textAnchor="middle" className="fill-gray-700 dark:fill-gray-300">
                        {slots.filter((s) => s.kind === 'day').map((s) => <text key={`d-${s.t}`} x={s.cx} y={AXIS_Y + 18}>{shortDate(s.t)}</text>)}
                        {reviewDays.map((d) => (
                            <text key={`rd-${d.t}`} x={(Math.min(...d.xs) + Math.max(...d.xs)) / 2} y={AXIS_Y + 18}>{shortDate(d.t)}</text>
                        ))}
                    </g>
                    <g fontSize="10" textAnchor="middle" className="fill-gray-500 dark:fill-gray-400">
                        {reviewSlots.map((s) => <text key={`t-${s.run.id}`} x={s.cx} y={AXIS_Y + 34}>{clock(s.run.t)}</text>)}
                    </g>

                    {tip && <Tooltip tip={tip} />}
                </svg>
            </div>
            <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">{strings.timeline_footnote.replace(':branch', target)}</p>
        </section>
    );
}

// Dunkle Box an der Maus-Position, innerhalb der Zeichenfläche gehalten.
function Tooltip({ tip }) {
    const width = Math.max(...tip.lines.map((l) => l.text.length)) * 6.2 + 24;
    const height = tip.lines.length * 15 + 12;
    const x = Math.min(Math.max(tip.x - width / 2, 4), W - width - 4);
    const y = Math.max(4, tip.y - height - 6);
    return (
        <g pointerEvents="none">
            <rect x={x} y={y} width={width} height={height} rx="6" fill="#111827" />
            {tip.lines.map((l, i) => (
                <text
                    key={i}
                    x={x + 12}
                    y={y + 19 + i * 15}
                    fontSize={l.strong ? 11 : 10}
                    fontWeight={l.strong ? 600 : 400}
                    fontFamily={l.mono ? 'ui-monospace, Menlo, monospace' : undefined}
                    fill={l.tone === 'good' ? '#86EFAC' : l.strong ? '#F9FAFB' : '#D1D5DB'}
                >
                    {l.text}
                </text>
            ))}
        </g>
    );
}
