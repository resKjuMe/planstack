import React from 'react';

// Flash-Meldungen (React-Pendant zu components/flash.blade.php): Erfolg (status),
// Fehler (error) und Validierungsfehler (errors). Werte kommen als Props von der
// Inertia-Seite (session-Flash bzw. $page.props.errors).
export default function Flash({ status, error, errors }) {
    const errorList = errors ? Object.values(errors) : [];

    return (
        <>
            {status && (
                <div className="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 p-3 text-sm text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800">
                    {status}
                </div>
            )}

            {error && (
                <div className="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800">
                    {error}
                </div>
            )}

            {errorList.length > 0 && (
                <div className="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800">
                    <ul className="list-disc ps-5 space-y-1">
                        {errorList.map((msg, i) => (
                            <li key={i}>{msg}</li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}
