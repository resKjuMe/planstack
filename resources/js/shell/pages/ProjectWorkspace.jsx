import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import ProjectHeaderBar from '../components/ProjectHeaderBar.jsx';
import ProjectTabs from '../components/ProjectTabs.jsx';
import Flash from '../components/Flash.jsx';
import BoardView from '../views/BoardView.jsx';
import SummaryView from '../views/SummaryView.jsx';
import DiagramView from '../views/DiagramView.jsx';
import PrSequenceView from '../views/PrSequenceView.jsx';
import CalibrationView from '../views/CalibrationView.jsx';
import ChangelogView from '../views/ChangelogView.jsx';
import PerformanceView from '../views/PerformanceView.jsx';
import TimelineView from '../views/TimelineView.jsx';
import ViewSwitch from '../components/ViewSwitch.jsx';

// EINE Inertia-Seite für die Projekt-Unterseiten. Board und Summary werden rein
// clientseitig umgeschaltet — 0 Server-Calls beim Tab-Wechsel: die statischen
// Props beider Views kommen gebündelt mit der ersten Antwort (ProjectWorkspace-
// Presenter), die Task-/Phasen-Daten aus dem geteilten Store. Die URL wird per
// History-API aktualisiert (deep-linkbar, F5 lädt die jeweilige Seite direkt).
//
// Weitere Unterseiten (Diagramm, PR-Sequence, …) laufen bis zu ihrer Migration
// weiter über den normalen Inertia-Visit (globaler Klick-Interceptor in app.jsx).
const CLIENT_TABS = ['board', 'summary', 'diagram', 'pr-sequence', 'timeline', 'calibration', 'changelog', 'performance'];

// Zwei Darstellungen derselben Abhängigkeiten (Graph ↔ Zeitachse). Sie teilen einen
// Reiter — welche gezeigt wird, entscheidet der Umschalter im Seitenkopf; die URL
// (und damit der Deep-Link) wechselt mit.
const DEPENDENCY_VIEWS = ['diagram', 'timeline'];

// Reiter, der für den aktiven Tab hervorgehoben wird: die Zeitachse hat keinen
// eigenen, sie hängt am Diagramm.
const tabBarKey = (tab) => (tab === 'timeline' ? 'diagram' : tab);

function tabForPath(pathname, tabs) {
    for (const t of tabs) {
        try {
            if (new URL(t.href, window.location.origin).pathname === pathname) return t.key;
        } catch {
            /* ignore */
        }
    }
    return null;
}

