import React, { useEffect, useRef, useState } from 'react';
import { Head, router, useForm, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';

// Profilseite (ehemals profile/edit.blade.php + 4 Partials). Vier unabhängige
// Formulare mit je eigenem Inertia-Fehler-Bag (Passwort/Löschen/Token gescoped,
// damit gleichnamige Felder – `name`, `password` – nicht überspringen). Die
// Token-Liste kommt als Deferred-Prop `tokens` asynchron nach.
export default function Profile({ user, urls, flash, strings }) {
    const card = 'p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg';

    return (
        <>
            <Head><title>{strings.profile}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.profile}</h2>}
            />

            <div className="py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className={card}><div className="max-w-xl"><ProfileInfoForm user={user} urls={urls} flash={flash} strings={strings} /></div></div>
                    <div className={card}><div className="max-w-xl"><PasswordForm urls={urls} strings={strings} /></div></div>
                    <div className={card}><div className="max-w-xl"><ApiTokens urls={urls} flash={flash} strings={strings} /></div></div>
                    <div className={card}><div className="max-w-xl"><DeleteAccount urls={urls} strings={strings} /></div></div>
                </div>
            </div>
        </>
    );
}

const label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
const input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const primaryBtn = 'inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50';
const err = (form, k) => form.errors[k] && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;

function Saved({ show, label }) {
    if (!show) return null;
    return <p className="text-sm text-gray-600 dark:text-gray-400 transition">{label}</p>;
}

function ProfileInfoForm({ user, urls, flash, strings }) {
    const form = useForm({ name: user.name, email: user.email, locale: user.locale, notification_display: user.notificationDisplay });

    const submit = (e) => { e.preventDefault(); form.patch(urls.profileUpdate); };

    return (
        <section>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.profileData}</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{strings.profileDataHint}</p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <label htmlFor="name" className={label}>{strings.name}</label>
                    <input id="name" type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus autoComplete="name" className={input} />
                    {err(form, 'name')}
                </div>

                <div>
                    <label htmlFor="email" className={label}>{strings.email}</label>
                    <input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required autoComplete="username" className={input} />
                    {err(form, 'email')}

                    {user.isUnverified && (
                        <div>
                            <p className="text-sm mt-2 text-gray-800 dark:text-gray-100">
                                {strings.unverified}{' '}
                                <button type="button" onClick={() => router.post(urls.verificationSend)} className="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">{strings.resend}</button>
                            </p>
                            {flash?.status === 'verification-link-sent' && (
                                <p className="mt-2 font-medium text-sm text-green-600 dark:text-green-400">{strings.verificationSent}</p>
                            )}
                        </div>
                    )}
                </div>

                <div>
                    <label htmlFor="locale" className={label}>{strings.language}</label>
                    <select id="locale" value={form.data.locale} onChange={(e) => form.setData('locale', e.target.value)} className={input}>
                        <option value="de">{strings.german}</option>
                        <option value="en">{strings.englishUs}</option>
                    </select>
                    {err(form, 'locale')}
                </div>

                <div>
                    <label htmlFor="notification_display" className={label}>{strings.notificationDisplay}</label>
                    <select id="notification_display" value={form.data.notification_display} onChange={(e) => form.setData('notification_display', e.target.value)} className={input}>
                        <option value="dropdown">{strings.notificationDropdown}</option>
                        <option value="sidebar">{strings.notificationSidebar}</option>
                    </select>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{strings.notificationDisplayHint}</p>
                    {err(form, 'notification_display')}
                </div>

                <div className="flex items-center gap-4">
                    <button type="submit" disabled={form.processing} className={primaryBtn}>{strings.save}</button>
                    <Saved show={form.recentlySuccessful} label={strings.saved} />
                </div>
            </form>
        </section>
    );
}

function PasswordForm({ urls, strings }) {
    const form = useForm('updatePassword', { current_password: '', password: '', password_confirmation: '' });

    const submit = (e) => {
        e.preventDefault();
        form.put(urls.passwordUpdate, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <section>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.updatePassword}</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{strings.updatePasswordHint}</p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <label htmlFor="current_password" className={label}>{strings.currentPassword}</label>
                    <input id="current_password" type="password" value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} autoComplete="current-password" className={input} />
                    {err(form, 'current_password')}
                </div>
                <div>
                    <label htmlFor="password" className={label}>{strings.newPassword}</label>
                    <input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" className={input} />
                    {err(form, 'password')}
                </div>
                <div>
                    <label htmlFor="password_confirmation" className={label}>{strings.confirmPassword}</label>
                    <input id="password_confirmation" type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} autoComplete="new-password" className={input} />
                    {err(form, 'password_confirmation')}
                </div>

                <div className="flex items-center gap-4">
                    <button type="submit" disabled={form.processing} className={primaryBtn}>{strings.save}</button>
                    <Saved show={form.recentlySuccessful} label={strings.saved} />
                </div>
            </form>
        </section>
    );
}

