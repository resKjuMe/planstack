import React, { useEffect } from 'react';

// Minimal error toast for rejected drag-and-drop moves. Auto-dismisses.
export default function Toast({ message, onDismiss }) {
    useEffect(() => {
        if (!message) return undefined;
        const id = setTimeout(onDismiss, 5000);
        return () => clearTimeout(id);
    }, [message, onDismiss]);

    if (!message) return null;

    return (
        <div className="fixed bottom-6 right-6 z-50 max-w-sm">
            <div className="flex items-start gap-3 rounded-lg bg-rose-600 px-4 py-3 text-sm text-white shadow-lg">
                <span className="mt-0.5">⚠</span>
                <p className="flex-1">{message}</p>
                <button
                    type="button"
                    onClick={onDismiss}
                    className="text-white/80 hover:text-white"
                    aria-label="Dismiss"
                >
                    ✕
                </button>
            </div>
        </div>
    );
}
