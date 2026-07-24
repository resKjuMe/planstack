import React, { useState } from 'react';
import { Head, router, useForm, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import Flash from '../components/Flash.jsx';
import { FormSkeleton } from '../components/Skeleton.jsx';

// Team-Detail (ehemals teams/show.blade.php). Der Teamname steht sofort im Kopf;
// Mitglieder/Rechte/Auswahlliste kommen als Deferred-Prop `teamData` nach.
export default function TeamShow({ team, urls, flash, strings }) {
    return (
        <>
            <Head><title>{`${strings.team} · ${team.name}`}</title></Head>

            <PageBands
                header={
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                        {strings.team} – {team.name}
                    </h2>
                }
            />

            <div className="py-8">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />
                    <Deferred data="teamData" fallback={<FormSkeleton rows={5} />}>
                        <TeamBody team={team} urls={urls} strings={strings} />
                    </Deferred>
                </div>
            </div>
        </>
    );
}

function TeamBody({ team, urls, strings }) {
    const { teamData } = usePage().props;
    const { canUpdate, canManageMembers, canDelete, members, assignableUsers } = teamData;

    const renameForm = useForm({ name: team.name });
    const [userId, setUserId] = useState(assignableUsers[0]?.id ?? '');

    const rename = (e) => {
        e.preventDefault();
        renameForm.patch(urls.update);
    };
    const addMember = (e) => {
        e.preventDefault();
        if (userId) router.post(urls.memberStore, { user_id: userId });
    };
    const removeMember = (member) => {
        if (window.confirm(strings.removeMemberConfirm)) {
            router.delete(urls.memberDestroy.replace('__ID__', String(member.id)));
        }
    };
    const destroy = () => {
        if (window.confirm(strings.deleteConfirm)) router.delete(urls.destroy);
    };

    const input = 'block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

    return (
        <>
            {canUpdate && (
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-4">{strings.renameTeam}</h3>
                    <form onSubmit={rename}>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.teamName}</label>
                        <div className="mt-1 flex flex-wrap items-center gap-3">
                            <div className="flex-1 min-w-64">
                                <input id="name" type="text" value={renameForm.data.name} onChange={(e) => renameForm.setData('name', e.target.value)} required maxLength={100} className={input} />
                            </div>
                            <button type="submit" disabled={renameForm.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.save}</button>
                        </div>
                        {renameForm.errors.name && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{renameForm.errors.name}</p>}
                    </form>
                </div>
            )}

            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-4">{strings.members}</h3>

                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-400 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <th className="py-2">{strings.name}</th>
                            <th className="py-2">{strings.email}</th>
                            <th className="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => (
                            <tr key={member.id} className="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <td className="py-2 font-medium text-gray-800 dark:text-gray-100">
                                    {member.name}
                                    {member.isOwner && <span className="ms-1 text-xs text-amber-600 dark:text-amber-400">{strings.creatorBadge}</span>}
                                </td>
                                <td className="py-2 text-gray-500 dark:text-gray-400">{member.email}</td>
                                <td className="py-2 text-right">
                                    {canManageMembers && !member.isOwner && (
                                        <button type="button" onClick={() => removeMember(member)} className="text-xs text-red-500 dark:text-red-400 hover:underline">{strings.remove}</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {canManageMembers && (
                    <form onSubmit={addMember} className="mt-5 border-t border-gray-100 dark:border-gray-700 pt-5">
                        <label htmlFor="user_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.addMember}</label>
                        {assignableUsers.length > 0 ? (
                            <>
                                <div className="mt-1 flex flex-wrap items-center gap-3">
                                    <div className="flex-1 min-w-64">
                                        <select id="user_id" value={userId} onChange={(e) => setUserId(e.target.value)} required className={input}>
                                            {assignableUsers.map((u) => <option key={u.id} value={u.id}>{u.label}</option>)}
                                        </select>
                                    </div>
                                    <button type="submit" className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{strings.add}</button>
                                </div>
                                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.chooseHint}</p>
                            </>
                        ) : (
                            <p className="mt-1 text-sm text-gray-400 dark:text-gray-500">{strings.allMembersHint}</p>
                        )}
                    </form>
                )}
            </div>

            {canDelete && (
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-red-100 dark:border-red-900/50">
                    <h3 className="font-semibold text-red-700 dark:text-red-300">{strings.deleteTeam}</h3>
                    <button type="button" onClick={destroy} className="mt-4 inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{strings.delete}</button>
                </div>
            )}
        </>
    );
}

TeamShow.layout = AppShell;
