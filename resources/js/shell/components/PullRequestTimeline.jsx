import React from 'react';
import { formatDate, formatDuration } from './PullRequestParts.jsx';

// Verlauf seit PR-Erstellung in zwei Spuren auf gemeinsamer Zeitachse:
// oben die Reviews mit gestapelten Befunden, unten die Commits (Kreisgröße = geänderte Zeilen).

const W = 1000;
const X0 = 110;
const X1 = W - 20;
const REVIEW_TOP = 24;
const REVIEW_BASE = 170;
const COMMIT_TOP = 186;
const COMMIT_Y = 224;
const COMMIT_BOTTOM = 262;
const AXIS_Y = 278;
const H = 300;
const BAR = 16;
const LABEL_GAP = 90;

const LEVELS = [
    { key: 'hint', level: 'HINT', fill: 'fill-gray-400 dark:fill-gray-500' },
    { key: 'important', level: 'IMPORTANT', fill: 'fill-amber-400 dark:fill-amber-500' },
    { key: 'critical', level: 'CRITICAL', fill: 'fill-red-500 dark:fill-red-400' },
];

const time = (iso) => (iso ? new Date(iso).getTime() : null);
const shortDate = (ms) => new Date(ms).toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' });

export default function PullRequestTimeline({ pr, reviews, commits, strings }) {
    const runs = reviews
        .filter((r) => r.status === 'finished' && r.reviewedAt)
        .map((r) => ({ ...r, t: time(r.reviewedAt) }))
        .sort((a, b) => a.t - b.t);
    const points = (commits ?? []).map((c) => ({ ...c, t: time(c.committedAt) })).sort((a, b) => a.t - b.t);

    const end = time(pr.mergedAt) ?? time(pr.closedAt) ?? Date.now();
    const stamps = [time(pr.openedAt), end, ...runs.map((r) => r.t), ...points.map((c) => c.t)].filter(Boolean);
    let from = Math.min(...stamps);
    let to = Math.max(...stamps);
    if (to - from < 3_600_000) {
        from -= 1_800_000;
        to += 1_800_000;
    }
    const pad = (to - from) * 0.02;
    from -= pad;
    to += pad;
    const x = (t) => X0 + ((t - from) / (to - from)) * (X1 - X0);

    const maxFindings = Math.max(1, ...runs.map((r) => r.critical + r.important + r.hint));
    const unit = (REVIEW_BASE - REVIEW_TOP - 30) / maxFindings;
    const maxLines = Math.max(1, ...points.map((c) => c.additions + c.deletions));
    const radius = (c) => 4 + 10 * Math.sqrt((c.additions + c.deletions) / maxLines);

    const additions = points.reduce((sum, c) => sum + c.additions, 0);
    const deletions = points.reduce((sum, c) => sum + c.deletions, 0);
    const ticks = Array.from({ length: 5 }, (_, i) => from + ((to - from) * i) / 4);
    const tickLabel = (t) => (to - from < 2 * 86_400_000
        ? new Date(t).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
        : shortDate(t));

    // Beschriftung nur, wenn sie nicht in die vorige ragt; die Details stehen im Tooltip.
    const labelled = (items) => {
        let last = -Infinity;
        return items.map((item) => {
            const show = x(item.t) - last >= LABEL_GAP;
            if (show) last = x(item.t);
            return show;
        });
    };
    const runLabels = labelled(runs);
    const commitLabels = labelled(points);
    const anchorEnd = (t) => x(t) > X1 - LABEL_GAP;

    return (
        <section className="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{strings.timeline_title}</h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{strings.timeline_hint}</p>
                </div>
                <div className="flex gap-3 text-xs text-gray-600 dark:text-gray-300">
                    {[...LEVELS].reverse().map((l) => (
                        <span key={l.key} className="inline-flex items-center gap-1.5">
                            <svg width="10" height="10" aria-hidden="true"><rect width="10" height="10" rx="2" className={l.fill} /></svg>
                            {strings[`level_${l.level}`]}
                        </span>
                    ))}
                </div>
            </div>

            <div className="mt-3 overflow-x-auto">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className="w-full min-w-[640px] text-gray-700 dark:text-gray-300"
                    role="img"
                    aria-label={strings.timeline_title}
                >
                    <rect x={X0 - 10} y={0} width={X1 - X0 + 20} height={REVIEW_BASE} rx="6" className="fill-gray-50 dark:fill-gray-900/40" />
                    <rect x={X0 - 10} y={COMMIT_TOP} width={X1 - X0 + 20} height={COMMIT_BOTTOM - COMMIT_TOP} rx="6" className="fill-gray-50 dark:fill-gray-900/40" />

                    <text x="0" y={REVIEW_BASE / 2} fontSize="13" fontWeight="600" className="fill-current">{strings.reviews}</text>
                    <text x="0" y={REVIEW_BASE / 2 + 15} fontSize="11" className="fill-gray-500 dark:fill-gray-400">
                        {strings.timeline_runs.replace(':count', runs.length)}
                    </text>
                    <text x="0" y={COMMIT_Y - 2} fontSize="13" fontWeight="600" className="fill-current">{strings.commits}</text>
                    {commits && (
                        <text x="0" y={COMMIT_Y + 13} fontSize="11" className="fill-gray-500 dark:fill-gray-400">
                            {`${points.length} · +${additions} −${deletions}`}
                        </text>
                    )}

                    <g strokeDasharray="3 4" className="stroke-indigo-200 dark:stroke-indigo-800">
                        {runs.map((r) => (
                            <line key={r.id} x1={x(r.t)} x2={x(r.t)} y1={REVIEW_BASE} y2={COMMIT_BOTTOM} />
                        ))}
                    </g>

                    {runs.map((r, i) => {
                        let y = REVIEW_BASE - 4;
                        const left = x(r.t) - BAR / 2;
                        const total = r.critical + r.important + r.hint;
                        const top = REVIEW_BASE - 4 - total * unit;
                        return (
                            <g key={r.id}>
                                <title>
                                    {`${formatDate(r.reviewedAt)}${r.commitSha ? ` · ${r.commitSha.slice(0, 7)}` : ''}${formatDuration(r.startedAt, r.finishedAt) ? ` · ${formatDuration(r.startedAt, r.finishedAt)}` : ''}\n`
                                        + `${strings.level_CRITICAL} ${r.critical} · ${strings.level_IMPORTANT} ${r.important} · ${strings.level_HINT} ${r.hint}`}
                                </title>
                                {total === 0 ? (
                                    <rect x={left} y={REVIEW_BASE - 7} width={BAR} height="3" rx="1.5" className="fill-green-500 dark:fill-green-400" />
                                ) : LEVELS.map((l) => {
                                    const h = r[l.key] * unit;
                                    if (!h) return null;
                                    y -= h;
                                    return <rect key={l.key} x={left} y={y} width={BAR} height={h} className={l.fill} />;
                                })}
                                {runLabels[i] && (
                                    <text
                                        x={anchorEnd(r.t) ? left - 6 : left + BAR + 6}
                                        y={Math.max(REVIEW_TOP, top) + 10}
                                        fontSize="11"
                                        textAnchor={anchorEnd(r.t) ? 'end' : 'start'}
                                    >
                                        <tspan fontWeight="600" className="fill-current">{`${r.critical} · ${r.important} · ${r.hint}`}</tspan>
                                        <tspan x={anchorEnd(r.t) ? left - 6 : left + BAR + 6} dy="14" fontSize="10" className="fill-gray-500 dark:fill-gray-400">
                                            {shortDate(r.t)}
                                        </tspan>
                                    </text>
                                )}
                            </g>
                        );
                    })}

                    {!commits && (
                        <text x={X0} y={COMMIT_Y + 4} fontSize="11" className="fill-gray-400 dark:fill-gray-500">{strings.timeline_loading}</text>
                    )}
                    {commits && points.length === 0 && (
                        <text x={X0} y={COMMIT_Y + 4} fontSize="11" className="fill-gray-400 dark:fill-gray-500">{strings.timeline_no_commits}</text>
                    )}
                    {points.map((c, i) => {
                        const r = radius(c);
                        const end = anchorEnd(c.t);
                        const lx = end ? x(c.t) - r - 6 : x(c.t) + r + 6;
                        return (
                            <g key={c.sha}>
                                <title>{`${c.sha.slice(0, 7)} · ${formatDate(c.committedAt)} · +${c.additions} −${c.deletions}\n${c.message}`}</title>
                                <circle cx={x(c.t)} cy={COMMIT_Y} r={r} strokeWidth="2" className="fill-indigo-50 stroke-indigo-600 dark:fill-indigo-900/60 dark:stroke-indigo-400" />
                                {commitLabels[i] && (
                                    <text x={lx} y={COMMIT_Y - 3} fontSize="10" textAnchor={end ? 'end' : 'start'}>
                                        <tspan fontFamily="ui-monospace, Menlo, monospace" className="fill-current">{c.sha.slice(0, 7)}</tspan>
                                        <tspan x={lx} dy="13" className="fill-green-600 dark:fill-green-400">{`+${c.additions}`}</tspan>
                                        <tspan className="fill-red-600 dark:fill-red-400">{` −${c.deletions}`}</tspan>
                                    </text>
                                )}
                            </g>
                        );
                    })}

                    <line x1={X0 - 10} x2={X1 + 10} y1={AXIS_Y} y2={AXIS_Y} className="stroke-gray-300 dark:stroke-gray-600" />
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
