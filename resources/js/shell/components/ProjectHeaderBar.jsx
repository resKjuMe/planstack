import React from 'react';
import { router } from '@inertiajs/react';

// Projekt-Kopfzeile (React-Pendant zu components/project-header-bar.blade.php +
// project-actions.blade.php): Alias-Badge, Projektname und die Aktionen
// (PR-Sync, Einstellungen, „+ Task"). Sync ist ein Inertia-POST mit Rückfrage;
// danach lädt Inertia die Seite reload-frei neu (frischer Board-Zustand).
export default function ProjectHeaderBar({ project, can, strings }) {
    const sync = () => {
        if (window.confirm(strings.syncConfirm)) {
            router.post(project.syncUrl);
        }
    };

    const secondaryBtn =
        'inline-flex items-center gap-1.5 rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50';

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
                <a
                    href={project.showUrl}
                    className="inline-flex items-center rounded bg-gray-800 px-2.5 py-1 text-sm font-mono font-semibold text-white hover:bg-gray-700"
                >
                    {project.alias}
                </a>
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{project.name}</h2>
            </div>

            <div className="flex items-center gap-2">
                {can.update && (
                    <>
                        <button type="button" onClick={sync} className={secondaryBtn}>
                            <svg className="h-4 w-4 text-gray-500 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.311h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clipRule="evenodd" />
                            </svg>
                            {strings.sync}
                        </button>
                        <a href={project.editUrl} className={secondaryBtn}>
                            <svg className="h-4 w-4 text-gray-500 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd" d="M8.34 1.804A1 1 0 019.32 1h1.36a1 1 0 01.98.804l.295 1.473c.618.16 1.2.4 1.735.708l1.25-.833a1 1 0 011.276.13l.962.962a1 1 0 01.13 1.277l-.833 1.25c.308.535.547 1.117.708 1.735l1.473.294a1 1 0 01.804.98v1.361a1 1 0 01-.804.98l-1.473.295a5.973 5.973 0 01-.708 1.735l.833 1.25a1 1 0 01-.13 1.276l-.962.962a1 1 0 01-1.277.13l-1.25-.833a5.973 5.973 0 01-1.735.708l-.294 1.473a1 1 0 01-.98.804H9.32a1 1 0 01-.98-.804l-.295-1.473a5.973 5.973 0 01-1.735-.708l-1.25.833a1 1 0 01-1.277-.13l-.962-.962a1 1 0 01-.13-1.277l.833-1.25a5.973 5.973 0 01-.708-1.735l-1.473-.294A1 1 0 011 10.68V9.32a1 1 0 01.804-.98l1.473-.295c.16-.618.4-1.2.708-1.735l-.833-1.25a1 1 0 01.13-1.276l.962-.962a1 1 0 011.277-.13l1.25.833a5.973 5.973 0 011.735-.708l.294-1.473zM13.5 10a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z" clipRule="evenodd" />
                            </svg>
                            {strings.settings}
                        </a>
                    </>
                )}
                {can.contribute && (
                    <a
                        href={project.taskCreateUrl}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        {strings.task}
                    </a>
                )}
            </div>
        </div>
    );
}
