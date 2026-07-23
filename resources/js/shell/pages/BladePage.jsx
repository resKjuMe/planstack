import React, { useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import { runScripts } from '../runScripts.js';

// Einzige Inertia-Seitenkomponente. Bettet den server-gerenderten Blade-Inhalt
// ein: Header-/Subheader-Band (sticky) + Hauptinhalt. Der eigentliche Inhalt
// bleibt Blade-HTML und wird per dangerouslySetInnerHTML gesetzt; danach werden
// enthaltene Skripte ausgeführt und Islands/Alpine über ein Event re-initialisiert.
export default function BladePage({ title, header, subheader, main }) {
    const headerRef = useRef(null);
    const subheaderRef = useRef(null);
    const mainRef = useRef(null);

    useEffect(() => {
        // Reihenfolge: erst die eingebetteten <script> ausführen (setzt u. a.
        // window.__PLANSTACK_BOARD__ und lädt die @vite-Islands), dann das
        // Content-Ready-Event auslösen, auf das board/diagram (neu) mounten.
        runScripts(headerRef.current);
        runScripts(subheaderRef.current);
        runScripts(mainRef.current);
        window.dispatchEvent(new CustomEvent('planstack:content-ready'));
        window.scrollTo(0, 0);
        // Alpine (global gestartet in app.js) initialisiert neu eingefügte
        // x-data-Bäume über seinen MutationObserver selbst.
    }, [header, subheader, main]);

    return (
        <>
            <Head><title>{title}</title></Head>

            {(header || subheader) && (
                <div className="sticky top-0 z-30">
                    {header && (
                        <header className="bg-white shadow dark:bg-gray-800 dark:shadow-black/30">
                            <div
                                ref={headerRef}
                                className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8"
                                dangerouslySetInnerHTML={{ __html: header }}
                            />
                        </header>
                    )}

                    {subheader && (
                        <div className="bg-gray-50 border-b border-gray-200 dark:bg-gray-800/50 dark:border-gray-700">
                            <div
                                ref={subheaderRef}
                                className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
                                dangerouslySetInnerHTML={{ __html: subheader }}
                            />
                        </div>
                    )}
                </div>
            )}

            <main ref={mainRef} dangerouslySetInnerHTML={{ __html: main }} />
        </>
    );
}

// Persistentes Layout: Wrapper + Navigation bleiben über Navigationen erhalten
// (kein Neu-Mounten der Navi/Glocke).
BladePage.layout = (page) => <AppShell>{page}</AppShell>;
