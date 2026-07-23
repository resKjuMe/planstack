import React from 'react';
import { Head, useForm, usePage, Deferred } from '@inertiajs/react';
import AppShell from '../AppShell.jsx';
import PageBands from '../components/PageBands.jsx';
import TaskForm from '../components/TaskForm.jsx';
import { FormSkeleton } from '../components/Skeleton.jsx';

// Task anlegen (ehemals tasks/create.blade.php). Die Options-Listen (Status,
// Kritikalität, Phasen, Reviewer, Kandidaten) kommen als Deferred-Prop `formData`
// asynchron nach — bis dahin zeigt die Seite ein Formular-Skeleton.
export default function TaskCreate({ project, showReview, storeUrl, strings }) {
    return (
        <>
            <Head><title>{`${strings.title} · ${project.alias}`}</title></Head>
            <PageBands header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{strings.title} – <span className="font-mono">{project.alias}</span></h2>} />

            <div className="py-8">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <Deferred data="formData" fallback={<FormSkeleton rows={8} />}>
                            <CreateForm project={project} showReview={showReview} storeUrl={storeUrl} strings={strings} />
                        </Deferred>
                    </div>
                </div>
            </div>
        </>
    );
}

function CreateForm({ project, showReview, storeUrl, strings }) {
    const { formData } = usePage().props;
    const form = useForm({
        name: '', status: 'UNKNOWN', summary: '', criticality: '',
        description: '', description_acceptance_criteria: '', description_target_actual: '', description_test_cases: '',
        phase_id: '', effort_man_days: '', effort_story_points: '', effort_tokens: '',
        affected_files: '', pr_number: '', reviewed_by: '',
        last_review_recommendation: '', last_reviewed_at: '', last_review_summary: '',
        prerequisites: [],
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(storeUrl);
    };

    return (
        <form onSubmit={submit}>
            <TaskForm
                form={form}
                statuses={formData.statuses}
                criticalities={formData.criticalities}
                recommendations={formData.recommendations}
                phases={formData.phases}
                reviewers={formData.reviewers}
                candidates={formData.candidates}
                showReview={showReview}
                strings={strings}
            />
            <div className="mt-6 flex items-center justify-end gap-3">
                <a href={project.showUrl} className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">{strings.cancel}</a>
                <button type="submit" disabled={form.processing} className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{strings.submit}</button>
            </div>
        </form>
    );
}

TaskCreate.layout = AppShell;
