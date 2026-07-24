import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';

// Status-„On-Enter"-Effekte (ehemals organization/status-effects.blade.php). Je
// Status wird eine editierbare Liste von Effekten (Feld/Wert/nur-wenn-leer) in
// einem Sammel-Formular gepflegt; ein Save-Button speichert alle Status.
// Literale Tailwind-Swatch-Klassen (vom Scanner erfasst) für den Farbpunkt.
const SWATCH = {
    gray: 'bg-gray-500', slate: 'bg-slate-500', indigo: 'bg-indigo-500',
    sky: 'bg-sky-500', blue: 'bg-blue-500', navy: 'bg-blue-700',
    purple: 'bg-purple-500', green: 'bg-green-500', emerald: 'bg-emerald-500',
    teal: 'bg-teal-500', rose: 'bg-rose-500', red: 'bg-red-500',
    orange: 'bg-orange-500', amber: 'bg-amber-500',
};

export default function OrganizationStatusEffects({ tabs, flash, statuses, effectFields, urls, strings }) {
    // Sammel-Formular: statuses[id] = { effects: [ {field, value, only_if_empty} ] }.
    const form = useForm({
        statuses: Object.fromEntries(statuses.map((s) => [s.id, {
            effects: (s.effects ?? []).map((e) => ({
                field: e.field ?? '', value: e.value ?? '', only_if_empty: !!e.only_if_empty,
            })),
        }])),
    });

    const rowsOf = (id) => form.data.statuses[id]?.effects ?? [];
    const setRows = (id, rows) => form.setData('statuses', {
        ...form.data.statuses, [id]: { ...form.data.statuses[id], effects: rows },
    });
    const setRow = (id, idx, key, value) => setRows(id, rowsOf(id).map((r, i) => (i === idx ? { ...r, [key]: value } : r)));
    const addRow = (id) => setRows(id, [...rowsOf(id), { field: '', value: '', only_if_empty: false }]);
    const removeRow = (id, idx) => setRows(id, rowsOf(id).filter((_, i) => i !== idx));
    const saveAll = () => form.put(urls.updateAll, { preserveScroll: true });

    const inputCls = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';

    return (
        <>
            <Head><title>{strings.automationsTitle}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.automationsTitle}</h2>}
                subnav={<OrganizationTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />

                    <div className="flex items-center justify-between gap-4">
                        <p className="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{strings.automationsIntro}</p>
                        <a href={urls.statusesIndex} className="shrink-0 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← {strings.backToStatuses}</a>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    <th className="py-2 pe-4 text-left font-medium">{strings.colStatus}</th>
                                    <th className="py-2 text-left font-medium">{strings.automations}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {statuses.map((status) => {
                                    const rows = rowsOf(status.id);
                                    return (
                                        <tr key={status.id} className="align-top">
                                            {/* Status */}
                                            <td className="py-3 pe-4">
                                                <div className="flex items-center gap-2">
                                                    <span className={`block h-2 w-2 shrink-0 rounded-full ${SWATCH[status.color_token] ?? SWATCH.gray}`}></span>
                                                    {status.icon && (
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                                                            strokeLinecap="round" strokeLinejoin="round"
                                                            className="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                                            dangerouslySetInnerHTML={{ __html: status.icon }} />
                                                    )}
                                                    <span className="font-medium text-gray-900 dark:text-gray-100">{status.label}</span>
                                                    <span className="font-mono text-xs text-gray-400 dark:text-gray-500">{status.key}</span>
                                                </div>
                                            </td>

                                            {/* Automationen (editierbar) */}
                                            <td className="py-3">
                                                <div className="space-y-2">
                                                    {rows.map((row, idx) => (
                                                        <div key={idx} className="flex flex-wrap items-center gap-2">
                                                            <select value={row.field} onChange={(e) => setRow(status.id, idx, 'field', e.target.value)} className={inputCls}>
                                                                <option value="">{strings.effectField}</option>
                                                                {effectFields.map((f) => <option key={f} value={f}>{f}</option>)}
                                                            </select>
                                                            <input type="text" value={row.value} onChange={(e) => setRow(status.id, idx, 'value', e.target.value)}
                                                                placeholder={strings.effectValuePlaceholder} className={`${inputCls} w-52`} />
                                                            <label className="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                                <input type="checkbox" checked={row.only_if_empty} onChange={(e) => setRow(status.id, idx, 'only_if_empty', e.target.checked)}
                                                                    className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                                                {strings.effectOnlyIfEmpty}
                                                            </label>
                                                            <button type="button" onClick={() => removeRow(status.id, idx)}
                                                                className="text-rose-600 dark:text-rose-400 hover:underline">×</button>
                                                        </div>
                                                    ))}
                                                    <div className="pt-1">
                                                        <button type="button" onClick={() => addRow(status.id)}
                                                            className="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{strings.addEffect}</button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex justify-end">
                        <button type="button" onClick={saveAll} disabled={form.processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{strings.save}</button>
                    </div>
                </div>
            </div>
        </>
    );
}

OrganizationStatusEffects.layout = AppShell;
