import React, { useState } from 'react';
import { Head, router, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PageHead from '../components/PageHead.jsx';
import ProjectEditTabs from '../components/ProjectEditTabs.jsx';
import Flash from '../components/Flash.jsx';

// Phasen-Verwaltung (ehemals projects/phases.blade.php). Die Phasenliste kommt
// als Deferred-Prop `phases` asynchron nach (Skeleton währenddessen); das
// Anlegen-Formular ist sofort verfügbar.
export default function ProjectPhases({ project, editTabs, canContribute, urls, strings }) {
    const { flash } = usePage().props;
    const [newName, setNewName] = useState('');

    const create = (e) => {
        e.preventDefault();
        if (newName.trim()) router.post(urls.store, { name: newName }, { onSuccess: () => setNewName('') });
    };

    return (
        <>
            <Head><title>{`${strings.editTitle} · ${project.alias}`}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.editTitle} – <span className="font-mono">{project.alias}</span></h2>}
                subnav={<ProjectEditTabs tabs={editTabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />
                    <PageHead title={strings.phasesTitle} toggleLabel={strings.showHideExplanation} bullets={strings.helpBullets} />

                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <Deferred data="phases" fallback={<PhasesSkeleton />}>
                            <PhasesList urls={urls} strings={strings} canContribute={canContribute} />
                        </Deferred>

                        {canContribute && (
                            <form onSubmit={create} className="mt-5 border-t border-gray-100 dark:border-gray-700 pt-5">
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.newPhase}</label>
                                <div className="mt-1 flex items-center gap-3">
                                    <input id="name" type="text" value={newName} onChange={(e) => setNewName(e.target.value)} required maxLength={100} placeholder={strings.placeholder} className="block flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <button type="submit" className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{strings.create}</button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function PhasesList({ urls, strings, canContribute }) {
    const { phases } = usePage().props;
    const [editingId, setEditingId] = useState(null);
    const [editName, setEditName] = useState('');

    const fill = (tpl, id) => tpl.replace('__ID__', String(id));
    const rename = (e, phase) => {
        e.preventDefault();
        router.patch(fill(urls.update, phase.id), { name: editName }, { onSuccess: () => setEditingId(null) });
    };
    const move = (phase, direction) => router.post(fill(urls.move, phase.id), { direction });
    const destroy = (phase) => {
        const msg = strings.deleteConfirm.replace(':name', phase.name).replace(':count', phase.tasksCount);
        if (window.confirm(msg)) router.delete(fill(urls.destroy, phase.id));
    };

    return (
        <>
            <h3 className="mb-4 font-semibold text-gray-900 dark:text-gray-100">{strings.phasesCount.replace(':count', phases.length)}</h3>

            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {phases.length === 0 && <p className="py-3 text-sm text-gray-400 dark:text-gray-500">{strings.noPhases}</p>}
                {phases.map((phase, i) => (
                    <div key={phase.id} className="flex items-center gap-3 py-3">
                        <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-500 dark:text-gray-400">{i + 1}</span>

                        {editingId === phase.id ? (
                            <form onSubmit={(e) => rename(e, phase)} className="flex flex-1 items-center gap-2">
                                <input type="text" value={editName} onChange={(e) => setEditName(e.target.value)} required maxLength={100} autoFocus className="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <button type="submit" className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">{strings.save}</button>
                                <button type="button" onClick={() => setEditingId(null)} className="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</button>
                            </form>
                        ) : (
                            <>
                                <div className="min-w-0 flex-1">
                                    <span className="font-medium text-gray-800 dark:text-gray-100">{phase.name}</span>
                                    <span className="ms-2 text-xs text-gray-400 dark:text-gray-500">{phase.tasksCount} {strings.tasksSuffix}</span>
                                </div>
                                {canContribute && (
                                    <div className="flex shrink-0 items-center gap-1">
                                        <button type="button" onClick={() => move(phase, 'up')} disabled={i === 0} title={strings.moveUp} className="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 disabled:hover:bg-transparent">
                                            <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fillRule="evenodd" d="M10 5a.75.75 0 01.53.22l5 5a.75.75 0 11-1.06 1.06L10 6.81l-4.47 4.47a.75.75 0 01-1.06-1.06l5-5A.75.75 0 0110 5z" clipRule="evenodd" /></svg>
                                        </button>
                                        <button type="button" onClick={() => move(phase, 'down')} disabled={i === phases.length - 1} title={strings.moveDown} className="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 disabled:hover:bg-transparent">
                                            <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fillRule="evenodd" d="M10 15a.75.75 0 01-.53-.22l-5-5a.75.75 0 111.06-1.06L10 13.19l4.47-4.47a.75.75 0 111.06 1.06l-5 5A.75.75 0 0110 15z" clipRule="evenodd" /></svg>
                                        </button>
                                        <button type="button" onClick={() => { setEditingId(phase.id); setEditName(phase.name); }} className="ms-1 inline-flex items-center py-1 text-xs leading-none text-indigo-600 dark:text-indigo-400 hover:underline">{strings.edit}</button>
                                        <button type="button" onClick={() => destroy(phase)} className="ms-1 inline-flex items-center py-1 text-xs leading-none text-red-500 dark:text-red-400 hover:underline">{strings.delete}</button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                ))}
            </div>
        </>
    );
}

function PhasesSkeleton() {
    const bar = 'rounded bg-gray-200 dark:bg-gray-700';
    return (
        <div className="animate-pulse" aria-hidden="true">
            <div className={`mb-4 h-4 w-40 ${bar}`} />
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="flex items-center gap-3 py-3">
                        <span className={`h-6 w-6 shrink-0 rounded-full ${bar}`} />
                        <span className={`h-4 flex-1 ${bar}`} />
                        <span className={`h-4 w-16 ${bar}`} />
                    </div>
                ))}
            </div>
        </div>
    );
}

ProjectPhases.layout = AppShell;
