import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import OrganizationTabs from '../components/OrganizationTabs.jsx';
import Flash from '../components/Flash.jsx';
import ActivityHeatmap from '../components/ActivityHeatmap.jsx';

// Organisations-Unterseite „Aktivität": dieselbe Heatmap wie auf der
// Projekt-Performance und in der persönlichen Statistik, hier über ALLE Projekte der
// Organisation. Die Seite ist reine Hülle — Daten, Filter und Darstellung stecken in
// ActivityHeatmap, die ihre Zähler selbst vom Endpunkt holt (urls.activity).
export default function OrganizationActivity({ tabs, flash, urls, strings }) {
    return (
        <>
            <Head><title>{strings.title}</title></Head>

            <PageBands
                header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">{strings.title}</h2>}
                subnav={<OrganizationTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <Flash status={flash?.status} error={flash?.error} />

                    <p className="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{strings.intro}</p>

                    <ActivityHeatmap url={urls.activity} strings={strings} />
                </div>
            </div>
        </>
    );
}

OrganizationActivity.layout = AppShell;
