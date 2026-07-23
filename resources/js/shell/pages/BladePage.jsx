import React, { useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import { runScripts } from '../runScripts.js';

// Seitenkomponente für noch nicht nach React migrierte Seiten: bettet den
// server-gerenderten Blade-Inhalt ein (Header-/Subheader-Band + Hauptinhalt).
// Der Inhalt bleibt Blade-HTML (dangerouslySetInnerHTML); danach werden
// enthaltene Skripte ausgeführt und Islands/Alpine per Event re-initialisiert.
export default function BladePage({ title, header, subheader, main }) {
    const headerRef = useRef(null);
    const subheaderRef = useRef(null);
    const mainRef = useRef(null);

    useEffect(() => {
        runScripts(headerRef.current);
        runScripts(subheaderRef.current);
        runScripts(mainRef.current);
        window.dispatchEvent(new CustomEvent('planstack:content-ready'));
        window.scrollTo(0, 0);
    }, [header, subheader, main]);

    return (
        <>
            <Head><title>{title}</title></Head>

            <PageBands
                header={header ? <div ref={headerRef} dangerouslySetInnerHTML={{ __html: header }} /> : null}
                subnav={subheader ? <div ref={subheaderRef} dangerouslySetInnerHTML={{ __html: subheader }} /> : null}
            />

            <main ref={mainRef} dangerouslySetInnerHTML={{ __html: main }} />
        </>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
BladePage.layout = AppShell;
