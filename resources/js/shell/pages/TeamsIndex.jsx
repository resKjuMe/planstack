import React from 'react';
import { Head, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import Flash from '../components/Flash.jsx';
import { CardsSkeleton } from '../components/Skeleton.jsx';

// Teamübersicht (ehemals teams/index.blade.php). Die Teamliste kommt als
// Deferred-Prop `teams` asynchron nach (Skeleton währenddessen).
export default function TeamsIndex({ createUrl, flash, strings }) {
    return (
        <>
            <Head><title>{strings.teams}</title></Head>

            <PageBands
                header={
                    <div className="flex items-center justify-between">
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.teams}</h2>
                        <a href={createUrl} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            + {strings.newTeam}
                        </a>
                    </div>
                }
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Flash status={flash?.status} error={flash?.error} />
                    <Deferred data="teams" fallback={<CardsSkeleton count={4} cols={2} lines={1} />}>
                        <TeamsList strings={strings} />
                    </Deferred>
                </div>
            </div>
        </>
    );
}

function TeamsList({ strings }) {
    const { teams } = usePage().props;

    if (teams.length === 0) {
        return (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500 dark:text-gray-400">
                {strings.noTeams}
            </div>
        );
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {teams.map((team) => (
                <a key={team.id} href={team.showUrl} className="block bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition p-5">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900 dark:text-gray-100">{team.name}</h3>
                        <span className="text-xs text-gray-400 dark:text-gray-500">{strings.countMembersTpl.replace('__COUNT__', team.membersCount)}</span>
                    </div>
                    <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">{strings.creator} {team.ownerName}</p>
                </a>
            ))}
        </div>
    );
}

TeamsIndex.layout = AppShell;
