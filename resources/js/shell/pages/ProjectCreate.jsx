import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Projekt anlegen (ehemals projects/create.blade.php) als React-Inertia-Formular.
export default function ProjectCreate({ storeUrl, cancelUrl, strings }) {
    const form = useForm({ alias: '', name: '', description: '', github_repo: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(storeUrl);
    };

    const field = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    const err = (k) => form.errors[k] && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title}</h2>} />

            <div className="py-8">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label htmlFor="alias" className={label}>{strings.keyUnique}</label>
                                <input id="alias" type="text" value={form.data.alias} onChange={(e) => form.setData('alias', e.target.value)} required autoFocus maxLength={20} className={field} />
                                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.keyHint}</p>
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

                            <div className="flex items-center justify-end gap-3">
                                <a href={cancelUrl} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</a>
                                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.create}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

ProjectCreate.layout = AppShell;
