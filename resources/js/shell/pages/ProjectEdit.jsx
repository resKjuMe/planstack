import React from 'react';
import { Head, router, useForm, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import ProjectEditTabs from '../components/ProjectEditTabs.jsx';
import { FormSkeleton } from '../components/Skeleton.jsx';

// Projekt-Einstellungen „Allgemein" (ehemals projects/edit.blade.php). Die
// Formularwerte + das Löschrecht kommen als Deferred-Prop `formData` asynchron
// nach; bis dahin zeigt die Seite ein Formular-Skeleton.
export default function ProjectEdit({ project, editTabs, updateUrl, destroyUrl, strings }) {
    return (
        <>
            <Head><title>{`${strings.title} · ${project.alias}`}</title></Head>

            <PageBands
                header={
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                        {strings.title} – <span className="font-mono">{project.alias}</span>
                    </h2>
                }
                subnav={<ProjectEditTabs tabs={editTabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <Deferred data="formData" fallback={<FormSkeleton rows={5} />}>
                            <EditForm project={project} updateUrl={updateUrl} strings={strings} />
                        </Deferred>
                    </div>

                    <Deferred data="formData" fallback={null}>
                        <DeleteCard destroyUrl={destroyUrl} strings={strings} />
                    </Deferred>
                </div>
            </div>
        </>
    );
}

function EditForm({ project, updateUrl, strings }) {
    const { formData } = usePage().props;
    const form = useForm({ ...formData.values });

    const submit = (e) => {
        e.preventDefault();
        form.put(updateUrl);
    };

    const field = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    const err = (k) => form.errors[k] && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;

    return (
        <form onSubmit={submit} className="space-y-5">
            <div>
                <label htmlFor="alias" className={label}>{strings.keyUnique}</label>
                <input id="alias" type="text" value={form.data.alias} onChange={(e) => form.setData('alias', e.target.value)} required maxLength={20} className={field} />
                {err('alias')}
            </div>

            <div>
                <label htmlFor="name" className={label}>{strings.name}</label>
                <input id="name" type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={100} className={field} />
                {err('name')}
            </div>

            <div>
                <label htmlFor="description" className={label}>{strings.description}</label>
                <textarea id="description" rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className={field}></textarea>
                {err('description')}
            </div>

            <div>
                <label htmlFor="github_repo" className={label}>{strings.githubRepo}</label>
                <input id="github_repo" type="text" value={form.data.github_repo} onChange={(e) => form.setData('github_repo', e.target.value)} maxLength={255} placeholder="owner/repo" className={field + ' font-mono'} />
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.githubHintPre} <span className="font-mono">owner/repo</span> {strings.githubHintPost}</p>
                {err('github_repo')}
            </div>

            <div className="border-t border-gray-100 dark:border-gray-700 pt-5 space-y-4">
                <label htmlFor="completed" className="flex items-start gap-3">
                    <input id="completed" type="checkbox" checked={form.data.completed} onChange={(e) => form.setData('completed', e.target.checked)} className="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <span className="text-sm text-gray-700 dark:text-gray-300">
                        {strings.completedLabel}
                        <span className="block text-xs text-gray-400 dark:text-gray-500">{strings.completedHint}</span>
                    </span>
                </label>
                <label htmlFor="archived" className="flex items-start gap-3">
                    <input id="archived" type="checkbox" checked={form.data.archived} onChange={(e) => form.setData('archived', e.target.checked)} className="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <span className="text-sm text-gray-700 dark:text-gray-300">
                        {strings.archivedLabel}
                        <span className="block text-xs text-gray-400 dark:text-gray-500">{strings.archivedHint}</span>
                    </span>
                </label>
            </div>

            <div className="flex items-center justify-end gap-3">
                <a href={project.showUrl} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</a>
                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.save}</button>
            </div>
        </form>
    );
}

function DeleteCard({ destroyUrl, strings }) {
    const { formData } = usePage().props;
    if (!formData.canDelete) return null;

    const destroy = () => {
        if (window.confirm(strings.deleteConfirm)) router.delete(destroyUrl);
    };

    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-red-100 dark:border-red-900/50">
            <h3 className="font-semibold text-red-700 dark:text-red-300">{strings.deleteTitle}</h3>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{strings.deleteHint}</p>
            <button type="button" onClick={destroy} className="mt-4 inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{strings.delete}</button>
        </div>
    );
}

ProjectEdit.layout = AppShell;