function ApiTokens({ urls, flash, strings }) {
    const form = useForm('createApiToken', { name: 'planstack' });
    const [copied, setCopied] = useState(false);

    const submit = (e) => { e.preventDefault(); form.post(urls.tokenStore, { preserveScroll: true }); };
    const copy = () => {
        navigator.clipboard.writeText(flash.apiToken);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };
    const revoke = (token) => {
        if (window.confirm(strings.revokeConfirmTpl.replace('__NAME__', token.name))) {
            router.delete(urls.tokenDestroy.replace('__ID__', String(token.id)), { preserveScroll: true });
        }
    };

    return (
        <section id="api-tokens">
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.apiTokens}</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{strings.apiTokensHint}</p>
            </header>

            {flash?.apiToken && (
                <div className="mt-6 rounded-md border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 p-4">
                    <p className="text-sm font-medium text-green-800 dark:text-green-300">{strings.copyNowTpl.replace('__NAME__', flash.apiTokenName)}</p>
                    <div className="mt-2 flex items-center gap-2">
                        <code className="flex-1 overflow-x-auto whitespace-nowrap rounded bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-800 dark:text-gray-100 ring-1 ring-gray-200 dark:ring-gray-700">{flash.apiToken}</code>
                        <button type="button" onClick={copy} className="shrink-0 rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500">{copied ? strings.copied : strings.copy}</button>
                    </div>
                </div>
            )}

            {flash?.status === 'api-token-revoked' && (
                <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">{strings.tokenRevoked}</p>
            )}

            <form onSubmit={submit} className="mt-6">
                <label htmlFor="token_name" className={label}>{strings.tokenName}</label>
                <div className="mt-1 flex items-center gap-3">
                    <input id="token_name" type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required className="block flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <button type="submit" disabled={form.processing} className={primaryBtn}>{strings.createToken}</button>
                </div>
                {err(form, 'name')}
            </form>

            <div className="mt-6">
                <Deferred data="tokens" fallback={<TokensSkeleton />}>
                    <TokensList revoke={revoke} strings={strings} />
                </Deferred>
            </div>
        </section>
    );
}

function TokensList({ revoke, strings }) {
    const { tokens } = usePage().props;

    if (tokens.length === 0) {
        return <p className="text-sm text-gray-400 dark:text-gray-500">{strings.noTokens}</p>;
    }

    return (
        <>
            {tokens.map((token) => (
                <div key={token.id} className="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 py-2 last:border-0">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{token.name}</p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            {strings.created} {token.created}
                            {' · '}
                            {token.lastUsed ? `${strings.lastUsed} ${token.lastUsed}` : strings.neverUsed}
                        </p>
                    </div>
                    <button type="button" onClick={() => revoke(token)} className="text-xs text-red-500 dark:text-red-400 hover:underline">{strings.revoke}</button>
                </div>
            ))}
        </>
    );
}

function TokensSkeleton() {
    return (
        <div className="animate-pulse space-y-3" aria-hidden="true">
            {Array.from({ length: 2 }).map((_, i) => (
                <div key={i} className="flex items-center justify-between">
                    <div className="w-full">
                        <div className="h-3 w-24 rounded bg-gray-200 dark:bg-gray-700" />
                        <div className="mt-2 h-2.5 w-40 rounded bg-gray-200 dark:bg-gray-700" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function DeleteAccount({ urls, strings }) {
    const [open, setOpen] = useState(false);
    const inputRef = useRef(null);
    const form = useForm('userDeletion', { password: '' });

    // Bei einem Validierungsfehler (falsches Passwort) das Modal automatisch öffnen.
    useEffect(() => {
        if (form.errors.password) setOpen(true);
    }, [form.errors.password]);

    useEffect(() => {
        if (open) inputRef.current?.focus();
    }, [open]);

    const close = () => { setOpen(false); form.clearErrors(); form.reset(); };
    const submit = (e) => { e.preventDefault(); form.delete(urls.destroy, { preserveScroll: true }); };

    return (
        <section className="space-y-6">
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.deleteAccount}</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{strings.deleteAccountHint}</p>
            </header>

            <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{strings.deleteAccount}</button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" onClick={close}></div>
                    <div className="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-800 shadow-xl">
                        <form onSubmit={submit} className="p-6">
                            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">{strings.deleteConfirmTitle}</h2>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{strings.deleteConfirmHint}</p>

                            <div className="mt-6">
                                <label htmlFor="delete_password" className="sr-only">{strings.password}</label>
                                <input ref={inputRef} id="delete_password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} placeholder={strings.password} className="mt-1 block w-3/4 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                {err(form, 'password')}
                            </div>

                            <div className="mt-6 flex justify-end gap-3">
                                <button type="button" onClick={close} className="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">{strings.cancel}</button>
                                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50">{strings.deleteAccount}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </section>
    );
}

Profile.layout = AppShell;
