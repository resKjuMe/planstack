import React, { useState } from 'react';
import { Head, router, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import PageHead from '../components/PageHead.jsx';
import ProjectEditTabs from '../components/ProjectEditTabs.jsx';
import Flash from '../components/Flash.jsx';

// Zugriff/Mitglieder (ehemals projects/access.blade.php). Teams/Nutzer/Rollen
// kommen als Deferred-Prop `accessData` asynchron nach (Skeleton währenddessen).
export default function ProjectAccess({ project, editTabs, urls, strings }) {
    const { flash } = usePage().props;

    const Help = () => (
        <div className="space-y-3">
            {[{ h: strings.assignedTeams, b: strings.helpTeams }, { h: strings.rolesHeading, b: strings.helpRoles }].map((sec, i) => (
                <div key={i}>
                    <div className="mb-1 font-semibold text-gray-700 dark:text-gray-300">{sec.h}</div>
                    <ul className="list-disc space-y-1 ps-4">
                        {sec.b.map((x, j) => (
                            <li key={j}>{x.strong && <span className="font-medium">{x.strong}</span>}{x.strong ? ': ' : ''}{x.text}</li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );

    return (
        <>
            <Head><title>{`${strings.editTitle} · ${project.alias}`}</title></Head>

            <PageBands
                header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.editTitle} – <span className="font-mono">{project.alias}</span></h2>}
                subnav={<ProjectEditTabs tabs={editTabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} />
                    <PageHead title={strings.accessTitle} toggleLabel={strings.showHideExplanation}><Help /></PageHead>

                    <Deferred data="accessData" fallback={<AccessSkeleton />}>
                        <AccessBody urls={urls} strings={strings} />
                    </Deferred>
                </div>
            </div>
        </>
    );
}

function AccessBody({ urls, strings }) {
    const { accessData } = usePage().props;
    const { canManage, teams, assignableTeams, users, roles } = accessData;
    const [teamId, setTeamId] = useState(assignableTeams[0]?.id ?? '');
    const [roleByUser, setRoleByUser] = useState(() => Object.fromEntries(users.map((u) => [u.id, u.role])));

    const fill = (tpl, id) => tpl.replace('__ID__', String(id));
    const assignTeam = (e) => {
        e.preventDefault();
        if (teamId) router.post(urls.teamStore, { team_id: teamId });
    };
    const removeTeam = (team) => {
        if (window.confirm(strings.removeTeamConfirm)) router.delete(fill(urls.teamDestroy, team.id));
    };
    const saveRole = (user) => router.patch(fill(urls.memberUpdate, user.id), { role: roleByUser[user.id] });
    const resetRole = (user) => router.delete(fill(urls.memberDestroy, user.id));

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            {/* Zugewiesene Teams */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 className="mb-4 font-semibold text-gray-900 dark:text-gray-100">{strings.assignedTeams}</h3>
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {teams.length === 0 && <p className="text-sm text-gray-400 dark:text-gray-500">{strings.noTeamAssigned}</p>}
                    {teams.map((team) => (
                        <div key={team.id} className="flex items-center justify-between py-2">
                            <div>
                                <span className="font-medium text-gray-800 dark:text-gray-100">{team.name}</span>
                                <span className="ms-2 text-xs text-gray-400 dark:text-gray-500">{strings.countMembers.replace(':count', team.memberCount)}</span>
                            </div>
                            {canManage && (
                                <button type="button" onClick={() => removeTeam(team)} className="text-xs text-red-500 dark:text-red-400 hover:underline">{strings.remove}</button>
                            )}
                        </div>
                    ))}
                </div>

                {canManage && (assignableTeams.length > 0 ? (
                    <form onSubmit={assignTeam} className="mt-5 border-t border-gray-100 dark:border-gray-700 pt-5">
                        <label htmlFor="team_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300">{strings.assignTeam}</label>
                        <div className="mt-1 flex items-center gap-3">
                            <select id="team_id" value={teamId} onChange={(e) => setTeamId(e.target.value)} className="block flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                {assignableTeams.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </select>
                            <button type="submit" className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{strings.assign}</button>
                        </div>
                    </form>
                ) : (
                    <p className="mt-4 text-xs text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700 pt-4">{strings.noFurtherTeams}</p>
                ))}
            </div>

            {/* Rollen */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 className="mb-4 font-semibold text-gray-900 dark:text-gray-100">{strings.rolesHeading}</h3>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                            <th className="py-2">{strings.user}</th>
                            <th className="py-2">{strings.role}</th>
                            <th className="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr key={user.id} className="border-b border-gray-50 dark:border-gray-700 last:border-0">
                                <td className="py-2 font-medium text-gray-800 dark:text-gray-100">
                                    {user.name}
                                    {user.isOwner && <span className="ms-1 text-xs text-amber-600 dark:text-amber-400">{strings.projectOwner}</span>}
                                    <div className="text-xs text-gray-400 dark:text-gray-500">{user.email}</div>
                                </td>
                                <td className="py-2">
                                    {canManage && !user.isOwner ? (
                                        <div className="flex items-center gap-2">
                                            <select value={roleByUser[user.id]} onChange={(e) => setRoleByUser((m) => ({ ...m, [user.id]: e.target.value }))} className="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                                                {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                                            </select>
                                            <button type="button" onClick={() => saveRole(user)} className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">{strings.save}</button>
                                        </div>
                                    ) : (
                                        <span className="text-xs text-gray-500 dark:text-gray-400">{user.roleLabel}</span>
                                    )}
                                </td>
                                <td className="py-2 text-right">
                                    {canManage && !user.isOwner && user.hasMembership && (
                                        <button type="button" onClick={() => resetRole(user)} title={strings.resetToWorker} className="text-xs text-gray-400 dark:text-gray-500 hover:underline">{strings.reset}</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">{strings.accessViaTeams}</p>
            </div>
        </div>
    );
}

function AccessSkeleton() {
    const bar = 'rounded bg-gray-200 dark:bg-gray-700';
    const Card = () => (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 animate-pulse">
            <div className={`mb-4 h-4 w-40 ${bar}`} />
            <div className="space-y-3">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="flex items-center justify-between">
                        <div className={`h-4 w-32 ${bar}`} />
                        <div className={`h-4 w-16 ${bar}`} />
                    </div>
                ))}
            </div>
        </div>
    );
    return (
        <div className="grid gap-6 lg:grid-cols-2" aria-hidden="true">
            <Card />
            <Card />
        </div>
    );
}

ProjectAccess.layout = AppShell;
