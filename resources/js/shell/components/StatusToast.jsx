import React, { useEffect, useState } from 'react';

// Erfolgsmeldung als Toast unten rechts statt als Banner auf der Seite.
// Jedes neue flash-Objekt (also jeder Besuch) zeigt die Meldung erneut.
export default function StatusToast({ flash }) {
    const [message, setMessage] = useState(flash?.status ?? null);

    useEffect(() => setMessage(flash?.status ?? null), [flash]);

    useEffect(() => {
        if (!message) return undefined;
        const id = setTimeout(() => setMessage(null), 5000);
        return () => clearTimeout(id);
    }, [message, flash]);

    if (!message) return null;

    return (
        <div className="fixed bottom-6 right-6 z-50 max-w-sm" role="status">
            <div className="flex items-start gap-3 rounded-lg bg-green-600 px-4 py-3 text-sm text-white shadow-lg">
                <span className="mt-0.5">✓</span>
                <p className="flex-1">{message}</p>
                <button
                    type="button"
                    onClick={() => setMessage(null)}
                    className="text-white/80 hover:text-white"
                    aria-label="Dismiss"
                >
                    ✕
                </button>
            </div>
        </div>
    );
}
