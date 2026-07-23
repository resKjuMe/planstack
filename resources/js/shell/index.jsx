import React from 'react';
import { createRoot } from 'react-dom/client';
import AppShell from './AppShell.jsx';

// Mount-Punkt: layouts/app.blade.php rendert <div id="app-shell"> und legt die
// Shell-Nutzlast (User, Org, Navi-Links, Menü, Labels …) als window.__PLANSTACK_SHELL__ ab.
// Die seitenspezifischen Blade-Inhalte liegen server-gerendert in #shell-nodes
// und werden von der Shell an ihren Platz umgehängt (siehe Relocate).
const el = document.getElementById('app-shell');
if (el && window.__PLANSTACK_SHELL__) {
    createRoot(el).render(<AppShell shell={window.__PLANSTACK_SHELL__} />);
}
