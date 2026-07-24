import React from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';

// Organisationszugehörigkeit (ehemals organization/index.blade.php). Zeigt die
// eigene Organisation (Mitglieder, Einladen, Austreten/Löschen) oder – ohne
// Organisation – Formulare zum Gründen/Beitreten. Reine Formularseite, kein
// asynchrones Nachladen nötig.
export default function Organization({ tabs, flash, organization, assignableTeams, urls, strings }) {
    return (
        <>
            <Head><title>{strings.organization}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.organization}</h2>}
                subnav={organization && tabs ? <OrganizationTabs tabs={tabs} /> : null}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />

                    {organization ? (
                        <>
                            <OrgCard organization={organization} urls={urls} strings={strings} />
                            {organization.isOwner && <InviteCard assignableTeams={assignableTeams} urls={urls} strings={strings} />}
                        </>
                    ) : (
                        <NoOrg urls={urls} strings={strings} />
                    )}
                </div>
            </div>
        </>
    );
}

const card = 'bg-white dark:bg-gray-800 rounded-lg shadow p-6';
const label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
const input = 'block flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const primaryBtn = 'inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50';
const fieldErr = (msg) => msg && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{msg}</p>;

function OrgCard({ organization, urls, strings }) {
    const { errors } = usePage().props;

    const leave = () => { if (window.confirm(strings.leaveConfirm)) router.post(urls.leave); };
    const destroy = () => { if (window.confirm(strings.deleteConfirmTpl.replace('__NAME__', organization.name))) router.delete(urls.destroy); };

    return (
        <div className={card}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{organization.name}</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {strings.foundedBy} {organization.ownerName}
                        {' · '}{organization.memberCount} {organization.memberCount === 1 ? strings.member : strings.members}
                    </p>
                </div>
            </div>

            <div className="mt-6">
                <h4 className="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">{strings.members}</h4>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-gray-100 dark:border-gray-700 text-left text-gray-400 dark:text-gray-400">
                            <th className="py-2">{strings.name}</th>
                            <th className="py-2">{strings.email}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {organization.members.map((member) => (
                            <tr key={member.id} className="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <td className="py-2 font-medium text-gray-800 dark:text-gray-100">
                                    {member.name}
                                    {member.isFounder && <span className="ms-1 text-xs text-amber-600 dark:text-amber-400">{strings.founder}</span>}
                                    {member.isYou && <span className="ms-1 text-xs text-gray-400 dark:text-gray-500">{strings.you}</span>}
                                </td>
                                <td className="py-2 text-gray-500 dark:text-gray-400">{member.email}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-6 border-t border-gray-100 dark:border-gray-700 pt-5">
                {fieldErr(errors?.leave)}
                {organization.isOwner ? (
                    <div>
                        <button type="button" onClick={destroy} className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{strings.deleteOrganization}</button>
                        <span className="ms-2 text-xs text-gray-400 dark:text-gray-500">{strings.deleteHint}</span>
                    </div>
                ) : (
                    <button type="button" onClick={leave} className="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">{strings.leaveOrganization}</button>
                )}
            </div>
        </div>
    );
}

function InviteCard({ assignableTeams, urls, strings }) {
    const form = useForm({ email: '', team_ids: [] });

    const submit = (e) => { e.preventDefault(); form.post(urls.invite, { onSuccess: () => form.reset() }); };
    const toggleTeam = (id) => {
        const cur = form.data.team_ids;
        form.setData('team_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    return (
        <div className={card}>
            <h3 className="mb-1 font-semibold text-gray-900 dark:text-gray-100">{strings.inviteMembers}</h3>
            <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">{strings.inviteHint}</p>

            <form onSubmit={submit}>
                <label htmlFor="email" className={label}>{strings.emailAddress}</label>
                <div className="mt-1 flex items-center gap-3">
                    <input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required placeholder={strings.emailPlaceholder} className={input} />
                    <button type="submit" disabled={form.processing} className={primaryBtn}>{strings.sendInvitation}</button>
                </div>
                {fieldErr(form.errors.email)}

                {assignableTeams.length > 0 ? (
                    <div className="mt-5 border-t border-gray-100 dark:border-gray-700 pt-5">
                        <span className={label}>{strings.teamsOptional}</span>
                        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.teamsHint}</p>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {assignableTeams.map((team) => (
                                <label key={team.id} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" checked={form.data.team_ids.includes(team.id)} onChange={() => toggleTeam(team.id)} className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500" />
                                    <span>{team.name}</span>
                                </label>
                            ))}
                        </div>
                        {fieldErr(form.errors.team_ids)}
                    </div>
                ) : (
                    <p className="mt-4 border-t border-gray-100 dark:border-gray-700 pt-4 text-xs text-gray-400 dark:text-gray-500">{strings.noTeamsYet}</p>
                )}
            </form>
        </div>
    );
}

function NoOrg({ urls, strings }) {
    const createForm = useForm({ name: '' });
    const joinForm = useForm({ token: '' });

    const create = (e) => { e.preventDefault(); createForm.post(urls.store); };
    const join = (e) => { e.preventDefault(); joinForm.post(urls.join); };

    return (
        <>
            <p className="text-sm text-gray-500 dark:text-gray-400">{strings.noOrgIntro}</p>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className={card}>
                    <h3 className="mb-1 font-semibold text-gray-900 dark:text-gray-100">{strings.createOrganization}</h3>
                    <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">{strings.createHint}</p>
                    <form onSubmit={create}>
                        <label htmlFor="name" className={label}>{strings.organizationName}</label>
                        <div className="mt-1 flex items-center gap-3">
                            <input id="name" type="text" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} required maxLength={100} placeholder={strings.orgNamePlaceholder} className={input} />
                            <button type="submit" disabled={createForm.processing} className={primaryBtn}>{strings.create}</button>
                        </div>
                        {fieldErr(createForm.errors.name)}
                    </form>
                </div>

                <div className={card}>
                    <h3 className="mb-1 font-semibold text-gray-900 dark:text-gray-100">{strings.joinOrganization}</h3>
                    <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">{strings.joinHint}</p>
                    <form onSubmit={join}>
                        <label htmlFor="token" className={label}>{strings.invitationCode}</label>
                        <div className="mt-1 flex items-center gap-3">
                            <input id="token" type="text" value={joinForm.data.token} onChange={(e) => joinForm.setData('token', e.target.value)} required placeholder={strings.codePlaceholder} className={input + ' font-mono text-xs'} />
                            <button type="submit" disabled={joinForm.processing} className={primaryBtn}>{strings.join}</button>
                        </div>
                        {fieldErr(joinForm.errors.token)}
                    </form>
                </div>
            </div>
        </>
    );
}

Organization.layout = AppShell;
