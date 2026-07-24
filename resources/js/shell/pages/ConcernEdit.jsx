import React from 'react';
import { Head, useForm, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import Flash from '../components/Flash.jsx';
import { FormSkeleton } from '../components/Skeleton.jsx';

// Concern erfassen/bearbeiten (ehemals concerns/edit.blade.php). Die bestehenden
// Concern-Werte kommen als Deferred-Prop `formData` asynchron nach; bis dahin
// zeigt die Seite ein Formular-Skeleton.
export default function ConcernEdit({ project, task, updateUrl, cancelUrl, flash, strings }) {
    return (
        <>
            <Head><title>{`${strings.concern} · ${project.alias}/${task.name}`}</title></Head>

            <PageBands
                header={
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                        {strings.concern} – <span className="font-mono">{project.alias}/{task.name}</span>
                    </h2>
                }
            />

            <div className="py-8">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Flash status={flash?.status} error={flash?.error} />

                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <Deferred data="formData" fallback={<FormSkeleton rows={5} />}>
                            <ConcernForm updateUrl={updateUrl} cancelUrl={cancelUrl} strings={strings} />
                        </Deferred>
                    </div>
                </div>
            </div>
        </>
    );
}

function ConcernForm({ updateUrl, cancelUrl, strings }) {
    const { formData } = usePage().props;
    const form = useForm({ ...formData.values });

    const submit = (e) => {
        e.preventDefault();
        form.put(updateUrl);
    };

    const label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    const area = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const set = (k) => (e) => form.setData(k, e.target.value);
    const err = (k) => form.errors[k] && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;

    return (
        <form onSubmit={submit} className="space-y-5">
            <div>
                <label htmlFor="summary" className={label}>{strings.summary}</label>
                <input id="summary" type="text" value={form.data.summary} onChange={set('summary')} required maxLength={255} className={area} />
                {err('summary')}
            </div>

            <div>
                <label htmlFor="description_context" className={label}>{strings.context}</label>
                <textarea id="description_context" rows={4} value={form.data.description_context} onChange={set('description_context')} className={area}></textarea>
            </div>

            <div>
                <label htmlFor="description_blocker" className={label}>{strings.blocker}</label>
                <textarea id="description_blocker" rows={4} value={form.data.description_blocker} onChange={set('description_blocker')} className={area}></textarea>
            </div>

            <div>
                <label htmlFor="description_misconception" className={label}>{strings.misconception}</label>
                <textarea id="description_misconception" rows={4} value={form.data.description_misconception} onChange={set('description_misconception')} className={area}></textarea>
            </div>

            <div>
                <label htmlFor="description_decisions" className={label}>{strings.decisions}</label>
                <textarea id="description_decisions" rows={4} value={form.data.description_decisions} onChange={set('description_decisions')} className={area + ' font-mono text-sm'}></textarea>
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.decisionsHint} <code>{strings.decisionsExample}</code></p>
            </div>

            <div className="flex items-center justify-end gap-3">
                <a href={cancelUrl} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</a>
                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.save}</button>
            </div>
        </form>
    );
}

ConcernEdit.layout = AppShell;
