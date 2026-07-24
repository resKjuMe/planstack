import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';

// Event-Automationen (ehemals organization/events.blade.php): je Fortschritts-
// Event ein Zielstatus (optional) und die Menge der aktuell gehaltenen Status,
// die dabei überschrieben werden dürfen. Alles wird in einem Sammel-Formular
// gespeichert. Die Überschreibbar-Checkboxen sind gesperrt, solange kein
// Zielstatus gewählt ist (React-Ableitung statt Alpine x-model/x-bind).
export default function OrganizationEvents({ tabs, flash, statuses, groups, configs, urls, strings }) {
    // Sammel-Formular: events[eventValue] = { target_status_id, overridable_status_ids: [id] }.
    const initial = {};
    groups.forEach((group) => group.events.forEach((event) => {
        const config = configs?.[event.value];
        initial[event.value] = {
            target_status_id: config?.targetStatusId != null ? String(config.targetStatusId) : '',
            overridable_status_ids: config?.overridableStatusIds ?? [],
        };
    }));

    const form = useForm({ events: initial });

    const setEvent = (value, key, next) => form.setData('events', {
        ...form.data.events,
        [value]: { ...form.data.events[value], [key]: next },
    });

    const setTarget = (value, next) => setEvent(value, 'target_status_id', next);

    const toggleOverridable = (value, statusId) => {
        const current = form.data.events[value].overridable_status_ids;
        const next = current.includes(statusId)
            ? current.filter((id) => id !== statusId)
            : [...current, statusId];
        setEvent(value, 'overridable_status_ids', next);
    };

    const save = () => form.put(urls.update, { preserveScroll: true });

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
                        <a href={urls.effectsIndex}
                            className="shrink-0 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{strings.effectsLink}</a>
                    </div>

                    <div className="space-y-6">
                        {groups.map((group) => (
                            <div key={group.title} className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{group.title}</h3>

                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            <th rowSpan={2} className="py-2 pe-4 text-left align-bottom font-medium">{strings.colEvent}</th>
                                            <th rowSpan={2} className="py-2 pe-4 text-left align-bottom font-medium">{strings.targetStatus}</th>
                                            <th colSpan={statuses.length} className="pb-1 text-center font-medium">{strings.overridable}</th>
                                        </tr>
                                        <tr>
                                            {statuses.map((status) => (
                                                <th key={status.id} className="w-10 px-1 pb-2 pt-1 text-center text-gray-500 dark:text-gray-400"
                                                    title={status.label}>
                                                    <span className="mx-auto flex h-6 w-6 items-center justify-center">
                                                        {status.icon ? (
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                                                                strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true"
                                                                dangerouslySetInnerHTML={{ __html: status.icon }} />
                                                        ) : (
                                                            <span className="text-[10px] font-semibold">{status.label.slice(0, 2)}</span>
                                                        )}
                                                    </span>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                        {group.events.map((event) => {
                                            const data = form.data.events[event.value];
                                            const disabled = data.target_status_id === '';
                                            return (
                                                <tr key={event.value} className="align-top">
                                                    <td className="py-3 pe-4">
                                                        <div className="flex flex-wrap items-baseline gap-x-2">
                                                            <span className="font-medium text-gray-900 dark:text-gray-100">{event.label}</span>
                                                            <span className="font-mono text-xs text-gray-400 dark:text-gray-500">{event.value}</span>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 pe-4">
                                                        <select value={data.target_status_id}
                                                            onChange={(e) => setTarget(event.value, e.target.value)}
                                                            className={`${inputCls} w-full min-w-[10rem]`}>
                                                            <option value="">{strings.noStatusChange}</option>
                                                            {statuses.map((status) => (
                                                                <option key={status.id} value={String(status.id)}>{status.label}</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                    {statuses.map((status) => (
                                                        <td key={status.id} className={`px-1 py-3 text-center ${disabled ? 'opacity-40' : ''}`}>
                                                            <input type="checkbox"
                                                                checked={data.overridable_status_ids.includes(status.id)}
                                                                onChange={() => toggleOverridable(event.value, status.id)}
                                                                disabled={disabled}
                                                                title={status.label}
                                                                className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                                        </td>
                                                    ))}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>

                                <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">{strings.overridableHint}</p>
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

OrganizationEvents.layout = AppShell;
