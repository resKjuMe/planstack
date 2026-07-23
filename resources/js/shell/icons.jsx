import React from 'react';

// SVG-Icons der App-Shell. 1:1 aus der bisherigen Blade-Navigation portiert
// (layouts/navigation.blade.php, components/application-logo.blade.php), damit
// das React-Grundgerüst optisch identisch bleibt. `className` wird durchgereicht.

// Planstack-Logo: Bildmarke (public/images/planstack-logo.png) + Wortmarke.
// Der Schriftzug ist hell/dunkel-adaptiv; das Bild wird als quadratische Kachel
// gezeigt (der dunkle Glow-Hintergrund ist Teil der Marke).
export function LogoIcon({ className = '' }) {
    return (
        <span className={`inline-flex items-center gap-2 ${className}`}>
            <img
                src="/images/planstack-logo.png"
                alt=""
                aria-hidden="true"
                className="h-8 w-8 shrink-0 rounded-md"
            />
            <span className="text-xl font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                Planstack
            </span>
        </span>
    );
}

// Skill: Pfeil, der in eine Ablage zeigt (Download-Metapher am Skill-Link).
export function SkillIcon({ className = '' }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
            <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
        </svg>
    );
}

// Changelog: Funkeln/Stern – Marker für ungelesene Änderungen.
export function SparkleIcon({ className = '', title }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {title ? <title>{title}</title> : null}
            <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z" />
            <path d="M20 3v4" />
            <path d="M22 5h-4" />
            <path d="M4 17v2" />
            <path d="M5 18H3" />
        </svg>
    );
}

// Chevron nach unten (User-Dropdown-Trigger).
export function ChevronDownIcon({ className = '' }) {
    return (
        <svg className={className} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path
                fillRule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clipRule="evenodd"
            />
        </svg>
    );
}

// Kreis mit Aufwärtspfeil: „Update verfügbar" (CI-Status-Userscript).
export function UpdateIcon({ className = '' }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="9" />
            <path d="M12 16V8" />
            <path d="m8.5 11.5 3.5-3.5 3.5 3.5" />
        </svg>
    );
}

const MENU_ICON_PATHS = {
    // Organisation (Gebäude)
    org: (
        <>
            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" />
            <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
            <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2" />
            <path d="M10 6h4" />
            <path d="M10 10h4" />
            <path d="M10 14h4" />
            <path d="M10 18h4" />
        </>
    ),
    // Profil (Person)
    profile: (
        <>
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </>
    ),
    // CI-Setup (Code-Klammern)
    ci: (
        <>
            <polyline points="16 18 22 12 16 6" />
            <polyline points="8 6 2 12 8 18" />
        </>
    ),
    // Logout (Tür + Pfeil)
    logout: (
        <>
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" x2="9" y1="12" y2="12" />
        </>
    ),
};

// Icon für einen Menüpunkt im User-Dropdown (Strich-Icons, 24er-Grid).
export function MenuIcon({ name, className = '' }) {
    const paths = MENU_ICON_PATHS[name];
    if (!paths) return null;
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {paths}
        </svg>
    );
}

// Hamburger bzw. Schließen-Kreuz (Mobile-Menü-Umschalter).
export function HamburgerIcon({ open, className = '' }) {
    return (
        <svg className={className} stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d={open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'}
            />
        </svg>
    );
}
