import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Team anlegen (ehemals teams/create.blade.php). Reines Formular ohne
// nachzuladende Daten — sofort verfügbar.
export default function TeamCreate({ storeUrl, cancelUrl, strings }) {
    const form = useForm({ name: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(storeUrl);
    };

    const err = (k) => form.errors[k] && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;

    return (
        <>
            <Head><title>{strings.newTeam}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.newTeam}</h2>}
            />

            <div className="py-8">
                <div className="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.teamName}</label>
                                <input id="name" type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus maxLength={100}
                                    className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                {err('name')}
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

TeamCreate.layout = AppShell;