export default function ProjectWorkspace({ activeTab, currentUserId, project, can, tabs, flash, board, summary, diagram, sequence, timeline, calibration, changelog, performance }) {
    const { errors } = usePage().props;

    // Aktiven Tab autoritativ aus der BROWSER-URL ableiten (nicht aus dem
    // Server-activeTab-Prop): Beim Zurueck-Navigieren remountet die Seite ggf.
    // (AppShell key={url}) und das Prop traegt noch den Tab des ersten Renders —
    // die URL ist die verlaessliche Quelle. Server-activeTab nur als Fallback.
    const resolveTab = () => {
        const key = tabForPath(window.location.pathname, tabs);
        return key && CLIENT_TABS.includes(key) ? key : activeTab;
    };
    const [tab, setTab] = useState(resolveTab);

    // Back/Forward: bevorzugt der im History-State hinterlegte Tab-Marker,
    // sonst aus der URL. Deckt sowohl den Remount- als auch den
    // Komponente-bleibt-gemountet-Fall ab.
    useEffect(() => {
        const onPop = (e) => {
            const stored = e.state?.psTab;
            setTab(stored && CLIENT_TABS.includes(stored) ? stored : resolveTab());
        };
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tabs]);

    // Projekt-Stammdaten (Name/Alias in der Kopfzeile) liegen nicht im Store,
    // sondern in den Inertia-Props. Ändert sich das Projekt selbst, die betroffene
    // Prop gezielt nachladen (selten → ein Partial-Request ist vertretbar).
    useEffect(() => {
        const onEntity = (e) => {
            const d = e.detail;
            if (d && d.entity === 'project' && d.project_alias === project.alias) {
                router.reload({ only: ['project'] });
            }
        };
        window.addEventListener('planstack:entity-changed', onEntity);
        return () => window.removeEventListener('planstack:entity-changed', onEntity);
    }, [project.alias]);

    // Tab-Klick: board/summary clientseitig; andere (noch nicht migrierte) Tabs
    // gibt false zurück → der globale Interceptor macht den normalen Inertia-Visit.
    // Rückgabe true = clientseitig behandelt (der Aufrufer ruft preventDefault()).
    const navigate = (key, href) => {
        if (!CLIENT_TABS.includes(key)) return false;
        if (key !== tab) {
            setTab(key);
            // URL wechseln, aber Inertias History-State (die Workspace-Seite)
            // beibehalten → kein Server-Call, back/forward bleibt reload-frei.
            // Zusaetzlich den Tab-Marker hinterlegen, damit back/forward den
            // richtigen Tab wiederherstellt (siehe onPop).
            window.history.pushState({ ...window.history.state, psTab: key }, '', href);
            window.scrollTo(0, 0);
        }
        return true;
    };

    // Die Kopfzeile (Sync/Einstellungen/+Task) ist projektweit identisch — immer
    // board.strings (enthält diese Labels), unabhängig vom aktiven Tab.
    const headerStrings = board.strings;

    // Umschalter Diagramm ↔ Zeitachse: Beschriftung und URL kommen aus derselben
    // Tab-Liste wie die Reiterleiste (eine Quelle für Labels und Routen).
    const dependencySwitch = (
        <ViewSwitch
            options={DEPENDENCY_VIEWS.map((key) => tabs.find((t) => t.key === key)).filter(Boolean)}
            activeKey={tab}
            onSelect={navigate}
        />
    );

    // Titel + Inhalt je Tab an einer Stelle. `performance` ist nur für den
    // Organisations-Owner gesetzt (der Server schickt es anderen nicht mit und
    // rendert den Tab nicht) — fehlt es, fällt der Workspace aufs Board zurück.
    const views = {
        summary: { title: summary.strings.title, render: () => <SummaryView project={project} strings={summary.strings} /> },
        diagram: { title: diagram.strings.title, render: () => <DiagramView project={project} currentUserId={currentUserId} strings={diagram.strings} viewSwitch={dependencySwitch} /> },
        'pr-sequence': { title: sequence.strings.title, render: () => <PrSequenceView project={project} strings={sequence.strings} /> },
        timeline: { title: timeline.strings.title, render: () => <TimelineView project={project} strings={timeline.strings} viewSwitch={dependencySwitch} /> },
        calibration: { title: calibration.strings.title, render: () => <CalibrationView project={project} strings={calibration.strings} /> },
        changelog: { title: changelog.strings.title, render: () => <ChangelogView project={project} strings={changelog.strings} /> },
        ...(performance
            ? { performance: { title: performance.strings.title, render: () => <PerformanceView project={project} activityUrl={performance.activityUrl} strings={performance.strings} /> } }
            : {}),
    };
    const view = views[tab] ?? {
        title: board.strings.boardTitle,
        render: () => <BoardView meta={board.meta} strings={board.strings} />,
    };
    const title = `${project.name} · ${view.title}`;

    return (
        <>
            <Head><title>{title}</title></Head>

            <PageBands
                header={<ProjectHeaderBar project={project} can={can} strings={headerStrings} />}
                subnav={<ProjectTabs tabs={tabs} activeKey={tabBarKey(tab)} onNavigate={navigate} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    <Flash status={flash?.status} error={flash?.error} errors={errors} />

                    {/* key={tab} → beim Umschalten neu gemountet, sodass die
                        Einblend-Animation (.ps-view-enter) je View einmal laeuft. */}
                    <div key={tab} className="ps-view-enter">
                        {view.render()}
                    </div>
                </div>
            </div>
        </>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
ProjectWorkspace.layout = AppShell;
