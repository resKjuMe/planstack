import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';
import BladePage from './shell/pages/BladePage.jsx';
import ProjectWorkspace from './shell/pages/ProjectWorkspace.jsx';
import ProjectsIndex from './shell/pages/ProjectsIndex.jsx';
import ProjectCreate from './shell/pages/ProjectCreate.jsx';
import ProjectEdit from './shell/pages/ProjectEdit.jsx';
import ProjectAccess from './shell/pages/ProjectAccess.jsx';
import ProjectPhases from './shell/pages/ProjectPhases.jsx';
import ProjectClaude from './shell/pages/ProjectClaude.jsx';
import TaskCreate from './shell/pages/TaskCreate.jsx';
import TaskEdit from './shell/pages/TaskEdit.jsx';
import TaskShow from './shell/pages/TaskShow.jsx';
import ConcernEdit from './shell/pages/ConcernEdit.jsx';
import TeamsIndex from './shell/pages/TeamsIndex.jsx';
import TeamCreate from './shell/pages/TeamCreate.jsx';
import TeamShow from './shell/pages/TeamShow.jsx';
import Profile from './shell/pages/Profile.jsx';

// Seiten-Registry: „BladePage" bettet noch nicht migrierte Blade-Seiten ein,
// echte React-Seiten werden namentlich aufgelöst. Unbekannte Namen fallen auf
// BladePage zurück. ProjectWorkspace hostet die Projekt-Unterseiten (clientseitig
// umgeschaltet, 0 Server-Calls beim Tab-Wechsel); ProjectsIndex ist die Liste.
const pages = {
    BladePage,
    ProjectWorkspace,
    ProjectsIndex,
    ProjectCreate,
    ProjectEdit,
    ProjectAccess,
    ProjectPhases,
    ProjectClaude,
    TaskCreate,
    TaskEdit,
    TaskShow,
    ConcernEdit,
    TeamsIndex,
    TeamCreate,
    TeamShow,
    Profile,
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
