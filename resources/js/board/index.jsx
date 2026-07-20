import React from 'react';
import { createRoot } from 'react-dom/client';
import Board from './components/Board';

// Mount point: the board view (resources/views/projects/show.blade.php) renders
// <div id="board-root"> and injects the payload as window.__PLANSTACK_BOARD__.
const el = document.getElementById('board-root');
if (el && window.__PLANSTACK_BOARD__) {
    createRoot(el).render(<Board data={window.__PLANSTACK_BOARD__} />);
}
