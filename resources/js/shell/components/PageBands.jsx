import React from 'react';

// Sticky Kopfband der Seiten: optionale Überschrift (header) + optionale Subnav
// (subnav), jeweils als React-Knoten. position: sticky hält es im Fluss, sodass
// das Anpinnen den restlichen Inhalt nicht verschiebt. Genutzt von BladePage
// (eingebettetes Blade-HTML) und von echten React-Seiten (z. B. ProjectBoard).
export default function PageBands({ header, subnav }) {
    if (!header && !subnav) return null;

    return (
        <div className="sticky top-0 z-30">
            {header && (
                <header className="bg-white shadow dark:bg-gray-800 dark:shadow-black/30">
                    <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{header}</div>
                </header>
            )}

            {subnav && (
                <div className="bg-gray-50 border-b border-gray-200 dark:bg-gray-800/50 dark:border-gray-700">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">{subnav}</div>
                </div>
            )}
        </div>
    );
}
