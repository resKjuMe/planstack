import React, { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import ProjectHeaderBar from '../components/ProjectHeaderBar.jsx';
import ProjectTabs from '../components/ProjectTabs.jsx';
import Flash from '../components/Flash.jsx';
import BoardView from '../views/BoardView.jsx';
import SummaryView from '../views/SummaryView.jsx';

// EINE Inertia-Seite für die Projekt-Unterseiten. Board und Summary werden rein
// clientseitig umgeschaltet — 0 Server-Calls beim Tab-Wechsel: die statischen
// Props beider Views kommen gebündelt mit der ersten Antwort (ProjectWorkspace-
// Presenter), die Task-/Phasen-Daten aus dem geteilten Store. Die URL wird per
// History-API aktualisiert (deep-linkbar, F5 lädt die jeweilige Seite direkt).
//
// Weitere Unterseiten (Diagramm, PR-Sequence, …) laufen bis zu ihrer Migration
// weiter über den normalen Inertia-Visit (globaler Klick-Interceptor in app.jsx).
const CLIENT_TABS = ['board', 'summary'];

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

export default function ProjectWorkspace({ activeTab, project, can, tabs, flash, board, summary }) {
    const { errors } = usePage().props;
    const [tab, setTab] = useState(activeTab);

    // Back/Forward: aktiven Tab aus der URL ableiten. Inertia stellt dieselbe
    // Workspace-Seite aus dem History-State wieder her (ohne Server-Call), diese
    // Komponente bleibt gemountet — wir schalten nur den sichtbaren View um.
    useEffect(() => {
        const onPop = () => {
            const key = tabForPath(window.location.pathname, tabs);
            if (key && CLIENT_TABS.includes(key)) setTab(key);
        };
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
    }, [tabs]);

    // Tab-Klick: board/summary clientseitig; andere (noch nicht migrierte) Tabs
    // gibt false zurück → der globale Interceptor macht den normalen Inertia-Visit.
    // Rückgabe true = clientseitig behandelt (der Aufrufer ruft preventDefault()).
    const navigate = (key, href) => {
        if (!CLIENT_TABS.includes(key)) return false;
        if (key !== tab) {
            setTab(key);
            // URL wechseln, aber Inertias History-State (die Workspace-Seite)
            // beibehalten → kein Server-Call, back/forward bleibt reload-frei.
            window.history.pushState(window.history.state, '', href);
            window.scrollTo(0, 0);
        }
        return true;
    };

    const strings = tab === 'summary' ? summary.strings : board.strings;
    const title =
        tab === 'summary'
            ? `${project.name} · ${summary.strings.title}`
            : `${project.name} · ${board.strings.boardTitle}`;

    return (
        <>
            <Head><title>{title}</title></Head>

            <PageBands
                header={<ProjectHeaderBar project={project} can={can} strings={strings} />}
                subnav={<ProjectTabs tabs={tabs} activeKey={tab} onNavigate={navigate} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    <Flash status={flash?.status} error={flash?.error} errors={errors} />

                    {tab === 'summary' ? (
                        <SummaryView project={project} strings={summary.strings} />
                    ) : (
                        <BoardView meta={board.meta} strings={board.strings} />
                    )}
                </div>
            </div>
        </>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
ProjectWorkspace.layout = AppShell;
