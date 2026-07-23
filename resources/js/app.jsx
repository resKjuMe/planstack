import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';
import BladePage from './shell/pages/BladePage.jsx';
import ProjectBoard from './shell/pages/ProjectBoard.jsx';

// Seiten-Registry: „BladePage" bettet noch nicht migrierte Blade-Seiten ein,
// echte React-Seiten (z. B. ProjectBoard) werden namentlich aufgelöst. Unbekannte
// Namen fallen auf BladePage zurück.
const pages = {
    BladePage,
    ProjectBoard,
};

createInertiaApp({
    resolve: (name) => pages[name] ?? BladePage,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#FF4B3E' },
});

// Server-gerenderte <a>-Links (in den eingebetteten Blade-Inhalten) sind keine
// Inertia-Links. Dieser delegierte Handler fängt gleiche-Ursprung-Klicks ab und
// leitet sie über den Inertia-Router — so navigiert die ganze App ohne Reload.
// Ausgenommen: neue-Tab/Modifier-Klicks, target, download, data-native,
// Anker/mailto/tel und fremde Origins.
document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
    }
    const a = e.target.closest('a[href]');
    if (!a) return;
    if (a.hasAttribute('data-native') || a.hasAttribute('download') || (a.target && a.target !== '_self')) {
        return;
    }
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return;
    }
    const url = new URL(a.href, window.location.origin);
    if (url.origin !== window.location.origin) return;

    e.preventDefault();
    router.visit(url.href);
});

// Sicherheitsnetz: Antwortet ein angesteuerter Endpunkt nicht mit einer gültigen
// Inertia-Antwort (z. B. ein Download oder eine noch nicht umgestellte Route),
// weicht Inertia auf einen echten Full-Load aus, statt einen Fehler zu zeigen.
router.on('invalid', (event) => {
    const responseUrl = event.detail?.response?.request?.responseURL;
    if (responseUrl) {
        event.preventDefault();
        window.location.href = responseUrl;
    }
});
