import React, { useState } from 'react';
import { Head, useForm, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import ProjectEditTabs from '../components/ProjectEditTabs.jsx';
import Flash from '../components/Flash.jsx';
import { FormSkeleton } from '../components/Skeleton.jsx';

const DOTS = { g: 'bg-green-500', y: 'bg-amber-500', r: 'bg-red-500' };
const BADGE = { g: '🟢', y: '🟡', r: '🔴' };
const dotClass = (t) => DOTS[t] || 'bg-gray-400';
const badge = (t) => BADGE[t] || '⚪';

// Claude-Konfiguration (ehemals projects/claude.blade.php + Alpine claudeConfig).
// Die eigentliche Konfiguration (Profile/Optionen/Kosten/Werte) kommt als
// Deferred-Prop `config` asynchron nach; Kopf + Info-Karte erscheinen sofort.
export default function ProjectClaude({ configVersion, editTabs, updateUrl, cancelUrl, flash, strings }) {
    return (
        <>
            <Head><title>{`${strings.editTitle} · Claude`}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.editTitle} – <span className="font-mono">Claude</span></h2>}
                subnav={<ProjectEditTabs tabs={editTabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />

                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-3">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h3 className="font-semibold text-gray-800 dark:text-gray-100">{strings.configuration}</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {strings.tokenSavingText} <span className="font-mono">v{configVersion}</span> {strings.headerText} <span className="font-mono">X-Planstack-Config-Version</span>{strings.withoutExtraCall}
                                </p>
                            </div>
                            <span className="shrink-0 inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs font-mono text-gray-600 dark:text-gray-400">v{configVersion}</span>
                        </div>
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            {strings.tokenLoadPerOption} {badge('g')} {strings.low} · {badge('y')} {strings.medium} · {badge('r')} {strings.high}.
                        </p>
                    </div>

                    <Deferred data="config" fallback={<FormSkeleton rows={10} />}>
                        <ClaudeForm updateUrl={updateUrl} cancelUrl={cancelUrl} strings={strings} />
                    </Deferred>
                </div>
            </div>
        </>
    );
}

function ClaudeForm({ updateUrl, cancelUrl, strings }) {
    const { config } = usePage().props;
    const {
        configVersion, profile: initialProfile, presets, defaults, hintKeys, meta,
        values: initialValues, costs, groups, profilePills, skillText,
    } = config;

    const form = useForm({
        profile: initialProfile,
        overrides: { ...initialValues },
        skill_description: skillText || '',
    });
    const { profile, overrides } = form.data;
    const [presetHelp, setPresetHelp] = useState(false);
    const [openHelp, setOpenHelp] = useState({});

    const setProfile = (p) => form.setData('profile', p);
    const select = (key, val) => form.setData('overrides', { ...overrides, [key]: val });
    const isSelected = (key, val) => (overrides[key] ?? '') === val;

    const boolNorm = (v) => (v === true || v === '1' || v === 1 ? '1' : '0');
    const defaultVal = (key) => {
        const preset = presets[profile] || {};
        return preset[key] !== undefined ? preset[key] : defaults[key];
    };
    const defaultNorm = (key) => {
        const m = meta[key];
        const v = defaultVal(key);
        if (!m || m.type === 'int') return String(v);
        return m.type === 'bool' ? boolNorm(v) : String(v);
    };
    const shippedNorm = (key) => {
        const m = meta[key];
        const v = defaults[key];
        return m && m.type === 'bool' ? boolNorm(v) : String(v);
    };
    const effNorm = (key) => {
        const v = overrides[key] ?? '';
        return v !== '' ? String(v) : defaultNorm(key);
    };
    const defaultOptLabel = (key) => {
        const m = meta[key];
        if (!m || m.type === 'int') return String(defaultVal(key));
        return m.options[defaultNorm(key)]?.label || String(defaultVal(key));
    };
    const defaultToken = (key) => {
        const m = meta[key];
        if (!m || m.type === 'int') return 'n';
        return m.options[defaultNorm(key)]?.token || 'n';
    };

    const liveHints = () => {
        const out = [];
        for (const key of hintKeys) {
            const eff = effNorm(key);
            if (eff !== shippedNorm(key)) {
                const m = meta[key];
                const value = m && m.type === 'bool' ? (eff === '1' ? 'true' : 'false') : eff;
                out.push({ key, value });
            }
        }
        return out;
    };

    const tokenIndex = () => Object.keys(costs).reduce((s, k) => s + (costs[k][effNorm(k)] ?? 0), 0);
    const tokenBounds = () => {
        let mn = 0, mx = 0;
        for (const k in costs) {
            const vals = Object.values(costs[k]);
            mn += Math.min(...vals);
            mx += Math.max(...vals);
        }
        return { mn, mx };
    };
    const tokenRatio = () => {
        const { mn } = tokenBounds();
        return mn > 0 ? tokenIndex() / mn : 1;
    };
    const tokenPct = () => {
        const { mn, mx } = tokenBounds();
        return mx > mn ? Math.round(((tokenIndex() - mn) / (mx - mn)) * 100) : 0;
    };
    const tokenBarClass = () => {
        const p = tokenPct();
        return p < 20 ? 'bg-green-500' : p < 55 ? 'bg-amber-500' : 'bg-red-500';
    };

    const submit = (e) => {
        e.preventDefault();
        form.put(updateUrl);
    };
    const err = (k) => form.errors[k] && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;

    const pillBtn = (active) =>
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium ' +
        (active
            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 ring-1 ring-indigo-500'
            : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50');
    const helpIcon = (
        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><path d="M12 17h.01" /></svg>
    );

    const hints = liveHints();

    return (
        <form onSubmit={submit} className="space-y-6">
            {/* Profile */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div className="flex items-center gap-4">
                    <label className="w-52 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">{strings.profilePreset}</label>
                    <div className="flex-1 flex flex-wrap items-center justify-end gap-2">
                        {profilePills.map((p) => (
                            <button key={p.value} type="button" onClick={() => setProfile(p.value)} className={pillBtn(profile === p.value)}>
                                <span className={'h-2 w-2 rounded-full ' + p.dot}></span>
                                {p.label}
                            </button>
                        ))}
                    </div>
                    <button type="button" onClick={() => setPresetHelp((v) => !v)} title={strings.showHideExplanation} className="shrink-0 text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400">{helpIcon}</button>
                </div>
                {presetHelp && (
                    <div className="mt-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        <p>{strings.presetIntro}</p>
                        <ul className="mt-3 space-y-2.5">
                            {profilePills.map((p) => (
                                <li key={p.value}>
                                    <div className="flex items-center gap-1.5 font-medium text-gray-800 dark:text-gray-100"><span className={'h-2 w-2 rounded-full ' + p.dot}></span>{p.label}</div>
                                    <div className="ms-3.5">{p.desc}</div>
                                    <div className="ms-3.5"><span className="font-medium text-green-700 dark:text-green-300">{strings.pro}</span> {p.pro}</div>
                                    <div className="ms-3.5"><span className="font-medium text-rose-700 dark:text-rose-300">{strings.con}</span> {p.con}</div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {err('profile')}
            </div>

            {/* Token-Schätzung */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div className="flex items-baseline justify-between gap-4">
                    <h4 className="font-semibold text-gray-700 dark:text-gray-300">{strings.estimatedTokenUsage}</h4>
                    <span className="shrink-0 text-lg font-bold text-gray-800 dark:text-gray-100">× {tokenRatio().toFixed(1)}</span>
                </div>
                <div className="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div className={'h-full rounded-full transition-all duration-300 ' + tokenBarClass()} style={{ width: `${Math.max(2, tokenPct())}%` }}></div>
                </div>
                <div className="mt-2 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                    <span>{strings.minimal10}</span><span>{strings.maximal}</span>
                </div>
                <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                    {strings.roughEstimatePre} <span className="font-medium">{strings.executionModel}</span> {strings.and} <span className="font-medium">{strings.contextBetweenTasks}</span>.
                </p>
            </div>

            {/* Gruppen */}
            {groups.map((group) => (
                <div key={group.title} className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 className="font-semibold text-gray-700 dark:text-gray-300">{group.title}</h4>
                    {group.desc && <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5 mb-2">{group.desc}</p>}
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {group.settings.map((s) => (
                            <div key={s.key} className="py-3">
                                <div className="flex items-center gap-4">
                                    <label className="w-52 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">{s.label}</label>
                                    <div className="flex-1 flex flex-wrap items-center justify-end gap-3">
                                        {s.type === 'int' ? (
                                            <>
                                                <button type="button" onClick={() => select(s.key, '')} className={pillBtn(isSelected(s.key, ''))}>
                                                    {strings.defaultWord} {defaultOptLabel(s.key)}
                                                </button>
                                                <input type="range" min="1" max="32" step="1"
                                                    value={overrides[s.key] !== '' ? overrides[s.key] : defaultVal(s.key)}
                                                    onChange={(e) => select(s.key, e.target.value)} className="w-48 accent-indigo-600" />
                                                <span className="w-7 text-right text-sm font-semibold text-gray-800 dark:text-gray-100">{overrides[s.key] !== '' ? overrides[s.key] : defaultVal(s.key)}</span>
                                            </>
                                        ) : (
                                            <>
                                                <button type="button" onClick={() => select(s.key, '')} className={pillBtn(isSelected(s.key, ''))}>
                                                    <span className={'h-2 w-2 rounded-full ' + dotClass(defaultToken(s.key))}></span>
                                                    <span>{strings.defaultWord} {defaultOptLabel(s.key)}</span>
                                                </button>
                                                {s.options.map((opt) => (
                                                    <button key={opt.value} type="button" onClick={() => select(s.key, opt.value)} className={pillBtn(isSelected(s.key, opt.value))}>
                                                        <span className={'h-2 w-2 rounded-full ' + dotClass(opt.token)}></span>
                                                        {opt.label}
                                                    </button>
                                                ))}
                                            </>
                                        )}
                                    </div>
                                    <button type="button" onClick={() => setOpenHelp((m) => ({ ...m, [s.key]: !m[s.key] }))} title={strings.showHideExplanation} className="shrink-0 text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400">{helpIcon}</button>
                                </div>
                                {openHelp[s.key] && (
                                    <div className="mt-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                        <p>{s.desc}</p>
                                        {s.options.length > 0 && (
                                            <ul className="mt-3 space-y-2.5">
                                                {s.options.map((opt) => (
                                                    <li key={opt.value}>
                                                        <div className="flex items-center gap-1.5 font-medium text-gray-800 dark:text-gray-100">
                                                            <span className={'h-2 w-2 rounded-full ' + dotClass(opt.token)}></span>
                                                            {opt.label}
                                                            {opt.token !== 'n' && <span className="font-normal text-gray-400 dark:text-gray-500">· {strings.tokenLoad} {{ g: strings.low, y: strings.medium, r: strings.high }[opt.token]}</span>}
                                                        </div>
                                                        <div className="ms-3.5"><span className="font-medium text-green-700 dark:text-green-300">{strings.pro}</span> {opt.pro}</div>
                                                        <div className="ms-3.5"><span className="font-medium text-rose-700 dark:text-rose-300">{strings.con}</span> {opt.con}</div>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                )}
                                {err('overrides.' + s.key)}
                            </div>
                        ))}
                    </div>
                </div>
            ))}

            {/* Client-Hinweise + Skill */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h4 className="font-semibold text-gray-700 dark:text-gray-300 mb-2">{strings.activeClientHints}</h4>
                {hints.length > 0 ? (
                    <div>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mb-2">{strings.serverTransmits}</p>
                        <ul className="text-sm font-mono text-gray-600 dark:text-gray-400 space-y-1">
                            {hints.map((h) => <li key={h.key}>{h.key} = {h.value}</li>)}
                        </ul>
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{strings.noneBuiltIn}</p>
                )}

                <div className="mt-5 border-t border-gray-100 dark:border-gray-700 pt-5">
                    <label htmlFor="skill_description" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.skillLabel}</label>
                    <p className="mt-1 mb-2 text-xs text-gray-400 dark:text-gray-500">
                        {strings.skillHintPre} <span className="font-mono">v{configVersion}</span>{strings.skillHintMid} <span className="font-mono">/config → instructions</span>. <span className="font-mono">{'{{alias}}'}</span> {strings.and} <span className="font-mono">{'{{name}}'}</span> {strings.skillHintReplace}
                    </p>
                    <textarea id="skill_description" rows={14} spellCheck={false}
                        value={form.data.skill_description} onChange={(e) => form.setData('skill_description', e.target.value)}
                        placeholder={strings.skillPlaceholder}
                        className="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-xs"></textarea>
                    {err('skill_description')}
                </div>
            </div>

            <div className="flex items-center justify-end gap-3">
                <a href={cancelUrl} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</a>
                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.save}</button>
            </div>
        </form>
    );
}

ProjectClaude.layout = AppShell;
