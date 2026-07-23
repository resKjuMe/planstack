import React, { useEffect, useMemo, useRef, useState } from 'react';
import PageHead from '../components/PageHead.jsx';
import { useProjectData } from '../../data/useProjectData';
import { deriveDiagram } from '../../diagram/derive.js';
import { ChipsSkeleton, BlockSkeleton } from '../components/Skeleton.jsx';

// Inneres SVG-Icon-Markup (aus dem Status) in ein <svg> hüllen.
function Ico({ paths, className = 'ps-ico' }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: paths || '' }}
        />
    );
}

const BOTTLENECK_PATHS =
    '<path d="M6.5 7h11"/><path d="M6.5 17h11"/><path d="M6 20v-2a6 6 0 1 1 12 0v2a1 1 0 0 1 -1 1h-10a1 1 0 0 1 -1 -1z"/><path d="M6 4v2a6 6 0 1 0 12 0v-2a1 1 0 0 0 -1 -1h-10a1 1 0 0 0 -1 1z"/>';

// Die imperativ befüllte Zeichenfläche: React rendert sie EINMAL (memo, keine
// Props) und fasst danach ihre Kinder nie wieder an — der DependencyGraph-Renderer
// schreibt das Mermaid-SVG per innerHTML in .ps-graph und toggelt die Klassen.
const GraphCanvas = React.memo(
    React.forwardRef(function GraphCanvas({ emptyLabel, name }, ref) {
        return (
            <div ref={ref} className="ps-diagram overflow-auto" data-diagram-name={name}>
                <div className="ps-graph min-h-[280px]"></div>
                <p className="ps-diagram-empty hidden py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {emptyLabel}
                </p>
            </div>
        );
    }),
);

