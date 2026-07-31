import React from 'react';

// Umschalter zwischen zwei Darstellungen DERSELBEN Sache (Diagramm ↔ Zeitachse):
// beide zeigen die Abhängigkeiten des Projekts, einmal als Graph, einmal über die
// Zeit — deshalb teilen sie einen Tab und werden hier umgeschaltet.
//
// Die Optionen sind echte Links (eigene URL, deep-linkbar, mittlere Maustaste);
// gibt `onSelect` true zurück, wurde clientseitig umgeschaltet und der Klick wird
// abgefangen — sonst bleibt der normale Seitenwechsel.
export default function ViewSwitch({ options, activeKey, onSelect }) {
    if (!options || options.length < 2) return null;

    return (
        <div className="inline-flex items-center gap-0.5 rounded-full bg-gray-100 p-0.5 dark:bg-gray-700">
            {options.map((option) => {
                const active = option.key === activeKey;
                return (
                    <a
                        key={option.key}
                        href={option.href}
                        aria-current={active ? 'page' : undefined}
                        onClick={(e) => {
                            if (onSelect && onSelect(option.key, option.href)) e.preventDefault();
                        }}
                        className={
                            'rounded-full px-2.5 py-1 text-xs font-medium transition-colors ' +
                            (active
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-gray-100'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200')
                        }
                    >
                        {option.label}
                    </a>
                );
            })}
        </div>
    );
}
