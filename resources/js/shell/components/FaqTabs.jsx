import React from 'react';

// Subnavigation der FAQ-Seiten (Übersicht / Statuslogik). Server-gerenderte Tabs
// (key/label/href/active); die Links sind gewöhnliche <a> und werden vom globalen
// Interceptor reload-frei über Inertia geführt. Pendant zu ProjectEditTabs.
export default function FaqTabs({ tabs }) {
    return (
        <nav className="flex gap-1 border-b border-gray-200 dark:border-gray-700">
            {tabs.map((tab, i) => (
                <a
                    key={tab.key}
                    href={tab.href}
                    className={
                        `${i === 0 ? 'pr-4' : 'px-4'} py-2 text-sm font-medium border-b-2 -mb-px ` +
                        (tab.active
                            ? 'border-gray-800 dark:border-gray-100 text-gray-800 dark:text-gray-100'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600')
                    }
                >
                    {tab.label}
                </a>
            ))}
        </nav>
    );
}
