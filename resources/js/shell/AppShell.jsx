import React from 'react';
import { usePage } from '@inertiajs/react';
import Nav from './components/Nav.jsx';

// Persistentes React-Grundgerüst: Wrapper + Navigation. Bleibt über
// Inertia-Navigationen hinweg gemountet (die Glocke/Navi wird nicht neu
// aufgebaut). Der seitenspezifische Inhalt kommt als children (siehe BladePage).
// Die Shell-Nutzlast (Navi-Links, Menü, Labels) liefert Inertia als Shared-Prop.
export default function AppShell({ children }) {
    const { props, url } = usePage();
    const { shell } = props;

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <Nav shell={shell} />
            {/* key={url} → bei jeder Inertia-Navigation neu gemountet, sodass die
                Seiten-Einblendung (.ps-page-enter) genau einmal laeuft. Nav/Glocke
                liegen ausserhalb und bleiben persistent. Client-seitige Tab-Wechsel
                im Workspace nutzen history.pushState (aendern Inertias url NICHT)
                und bleiben von dieser Animation unberuehrt. */}
            <div key={url} className="ps-page-enter">
                {children}
            </div>
        </div>
    );
}
