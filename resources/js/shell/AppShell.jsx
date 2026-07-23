import React from 'react';
import { usePage } from '@inertiajs/react';
import Nav from './components/Nav.jsx';

// Persistentes React-Grundgerüst: Wrapper + Navigation. Bleibt über
// Inertia-Navigationen hinweg gemountet (die Glocke/Navi wird nicht neu
// aufgebaut). Der seitenspezifische Inhalt kommt als children (siehe BladePage).
// Die Shell-Nutzlast (Navi-Links, Menü, Labels) liefert Inertia als Shared-Prop.
export default function AppShell({ children }) {
    const { shell } = usePage().props;

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <Nav shell={shell} />
            {children}
        </div>
    );
}
