import React, { useEffect, useState } from 'react';

// Dark-Mode-Umschalter der Shell. Zyklus: hell → dunkel → System → hell.
// Quelle der Wahrheit ist localStorage['theme'] (light|dark|system) plus die
// `.dark`-Klasse am <html>-Element — dieselbe Konvention wie das Anti-Flash-
// Script im <head> (partials/theme-init) und der frühere Alpine-Store. Bei
// 'system' folgt die Anzeige der OS-Einstellung.

const media = window.matchMedia('(prefers-color-scheme: dark)');

function isDark(mode) {
    return mode === 'dark' || (mode === 'system' && media.matches);
}

function apply(mode) {
    document.documentElement.classList.toggle('dark', isDark(mode));
}

const NEXT = { light: 'dark', dark: 'system', system: 'light' };

export default function ThemeToggle({ labels, className = '' }) {
    const [mode, setMode] = useState(() => localStorage.getItem('theme') || 'system');

    // Auf OS-Wechsel reagieren, solange 'system' aktiv ist.
    useEffect(() => {
        const onChange = () => apply(mode);
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [mode]);

    const cycle = () => {
        const next = NEXT[mode] || 'light';
        localStorage.setItem('theme', next);
        apply(next);
        setMode(next);
    };

    const label = labels?.[mode] ?? mode;

    return (
        <button
            type="button"
            onClick={cycle}
            title={label}
            aria-label={label}
            className={
                'inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none transition ease-in-out duration-150 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 ' +
                className
            }
        >
            {mode === 'light' && (
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2" />
                    <path d="M12 20v2" />
                    <path d="m4.93 4.93 1.41 1.41" />
                    <path d="m17.66 17.66 1.41 1.41" />
                    <path d="M2 12h2" />
                    <path d="M20 12h2" />
                    <path d="m6.34 17.66-1.41 1.41" />
                    <path d="m19.07 4.93-1.41 1.41" />
                </svg>
            )}
            {mode === 'dark' && (
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                </svg>
            )}
            {mode === 'system' && (
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <rect x="2" y="3" width="20" height="14" rx="2" />
                    <path d="M8 21h8" />
                    <path d="M12 17v4" />
                </svg>
            )}
        </button>
    );
}
