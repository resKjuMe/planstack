import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import ProjectHeaderBar from '../components/ProjectHeaderBar.jsx';
import ProjectTabs from '../components/ProjectTabs.jsx';
import PageHead from '../components/PageHead.jsx';
import Flash from '../components/Flash.jsx';
import Board from '../../board/components/Board.jsx';

// Vollständig als React umgesetzte Board-Seite (ehemals projects/show.blade.php).
// Kopfzeile, Tabs, Seitenkopf, Flash und das Kanban-Board sind alle React; die
// Daten kommen als Inertia-Props aus ProjectController@show.
export default function ProjectBoard({ project, can, tabs, boardMeta, flash, strings }) {
    const { errors } = usePage().props;

    return (
        <>
            <Head><title>{`${project.name} · ${strings.boardTitle}`}</title></Head>

            <PageBands
                header={<ProjectHeaderBar project={project} can={can} strings={strings} />}
                subnav={<ProjectTabs tabs={tabs} />}
            />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <Flash status={flash?.status} error={flash?.error} errors={errors} />

                    <PageHead
                        title={strings.boardTitle}
                        toggleLabel={strings.showHideExplanation}
                        bullets={strings.helpBullets}
                    />

                    <Board meta={boardMeta} />
                </div>
            </div>
        </>
    );
}

// Persistentes Layout (Wrapper + Navi bleiben über Navigationen erhalten).
ProjectBoard.layout = AppShell;
