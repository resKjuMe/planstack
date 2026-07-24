import React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';

// Benutzerdefinierte Task-Felder (ehemals organization/custom-fields.blade.php).
// Bestehende Felder werden in einem Sammel-Formular editiert (Schlüssel
// unveränderlich), neue Felder unten angelegt, Presets als Buttons.
export default function OrganizationCustomFields({ tabs, flash, fields, types, presets, urls, strings }) {
    // Sammel-Formular: fields[id] = { label, label_en, type, validation }.
    const bulk = useForm({
        fields: Object.fromEntries(fields.map((f) => [f.id, {
            label: f.label, label_en: f.label_en, type: f.type, validation: f.validation,
        }])),
    });
    const create = useForm({ label: '', label_en: '', type: types[0]?.value ?? '', validation: '' });

    const setField = (id, key, value) => bulk.setData('fields', { ...bulk.data.fields, [id]: { ...bulk.data.fields[id], [key]: value } });
    const saveAll = () => bulk.put(urls.updateAll, { preserveScroll: true });
    const addField = (e) => { e.preventDefault(); create.post(urls.store, { preserveScroll: true, onSuccess: () => create.reset() }); };
    const addPreset = (id) => router.post(urls.preset, { preset: id }, { preserveScroll: true });
    const destroy = (field) => { if (window.confirm(strings.deleteConfirm)) router.delete(field.destroyUrl, { preserveScroll: true }); };

    const inputCls = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';

    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title}</h2>}
                subnav={<OrganizationTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />

                    <p className="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{strings.intro}</p>

                    {presets.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{strings.presetsLabel}</span>
                            {presets.map((preset) => (
                                <button key={preset.id} type="button" onClick={() => addPreset(preset.id)} title={preset.key}
                                    className="inline-flex items-center gap-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <span className="text-indigo-500">＋</span> {preset.label}
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                        {create.errors.label && <p className="mb-2 text-sm text-red-600 dark:text-red-400">{create.errors.label}</p>}

                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    <th className="py-2 pe-4 text-left font-medium">{strings.colKey}</th>
                                    <th className="py-2 pe-4 text-left font-medium">{strings.colLabel}</th>
                                    <th className="py-2 pe-4 text-left font-medium">{strings.colLabelEn}</th>
                                    <th className="py-2 pe-4 text-left font-medium">{strings.colType}</th>
                                    <th className="py-2 pe-4 text-left font-medium">{strings.colValidation}</th>
                                    <th className="py-2 text-left font-medium"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {fields.length === 0 && (
                                    <tr><td colSpan={6} className="py-4 text-sm text-gray-400 dark:text-gray-500">{strings.noFields}</td></tr>
                                )}
                                {fields.map((field) => {
                                    const d = bulk.data.fields[field.id];
                                    return (
                                        <tr key={field.id} className="align-top">
                                            <td className="py-2 pe-4"><span className="font-mono text-xs text-gray-600 dark:text-gray-300">{field.key}</span></td>
                                            <td className="py-2 pe-4"><input type="text" value={d.label} onChange={(e) => setField(field.id, 'label', e.target.value)} required maxLength={255} className={`${inputCls} w-full min-w-[8rem]`} /></td>
                                            <td className="py-2 pe-4"><input type="text" value={d.label_en} onChange={(e) => setField(field.id, 'label_en', e.target.value)} maxLength={255} className={`${inputCls} w-full min-w-[8rem]`} /></td>
                                            <td className="py-2 pe-4">
                                                <select value={d.type} onChange={(e) => setField(field.id, 'type', e.target.value)} className={inputCls}>
                                                    {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                                </select>
                                            </td>
                                            <td className="py-2 pe-4"><input type="text" value={d.validation} onChange={(e) => setField(field.id, 'validation', e.target.value)} maxLength={255} placeholder={strings.validationPlaceholder} className={`${inputCls} w-full min-w-[10rem] font-mono text-xs`} /></td>
                                            <td className="py-2 text-right">
                                                <button type="button" onClick={() => destroy(field)} title={strings.delete} className="text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
                                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" x2="10" y1="11" y2="17" /><line x1="14" x2="14" y1="11" y2="17" /></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}

                                {/* Neues Feld anlegen */}
                                <tr className="border-t-2 border-dashed border-gray-200 dark:border-gray-700 align-top">
                                    <td className="py-3 pe-4 text-center text-lg leading-none text-indigo-500" aria-hidden>＋</td>
                                    <td className="py-3 pe-4"><input type="text" value={create.data.label} onChange={(e) => create.setData('label', e.target.value)} required maxLength={255} placeholder={strings.colLabel} className={`${inputCls} w-full min-w-[8rem]`} /></td>
                                    <td className="py-3 pe-4"><input type="text" value={create.data.label_en} onChange={(e) => create.setData('label_en', e.target.value)} maxLength={255} placeholder={strings.colLabelEn} className={`${inputCls} w-full min-w-[8rem]`} /></td>
                                    <td className="py-3 pe-4">
                                        <select value={create.data.type} onChange={(e) => create.setData('type', e.target.value)} className={inputCls}>
                                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                        </select>
                                    </td>
                                    <td className="py-3 pe-4"><input type="text" value={create.data.validation} onChange={(e) => create.setData('validation', e.target.value)} maxLength={255} placeholder={strings.validationPlaceholder} className={`${inputCls} w-full min-w-[10rem] font-mono text-xs`} /></td>
                                    <td className="py-3 text-right">
                                        <button type="button" onClick={addField} disabled={create.processing} className="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{strings.addField}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {fields.length > 0 && (
                            <div className="mt-4 flex justify-end">
                                <button type="button" onClick={saveAll} disabled={bulk.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{strings.save}</button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

OrganizationCustomFields.layout = AppShell;
