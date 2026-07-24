import React, { useEffect, useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';

// Task-Status-Verwaltung (ehemals organization/statuses.blade.php). Sammel-Form
// für alle Status-Zeilen (Darstellung, Reihenfolge, Gruppe, WIP), ein Anlege-
// Formular, Collapse-Gruppen und die Übergangs-Matrix. Farb- und Icon-Picker
// als React-Flyouts (schließen bei Klick außerhalb).

// Literale Swatch-Klassen (von Tailwind gescannt) für die Farb-Tokens.
const SWATCH = {
    gray: 'bg-gray-500', slate: 'bg-slate-500', indigo: 'bg-indigo-500',
    sky: 'bg-sky-500', blue: 'bg-blue-500', navy: 'bg-blue-700',
    purple: 'bg-purple-500', green: 'bg-green-500', emerald: 'bg-emerald-500',
    teal: 'bg-teal-500', rose: 'bg-rose-500', red: 'bg-red-500',
    orange: 'bg-orange-500', amber: 'bg-amber-500',
};

const inputCls = 'rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm';
// Gemeinsames Spaltenraster für Kopf, Status-Zeilen und Anlege-Formular.
const grid = 'grid items-center gap-x-3 grid-cols-[3.5rem_11rem_minmax(7rem,1fr)_minmax(7rem,1fr)_4.5rem_5.5rem_4.5rem_9rem_9rem]';

// Schließt ein offenes Flyout, wenn außerhalb geklickt wird.
function useOutsideClose(open, onClose) {
    const ref = useRef(null);
    useEffect(() => {
        if (!open) return undefined;
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open, onClose]);
    return ref;
}

function ColorPicker({ value, colors, onChange, title }) {
    const [open, setOpen] = useState(false);
    const ref = useOutsideClose(open, () => setOpen(false));
    return (
        <div className="relative shrink-0" ref={ref}>
            <button type="button" onClick={() => setOpen((o) => !o)} title={title} className="p-1">
                <span className={`block h-2 w-2 rounded-full ${SWATCH[value] || ''}`} />
            </button>
            {open && (
                <div className="absolute z-10 mt-1 grid grid-cols-7 gap-1.5 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg">
                    {colors.map((token) => (
                        <button key={token} type="button" title={token}
                            onClick={() => { onChange(token); setOpen(false); }}
                            className={`h-5 w-5 rounded-full ${SWATCH[token]} ${value === token ? 'ring-2 ring-offset-1 ring-gray-800 dark:ring-gray-200 dark:ring-offset-gray-800' : ''}`} />
                    ))}
                </div>
            )}
        </div>
    );
}

// Platzhalter-Glyphe (gestrichelter Kreis), wenn kein Icon gewählt ist.
const ICON_PLACEHOLDER = '<circle cx="12" cy="12" r="9" stroke-dasharray="3 3"/>';

function IconPicker({ value, iconKeys, iconMarkup, onChange, strings }) {
    const [open, setOpen] = useState(false);
    const ref = useOutsideClose(open, () => setOpen(false));
    return (
        <div className="relative shrink-0" ref={ref}>
            <button type="button" onClick={() => setOpen((o) => !o)} title={strings.colIcon}
                className="flex items-center rounded p-1 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                    className="h-4 w-4" aria-hidden="true"
                    dangerouslySetInnerHTML={{ __html: iconMarkup[value] || ICON_PLACEHOLDER }} />
            </button>
            {open && (
                <div className="absolute z-10 mt-1 grid w-max grid-cols-6 gap-1 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg">
                    <button type="button" onClick={() => { onChange(''); setOpen(false); }} title={strings.noIcon}
                        className={`flex h-7 w-7 items-center justify-center rounded text-xs text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 ${value === '' ? 'ring-2 ring-gray-800 dark:ring-gray-200' : ''}`}>—</button>
                    {iconKeys.map((ik) => (
                        <button key={ik} type="button" onClick={() => { onChange(ik); setOpen(false); }} title={ik}
                            className={`flex h-7 w-7 items-center justify-center rounded text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 ${value === ik ? 'ring-2 ring-gray-800 dark:ring-gray-200' : ''}`}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                                className="h-4 w-4" aria-hidden="true"
                                dangerouslySetInnerHTML={{ __html: iconMarkup[ik] }} />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function OrganizationStatuses({
    tabs, flash, statuses, transitions, colors, iconKeys, iconMarkup, groups, kinds, urls, strings,
}) {
    // Reihenfolge der Zeilen als eigener State (Auf/Ab-Buttons); beim Speichern
    // wird der Index jeder Zeile als position übernommen.
    const [order, setOrder] = useState(statuses.map((s) => s.id));

    // Sammel-Form: statuses[id] = { label, label_en, color_token, icon, ... }.
    const bulk = useForm({
        statuses: Object.fromEntries(statuses.map((s) => [s.id, {
            label: s.label ?? '',
            label_en: s.label_en ?? '',
            color_token: s.color_token,
            icon: s.icon ?? '',
            is_column: !!s.is_column,
            default_expanded: !!s.default_expanded,
            wip_limit: s.wip_limit ?? '',
            group_id: s.group_id ?? '',
        }])),
    });

    const create = useForm({
        color_token: 'indigo',
        icon: '',
        kind: kinds[0]?.value ?? '',
        label: '',
        label_en: '',
        is_column: true,
        default_expanded: false,
        wip_limit: '',
        group_id: '',
    });

    const statusById = Object.fromEntries(statuses.map((s) => [s.id, s]));

    const setField = (id, key, value) => bulk.setData('statuses', {
        ...bulk.data.statuses,
        [id]: { ...bulk.data.statuses[id], [key]: value },
    });

    const move = (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= order.length) return;
        const next = [...order];
        [next[index], next[target]] = [next[target], next[index]];
        setOrder(next);
    };

    const saveAll = () => {
        // Position aus der aktuellen Reihenfolge in jede Zeile schreiben.
        bulk.transform((data) => ({
            statuses: Object.fromEntries(order.map((id, i) => [id, { ...data.statuses[id], position: i }])),
        })).put(urls.updateAll, { preserveScroll: true });
    };

    const addStatus = (e) => {
        e.preventDefault();
        create.post(urls.store, { preserveScroll: true, onSuccess: () => create.reset() });
    };

    const destroyStatus = (id) => {
        if (window.confirm(strings.deleteStatusConfirm)) {
            router.delete(urls.destroy.replace('__ID__', id), { preserveScroll: true });
        }
    };

    // ---- Collapse-Gruppen ----
    const groupForm = useForm({ label: '' });
    const addGroup = (e) => {
        e.preventDefault();
        groupForm.post(urls.groupStore, { preserveScroll: true, onSuccess: () => groupForm.reset() });
    };
    const destroyGroup = (id) => {
        if (window.confirm(`${strings.deleteGroup}?`)) {
            router.delete(urls.groupDestroy.replace('__ID__', id), { preserveScroll: true });
        }
    };

    // ---- Übergangs-Matrix ----
    const [trans, setTrans] = useState(() => Object.fromEntries(
        statuses.map((s) => [s.id, new Set(transitions[s.id] || [])]),
    ));
    const toggleTransition = (fromId, toId) => {
        setTrans((prev) => {
            const set = new Set(prev[fromId]);
            if (set.has(toId)) set.delete(toId); else set.add(toId);
            return { ...prev, [fromId]: set };
        });
    };
    const [savingTransitions, setSavingTransitions] = useState(false);
    const saveTransitions = () => {
        setSavingTransitions(true);
        router.put(urls.transitions, {
            transitions: Object.fromEntries(statuses.map((s) => [s.id, [...trans[s.id]]])),
        }, { preserveScroll: true, onFinish: () => setSavingTransitions(false) });
    };

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
                        <a href={urls.effectsIndex} className="shrink-0 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{strings.automationsLink}</a>
                    </div>

                    {/* ============ Status bearbeiten (Section A) ============ */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                        <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{strings.statusesHeading}</h3>

                        {/* Kopfzeile */}
                        <div className={`${grid} border-b pb-2 text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500`}>
                            <div />
                            <div>{strings.colKey}</div>
                            <div>{strings.colLabel}</div>
                            <div>{strings.colLabelEn}</div>
                            <div className="text-center">{strings.colIsColumn}</div>
                            <div className="text-center">{strings.colExpanded}</div>
                            <div>{strings.colWip}</div>
                            <div>{strings.colGroup}</div>
                            <div />
                        </div>

                        {bulk.errors.status && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{bulk.errors.status}</p>}

                        <div>
                            {order.map((id, index) => {
                                const s = statusById[id];
                                const d = bulk.data.statuses[id];
                                if (!s || !d) return null;
                                return (
                                    <div key={id} className="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                        <div className={`${grid} py-2`}>
                                            {/* Auf/Ab-Sortierung */}
                                            <div className="flex items-center justify-center gap-0.5 text-gray-400 dark:text-gray-500">
                                                <button type="button" onClick={() => move(index, -1)} disabled={index === 0}
                                                    title={strings.dragToSort} aria-label={strings.dragToSort}
                                                    className="leading-none hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30">▲</button>
                                                <button type="button" onClick={() => move(index, 1)} disabled={index === order.length - 1}
                                                    title={strings.dragToSort} aria-label={strings.dragToSort}
                                                    className="leading-none hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30">▼</button>
                                            </div>

                                            {/* Farbe + Icon + Schlüssel */}
                                            <div className="flex min-w-0 items-center gap-1.5">
                                                <ColorPicker value={d.color_token} colors={colors} title={strings.colColor}
                                                    onChange={(v) => setField(id, 'color_token', v)} />
                                                <IconPicker value={d.icon} iconKeys={iconKeys} iconMarkup={iconMarkup} strings={strings}
                                                    onChange={(v) => setField(id, 'icon', v)} />
                                                <span className="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{s.key}</span>
                                            </div>

                                            <input type="text" value={d.label} onChange={(e) => setField(id, 'label', e.target.value)} required maxLength={255} className={`${inputCls} w-full min-w-0`} />
                                            <input type="text" value={d.label_en} onChange={(e) => setField(id, 'label_en', e.target.value)} maxLength={255} className={`${inputCls} w-full min-w-0`} />
                                            <div className="text-center">
                                                <input type="checkbox" checked={d.is_column} onChange={(e) => setField(id, 'is_column', e.target.checked)}
                                                    className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                            </div>
                                            <div className="text-center">
                                                <input type="checkbox" checked={d.default_expanded} onChange={(e) => setField(id, 'default_expanded', e.target.checked)}
                                                    className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                            </div>
                                            <input type="number" value={d.wip_limit} onChange={(e) => setField(id, 'wip_limit', e.target.value)} min="1" placeholder="—" className={`${inputCls} w-full min-w-0`} />
                                            <select value={d.group_id} onChange={(e) => setField(id, 'group_id', e.target.value)} className={`${inputCls} w-full min-w-0`}>
                                                <option value="">{strings.noGroup}</option>
                                                {groups.map((g) => <option key={g.id} value={g.id}>{g.label}</option>)}
                                            </select>

                                            <div className="flex items-center justify-start gap-2">
                                                {s.role === null ? (
                                                    <button type="button" onClick={() => destroyStatus(id)} title={strings.delete}
                                                        className="block text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300">
                                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" x2="10" y1="11" y2="17" /><line x1="14" x2="14" y1="11" y2="17" /></svg>
                                                    </button>
                                                ) : (
                                                    <span className="block h-4 w-4" aria-hidden />
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="mt-4 flex justify-end">
                            <button type="button" onClick={saveAll} disabled={bulk.processing}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                {strings.save}
                            </button>
                        </div>

                        {/* Neuer Status (Section B) */}
                        <form onSubmit={addStatus} className={`${grid} border-t-2 border-dashed border-gray-200 dark:border-gray-700 py-3`}>
                            <span className="text-center text-lg leading-none text-indigo-500" aria-hidden>＋</span>
                            <div className="flex min-w-0 items-center gap-1.5">
                                <ColorPicker value={create.data.color_token} colors={colors} title={strings.colColor}
                                    onChange={(v) => create.setData('color_token', v)} />
                                <IconPicker value={create.data.icon} iconKeys={iconKeys} iconMarkup={iconMarkup} strings={strings}
                                    onChange={(v) => create.setData('icon', v)} />
                                <select value={create.data.kind} onChange={(e) => create.setData('kind', e.target.value)} title={strings.kind} className={`${inputCls} w-full min-w-0`}>
                                    {kinds.map((k) => <option key={k.value} value={k.value}>{k.label}</option>)}
                                </select>
                            </div>
                            <input type="text" value={create.data.label} onChange={(e) => create.setData('label', e.target.value)} required maxLength={255} placeholder={strings.colLabel} className={`${inputCls} w-full min-w-0`} />
                            <input type="text" value={create.data.label_en} onChange={(e) => create.setData('label_en', e.target.value)} maxLength={255} placeholder={strings.colLabelEn} className={`${inputCls} w-full min-w-0`} />
                            <div className="text-center">
                                <input type="checkbox" checked={create.data.is_column} onChange={(e) => create.setData('is_column', e.target.checked)} title={strings.colIsColumn}
                                    className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                            </div>
                            <div className="text-center">
                                <input type="checkbox" checked={create.data.default_expanded} onChange={(e) => create.setData('default_expanded', e.target.checked)} title={strings.colExpanded}
                                    className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                            </div>
                            <input type="number" value={create.data.wip_limit} onChange={(e) => create.setData('wip_limit', e.target.value)} min="1" placeholder="—" title={strings.colWip} className={`${inputCls} w-full min-w-0`} />
                            <select value={create.data.group_id} onChange={(e) => create.setData('group_id', e.target.value)} title={strings.colGroup} className={`${inputCls} w-full min-w-0`}>
                                <option value="">{strings.noGroup}</option>
                                {groups.map((g) => <option key={g.id} value={g.id}>{g.label}</option>)}
                            </select>
                            <div className="flex justify-start">
                                <button type="submit" disabled={create.processing}
                                    className="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                    {strings.createStatus}
                                </button>
                            </div>
                        </form>
                        {create.errors.label && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{create.errors.label}</p>}
                    </div>

                    {/* ============ Collapse-Gruppen (Section C) ============ */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 className="mb-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{strings.groupsTitle}</h3>
                        <p className="mb-4 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{strings.groupsIntro}</p>

                        {groups.length > 0 ? (
                            <ul className="mb-4 divide-y divide-gray-100 dark:divide-gray-700">
                                {groups.map((g) => (
                                    <li key={g.id} className="flex items-center justify-between py-2">
                                        <span className="text-sm text-gray-800 dark:text-gray-200">
                                            {g.label}
                                            <span className="ms-2 font-mono text-xs text-gray-400 dark:text-gray-500">{g.key}</span>
                                        </span>
                                        <button type="button" onClick={() => destroyGroup(g.id)}
                                            className="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">{strings.deleteGroup}</button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mb-4 text-sm text-gray-400 dark:text-gray-500">{strings.noGroups}</p>
                        )}

                        <form onSubmit={addGroup} className="flex items-end gap-3">
                            <div>
                                <label htmlFor="group-label" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.groupLabel}</label>
                                <input id="group-label" type="text" value={groupForm.data.label} onChange={(e) => groupForm.setData('label', e.target.value)} required maxLength={255} className={`${inputCls} mt-1 w-64`} />
                                {groupForm.errors.label && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{groupForm.errors.label}</p>}
                            </div>
                            <button type="submit" disabled={groupForm.processing}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                {strings.addGroup}
                            </button>
                        </form>
                    </div>

                    {/* ============ Übergänge (Section D) ============ */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
                        <h3 className="mb-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{strings.transitionsTitle}</h3>
                        <p className="mb-4 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{strings.transitionsIntro}</p>

                        <table className="text-sm">
                            <thead>
                                <tr>
                                    <th className="p-2 text-left text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{strings.transitionsFrom}</th>
                                    {statuses.map((to) => (
                                        <th key={to.id} className="p-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                            <span className="[writing-mode:vertical-rl]">{to.label}</span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {statuses.map((from) => (
                                    <tr key={from.id} className="border-t border-gray-100 dark:border-gray-700">
                                        <td className="p-2 font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{from.label}</td>
                                        {statuses.map((to) => (
                                            <td key={to.id} className="p-2 text-center">
                                                {from.id === to.id ? (
                                                    <span className="text-gray-300 dark:text-gray-600">·</span>
                                                ) : (
                                                    <input type="checkbox"
                                                        checked={trans[from.id]?.has(to.id) || false}
                                                        onChange={() => toggleTransition(from.id, to.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="mt-5">
                            <button type="button" onClick={saveTransitions} disabled={savingTransitions}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                {strings.saveTransitions}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

OrganizationStatuses.layout = AppShell;
