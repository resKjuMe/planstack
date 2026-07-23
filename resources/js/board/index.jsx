import React from 'react';
import { createRoot } from 'react-dom/client';
import Board from './components/Board';

// Kanban-Board-Island. Der Zustand kommt server-gerendert als
// window.__PLANSTACK_BOARD__ (siehe projects/show.blade.php).
//
// „Inertia über Blade": Der Seiteninhalt wird pro Navigation neu eingebettet.
// Das Inline-Payload-Skript und dieses Modul werden dabei von BladePage/runScripts
// (neu) ausgeführt bzw. — bei bereits geladenem Modul — nicht erneut. Damit das
// Board trotzdem bei jeder Navigation (neu) mountet, hört es zusätzlich auf das
// Event `planstack:content-ready`.
let root = null;

function mountBoard() {
    const el = document.getElementById('board-root');
    if (!el || !window.__PLANSTACK_BOARD__) return;

    // Alten (nun abgehängten) Root abräumen, dann in den frischen Knoten mounten.
    if (root) {
        try { root.unmount(); } catch (e) { /* Container bereits entfernt */ }
        root = null;
    }
    root = createRoot(el);
    root.render(<Board data={window.__PLANSTACK_BOARD__} />);
}

mountBoard();
window.addEventListener('planstack:content-ready', mountBoard);
