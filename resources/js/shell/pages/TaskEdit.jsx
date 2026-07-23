import React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import TaskForm from '../components/TaskForm.jsx';

// Task bearbeiten (ehemals tasks/edit.blade.php).
export default function TaskEdit({ project, task, statuses, criticalities, recommendations, phases, reviewers, candidates, showReview, canDelete, updateUrl, destroyUrl, showUrl, strings }) {
    const form = useForm({ ...task.values });

    const submit = (e) => {
        e.preventDefault();
        form.put(updateUrl);
    };
    const destroy = () => {
        if (window.confirm(strings.deleteConfirm)) router.delete(destroyUrl);
    };

    return (
        <>
            <Head><title>{`${strings.title} · ${project.alias}/${task.name}`}</title></Head>
            <PageBands header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title} – <span className="font-mono">{project.alias}/{task.name}</span></h2>} />

            <div className="py-8">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <TaskForm form={form} statuses={statuses} criticalities={criticalities} recommendations={recommendations} phases={phases} reviewers={reviewers} candidates={candidates} showReview={showReview} strings={strings} />
                            <div className="mt-6 flex items-center justify-end gap-3">
                                <a href={showUrl} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</a>
                                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.submit}</button>
                            </div>
                        </form>
                    </div>

                    {canDelete && (
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-red-100 dark:border-red-900/50">
                            <h3 className="font-semibold text-red-700 dark:text-red-300">{strings.deleteTitle}</h3>
                            <button type="button" onClick={destroy} className="mt-4 inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{strings.delete}</button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

TaskEdit.layout = AppShell;