// Diagramm-Unterseite als Teilansicht des ProjectWorkspace. Das Graph-Modell wird
// clientseitig aus dem geteilten Store abgeleitet (diagram/derive.js) und live über
// entity-changed aktualisiert; das Mermaid-Rendering übernimmt der lazy geladene
// DependencyGraph (so kommt Mermaid nur beim Öffnen dieses Tabs in den Bundle).
export default function DiagramView({ project, currentUserId, strings }) {
    const { tasks, phases, statusConfig, status, error } = useProjectData(project.alias);

    const diagram = useMemo(() => {
        if (status !== 'ready' || !statusConfig) return null;
        return deriveDiagram({
            tasks,
            phases,
            statusConfig,
            currentUserId,
            taskUrlTemplate: project.taskUrlTemplate,
            reviewClaimUrlTemplate: project.reviewClaimUrlTemplate,
        });
    }, [tasks, phases, statusConfig, status, currentUserId, project.taskUrlTemplate, project.reviewClaimUrlTemplate]);

    const readPref = (key) => {
        try {
            return localStorage.getItem(key) === '1';
        } catch {
            return false;
        }
    };
    const [hideDone, setHideDone] = useState(() => readPref('ps-diagram-hidedone'));
    const [showDesc, setShowDesc] = useState(() => readPref('ps-diagram-desc'));
    const [phaseFilter, setPhaseFilter] = useState(null);
    const [hasLock, setHasLock] = useState(false);

    const rootRef = useRef(null);
    const graphRef = useRef(null);

    // Renderer lazy anlegen und bei jeder Daten-/Optionsänderung aktualisieren.
    useEffect(() => {
        if (!diagram || !rootRef.current) return;
        let cancelled = false;
        (async () => {
            if (!graphRef.current) {
                const { DependencyGraph } = await import('../../diagram/DependencyGraph.js');
                if (cancelled || !rootRef.current) return;
                graphRef.current = new DependencyGraph(rootRef.current, {
                    name: project.alias,
                    onLockChange: setHasLock,
                });
            }
            graphRef.current.update({
                nodes: diagram.nodes,
                edges: diagram.edges,
                hideDone,
                showDesc,
                phaseFilter,
            });
        })();
        return () => {
            cancelled = true;
        };
    }, [diagram, hideDone, showDesc, phaseFilter, project.alias]);

    useEffect(
        () => () => {
            graphRef.current = null;
        },
        [],
    );

    const setPref = (key, value, setter) => {
        setter(value);
        try {
            localStorage.setItem(key, value ? '1' : '0');
        } catch {
            /* ignore */
        }
    };

    const togglePhase = (id) => {
        const key = String(id);
        setPhaseFilter((cur) => (cur === key ? null : key));
        graphRef.current?.clearLock(); // ein alter Knoten-Fokus ergibt nach Filter keinen Sinn
    };

    const reset = () => {
        setPhaseFilter(null);
        graphRef.current?.clearLock();
    };

    const hasDone = diagram?.nodes.some((n) => n.done);
    const showReset = hasLock || phaseFilter !== null;

    return (
        <div className="space-y-4">
            <PageHead
                title={strings.title}
                toggleLabel={strings.showHideExplanation}
                bullets={strings.helpBullets}
            />

            <div className="bg-white rounded-lg shadow p-6 overflow-x-auto space-y-4 dark:bg-gray-800 dark:shadow-black/30">
            {status !== 'ready' && status !== 'error' && (
                <>
                    <ChipsSkeleton count={5} />
                    <BlockSkeleton className="h-72" />
                </>
            )}
            {status === 'error' && (
                <p className="text-sm text-red-600 dark:text-red-400">{error || 'Fehler'}</p>
            )}

            {diagram && (
                <>
                    {/* Phasen-Kopfzeile: Klick filtert auf die Phase. */}
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                        {diagram.phases.map((ph) => {
                            const active = phaseFilter === String(ph.id);
                            return (
                                <button
                                    key={ph.id}
                                    type="button"
                                    data-diagram-phase={ph.id}
                                    {...(active ? { 'data-active': true } : {})}
                                    onClick={() => togglePhase(ph.id)}
                                    className="flex items-center gap-2 rounded-md bg-gray-50 dark:bg-gray-700/40 ring-1 ring-gray-100 dark:ring-gray-700 px-2.5 py-1"
                                    title={`${ph.name} — ${ph.pct}% · ${strings.clickToFilter}`}
                                >
                                    <span className="text-xs font-medium text-gray-600 dark:text-gray-400">{ph.short}</span>
                                    <span className="h-1.5 w-14 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <span
                                            className={'block h-full rounded-full ' + (ph.pct >= 100 ? 'bg-green-600' : 'bg-indigo-500')}
                                            style={{ width: `${ph.pct}%` }}
                                        ></span>
                                    </span>
                                    <span className="text-[11px] tabular-nums text-gray-400 dark:text-gray-500">{ph.pct}%</span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Legende + Toolbar */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="ps-diagram-legend flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                            {diagram.legend.map((item, i) => (
                                <span key={i} className="lg-item">
                                    <span className={`lg-swatch tok-${item.color} cat-${item.cat}`}>
                                        <Ico paths={item.icon} />
                                    </span>
                                    {item.label}
                                </span>
                            ))}
                            <span className="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700"></span>
                            <span className="lg-item">
                                <svg width="26" height="8" aria-hidden="true"><line x1="1" y1="4" x2="25" y2="4" stroke="#64748B" strokeWidth="1.5" /></svg>
                                {strings.openDependency}
                            </span>
                            <span className="lg-item">
                                <svg width="26" height="8" aria-hidden="true"><line x1="1" y1="4" x2="25" y2="4" stroke="#CBD5E1" strokeWidth="1" strokeDasharray="4 4" /></svg>
                                {strings.satisfied}
                            </span>
                            <span className="lg-item"><span className="ps-bn"><Ico paths={BOTTLENECK_PATHS} /></span>{strings.bottleneck}</span>
                        </div>

                        <div className="flex items-center gap-3">
                            {showReset && (
                                <button type="button" onClick={reset} className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {strings.clearSelection}
                                </button>
                            )}
                            <label className="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                <input
                                    type="checkbox"
                                    checked={showDesc}
                                    onChange={(e) => setPref('ps-diagram-desc', e.target.checked, setShowDesc)}
                                    className="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500"
                                />
                                {strings.shortDescriptions}
                            </label>
                            {hasDone && (
                                <label className="inline-flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                    <input
                                        type="checkbox"
                                        checked={hideDone}
                                        onChange={(e) => setPref('ps-diagram-hidedone', e.target.checked, setHideDone)}
                                        className="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500"
                                    />
                                    {strings.hideDone}
                                </label>
                            )}
                            <button
                                type="button"
                                onClick={() => graphRef.current?.exportPng()}
                                className="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-gray-700 px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4v12" /></svg>
                                {strings.asPng}
                            </button>
                        </div>
                    </div>
                </>
            )}

            <GraphCanvas ref={rootRef} emptyLabel={strings.noOpenPrs} name={project.alias} />
            </div>
        </div>
    );
}
