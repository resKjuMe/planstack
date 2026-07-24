import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';

// Zusätzliche Feld-Automationen je Event (ehemals organization/events-effects.blade.php):
// je Fortschritts-Event eine Liste editierbarer Effekte (Feld / Wert / nur-wenn-leer).
// Die Mittelspalte zeigt readonly die eigenen on-enter-Effekte des auf der Hauptseite
// gewählten Zielstatus ("Automationen der Spalte"). React-Ableitung statt Alpine
// x-for/x-model/splice; alles wird in einem Sammel-Formular gespeichert.
export default function OrganizationEventsEffects({ tabs, flash, groups, configs, effectFields, statusLabels, statusEffects, urls, strings }) {
    // Sammel-Formular: events[eventValue] = { effects: [{ field, value, only_if_empty }] }.
    const initial = {};
    groups.forEach((group) => group.events.forEach((event) => {
        const config = configs?.[event.value];
        initial[event.value] = { effects: (config?.effects ?? []).map((fx) => ({
            field: fx.field ?? '',
            value: fx.value ?? '',
            only_if_empty: !!fx.only_if_empty,
        })) };
    }));

    const form = useForm({ events: initial });

    const setRows = (value, rows) => form.setData('events', {
        ...form.data.events,
        [value]: { effects: rows },
    });

    const addRow = (value) => setRows(value, [
        ...form.data.events[value].effects,
        { field: '', value: '', only_if_empty: false },
    ]);

    const removeRow = (value, idx) => setRows(value, form.data.events[value].effects.filter((_, i) => i !== idx));

    const setRow = (value, idx, key, next) => setRows(value, form.data.events[value].effects.map(
        (row, i) => (i === idx ? { ...row, [key]: next } : row),
    ));

    const save = () => form.put(urls.updateEffects, { preserveScroll: true });

    // effectFields kommt roh als String-Liste (StatusEffects::ALLOWED_FIELDS) oder als
    // {value,label}-Liste — beides unterstützen.
    const fieldOptions = (effectFields ?? []).map((f) => (
        typeof f === 'string' ? { value: f, label: f } : { value: f.value, label: f.label ?? f.value }
    ));

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

                    <div className="flex items-center justify-between gap-4">
                        <p className="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{strings.intro}</p>
                        <div className="flex shrink-0 items-center gap-4 text-sm">
                            <a href={urls.eventsIndex}
                                className="text-indigo-600 dark:text-indigo-400 hover:underline">← {strings.backToEvents}</a>
                        </div>
                    </div>

                    <div className="space-y-6">
                        {groups.map((group) => (
                            <div key={group.title} className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{group.title}</h3>

                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            <th className="py-2 pe-4 text-left font-medium">{strings.colEvent}</th>
                                            <th className="py-2 pe-4 text-left font-medium">{strings.columnAutomations}</th>
                                            <th className="py-2 text-left font-medium">{strings.extraEffects}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                        {group.events.map((event) => {
                                            const rows = form.data.events[event.value].effects;
                                            const target = event.targetStatusId;
                                            const columnEffects = target != null ? (statusEffects?.[target] ?? []) : [];
                                            const targetLabel = target != null ? (statusLabels?.[target] ?? '') : '';
                                            return (
                                                <tr key={event.value} className="align-top">
                                                    {/* Event */}
                                                    <td className="py-3 pe-4">
                                                        <div className="flex flex-wrap items-baseline gap-x-2">
                                                            <span className="font-medium text-gray-900 dark:text-gray-100">{event.label}</span>
                                                            <span className="font-mono text-xs text-gray-400 dark:text-gray-500">{event.value}</span>
                                                        </div>
                                                    </td>

                                                    {/* Automationen der (auf der Hauptseite gewählten) Zielspalte, readonly. */}
                                                    <td className="py-3 pe-4">
                                                        {target != null ? (
                                                            <>
                                                                <div className="mb-1 flex items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                                                                        strokeLinecap="round" strokeLinejoin="round"
                                                                        className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true">
                                                                        <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                                                                    </svg>
                                                                    <span>{targetLabel}</span>
                                                                </div>
                                                                {columnEffects.length > 0 ? (
                                                                    <ul className="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                                        {columnEffects.map((fx, i) => (
                                                                            <li key={i}>
                                                                                <span className="font-mono">{fx.field ?? ''}</span>
                                                                                {' = '}
                                                                                <span className="font-mono">{fx.value ?? ''}</span>
                                                                                {fx.only_if_empty && (
                                                                                    <span className="text-gray-400 dark:text-gray-500"> ({strings.effectOnlyIfEmpty})</span>
                                                                                )}
                                                                            </li>
                                                                        ))}
                                                                    </ul>
                                                                ) : (
                                                                    <p className="text-xs text-gray-400 dark:text-gray-500">—</p>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <p className="text-xs text-gray-400 dark:text-gray-500">—</p>
                                                        )}
                                                    </td>

                                                    {/* Zusätzliche Feld-Automationen (editierbar) */}
                                                    <td className="py-3">
                                                        <div className="space-y-2">
                                                            {rows.map((row, idx) => (
                                                                <div key={idx} className="flex flex-wrap items-center gap-2">
                                                                    <select value={row.field}
                                                                        onChange={(e) => setRow(event.value, idx, 'field', e.target.value)}
                                                                        className={inputCls}>
                                                                        <option value="">{strings.effectField}</option>
                                                                        {fieldOptions.map((f) => (
                                                                            <option key={f.value} value={f.value}>{f.label}</option>
                                                                        ))}
                                                                    </select>
                                                                    <input type="text" value={row.value}
                                                                        onChange={(e) => setRow(event.value, idx, 'value', e.target.value)}
                                                                        placeholder={strings.effectValuePlaceholder}
                                                                        className={`${inputCls} w-48`} />
                                                                    <label className="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                                        <input type="checkbox" checked={row.only_if_empty}
                                                                            onChange={(e) => setRow(event.value, idx, 'only_if_empty', e.target.checked)}
                                                                            className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                                                        {strings.effectOnlyIfEmpty}
                                                                    </label>
                                                                    <button type="button" onClick={() => removeRow(event.value, idx)}
                                                                        className="text-rose-600 dark:text-rose-400 hover:underline">×</button>
                                                                </div>
                                                            ))}
                                                            <div className="pt-1">
                                                                <button type="button" onClick={() => addRow(event.value)}
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
                        ))}
                    </div>

                    <div className="flex justify-end">
                        <button type="button" onClick={save} disabled={form.processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{strings.save}</button>
                    </div>
                </div>
            </div>
        </>
    );
}

OrganizationEventsEffects.layout = AppShell;
