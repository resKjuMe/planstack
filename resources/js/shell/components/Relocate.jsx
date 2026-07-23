import React, { useLayoutEffect, useRef } from 'react';

// Hängt einen server-gerenderten Blade-Knoten (per id) an dieser Stelle in den
// React-Baum. Bewusst wird der ECHTE DOM-Knoten verschoben (appendChild), nicht
// per innerHTML kopiert: so bleiben Alpine-Bindings, Inline-<script>, Event-
// Handler und verschachtelte Islands (z. B. das React-Board unter #board-root)
// samt Zustand erhalten. Der Knoten verlässt das Dokument nie, nur seinen
// Elternknoten — daher überlebt auch bereits initialisierter State den Umzug.
export default function Relocate({ sourceId, as: Tag = 'div', ...props }) {
    const ref = useRef(null);

    useLayoutEffect(() => {
        const src = document.getElementById(sourceId);
        if (src && ref.current && src.parentNode !== ref.current) {
            ref.current.appendChild(src);
            src.hidden = false;
            src.removeAttribute('hidden');
        }
    }, [sourceId]);

    return <Tag ref={ref} {...props} />;
}
