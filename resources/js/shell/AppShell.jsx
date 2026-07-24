import React from 'react';
import { usePage } from '@inertiajs/react';
import Nav from './components/Nav.jsx';
import Relocate from './components/Relocate.jsx';

// Persistentes React-Grundgerüst: Wrapper + Navigation. Bleibt über
// Inertia-Navigationen hinweg gemountet (die Glocke/Navi wird nicht neu
// aufgebaut). Der seitenspezifische Inhalt kommt als children (siehe BladePage).
// Die Shell-Nutzlast (Navi-Links, Menü, Labels) liefert Inertia als Shared-Prop.
export default function AppShell({ children }) {
    const { props, url } = usePage();
    const { shell } = props;

    // Benachrichtigungs-Seitenleiste: der server-gerenderte Alpine-Knoten
    // #shell-sidebar (app-root.blade.php) wird ab lg in eine eigene Spalte
    // gehaengt. Auf kleineren Schirmen bleibt die klassische Glocke (Nav/Mobile)
    // als Zugang erhalten. Nur relevant fuer Nutzer mit Organisation.
    const sidebar = shell.hasOrg && shell.notificationDisplay === 'sidebar';

    return (
        // Im Sidebar-Modus wird der GESAMTE Hauptbereich (Nav + Inhalt) zur
        // linken Flex-Spalte, die Seitenleiste zur rechten. So teilen sich Nav
        // und Inhalt dieselbe Breite/Zentrierung (max-w-7xl mx-auto) — sonst
        // wirkt die volle-Breite-Nav gegenueber dem schmaleren Inhalt verschoben.
        <div className={`min-h-screen bg-gray-100 dark:bg-gray-900${sidebar ? ' flex items-start' : ''}`}>
            <div className="min-w-0 flex-1">
                <Nav shell={shell} sidebar={sidebar} />
                {/* key={url} → bei jeder Inertia-Navigation neu gemountet, sodass die
                    Seiten-Einblendung (.ps-page-enter) genau einmal laeuft. Nav/Glocke
                    liegen ausserhalb und bleiben persistent. Client-seitige Tab-Wechsel
                    im Workspace nutzen history.pushState (aendern Inertias url NICHT)
                    und bleiben von dieser Animation unberuehrt. */}
                <div key={url} className="ps-page-enter">
                    {children}
                </div>
            </div>
            {sidebar && (
                <Relocate
                    sourceId="shell-sidebar"
                    as="aside"
                    className="hidden w-80 shrink-0 border-s border-gray-200 bg-white lg:sticky lg:top-0 lg:block lg:h-screen dark:border-gray-700 dark:bg-gray-800"
                />
            )}
        </div>
    );
}
