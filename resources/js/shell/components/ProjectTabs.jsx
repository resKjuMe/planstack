import React from 'react';

// Projekt-Unterseiten-Navigation (React-Pendant zu components/project-tabs.blade.php).
// Die Tabs (key/label/href) kommen server-seitig als Prop. `activeKey` markiert den
// aktiven Tab (fällt auf das server-seitige tab.active zurück). `onNavigate(key,
// href)` erlaubt clientseitiges Umschalten ohne Server-Call: gibt es true zurück,
// wird der Klick abgefangen (preventDefault); sonst führt der globale Interceptor
// den normalen reload-freien Inertia-Visit aus.
export default function ProjectTabs({ tabs, activeKey, onNavigate }) {
    return (
        <nav className="flex gap-1 border-b border-gray-200 dark:border-gray-700">
            {tabs.map((tab, i) => {
                const active = activeKey != null ? tab.key === activeKey : tab.active;
                return (
                    <a
                        key={tab.key}
                        href={tab.href}
                        onClick={(e) => {
                            if (onNavigate && onNavigate(tab.key, tab.href)) e.preventDefault();
                        }}
                        className={
                            `${i === 0 ? 'pr-4' : 'px-4'} py-2 text-sm font-medium border-b-2 -mb-px ` +
                            (active
                                ? 'border-gray-800 dark:border-gray-100 text-gray-800 dark:text-gray-100'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600')
                        }
                    >
                        {tab.label}
                    </a>
                );
            })}
        </nav>
    );
}
