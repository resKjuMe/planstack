import React, { useState } from 'react';
import Nav from './components/Nav.jsx';
import Relocate from './components/Relocate.jsx';

// React-Grundgerüst der Seite: Wrapper, Kopfzeile/Navigation, das sticky
// Header-/Subnav-Band und der <main>-Bereich. Die seitenspezifischen Inhalte
// (Header-Slot, Subheader-Slot, Seiteninhalt) bleiben server-gerenderte
// Blade-Knoten und werden per <Relocate> an ihren Platz gehängt.
export default function AppShell({ shell }) {
    // Header-/Subheader-Band nur rendern, wenn die Seite den jeweiligen Slot
    // gefüllt hat (Blade gibt die Quell-Knoten dann aus). Einmalig beim Mount
    // ermittelt — die Knoten existieren bereits im server-gerenderten DOM.
    const [hasHeader] = useState(() => !!document.getElementById('shell-header'));
    const [hasSubheader] = useState(() => !!document.getElementById('shell-subheader'));

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <Nav shell={shell} />

            {/* Sticky Kopfband (Überschrift + optionale Subnav). position: sticky
                hält es im Fluss, sodass das Anpinnen beim Scrollen den restlichen
                Seiteninhalt nicht verschiebt. */}
            {(hasHeader || hasSubheader) && (
                <div className="sticky top-0 z-30">
                    {hasHeader && (
                        <header className="bg-white shadow dark:bg-gray-800 dark:shadow-black/30">
                            <Relocate sourceId="shell-header" className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8" />
                        </header>
                    )}

                    {hasSubheader && (
                        <div className="bg-gray-50 border-b border-gray-200 dark:bg-gray-800/50 dark:border-gray-700">
                            <Relocate sourceId="shell-subheader" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" />
                        </div>
                    )}
                </div>
            )}

            <Relocate sourceId="shell-main" as="main" />
        </div>
    );
}
