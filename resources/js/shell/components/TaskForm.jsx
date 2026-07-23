import React from 'react';

// Gemeinsames Task-Formular (Anlegen/Bearbeiten). Bekommt die Inertia-useForm-
// Instanz + die Optionen/Labels; rendert alle Felder. Pendant zum früheren
// tasks/partials/form.blade.php.
export default function TaskForm({ form, statuses, criticalities, recommendations, phases, reviewers, candidates, showReview, strings }) {
    const set = (k) => (e) => form.setData(k, e.target.value);
    const err = (k) => form.errors[k] && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{form.errors[k]}</p>;
    const input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';

    const togglePre = (id) => {
        const cur = form.data.prerequisites || [];
        form.setData('prerequisites', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    return (
        <div className="space-y-5">
            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <label htmlFor="name" className={label}>{strings.name}</label>
                    <input id="name" type="text" value={form.data.name} onChange={set('name')} required maxLength={50} className={input} />
                    {err('name')}
                </div>
                <div>
                    <label htmlFor="status" className={label}>{strings.status}</label>
                    <select id="status" value={form.data.status} onChange={set('status')} className={input}>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    {err('status')}
                </div>
            </div>

            <div>
                <label htmlFor="summary" className={label}>{strings.summary}</label>
                <input id="summary" type="text" value={form.data.summary} onChange={set('summary')} required maxLength={255} className={input} />
                {err('summary')}
            </div>

            <div className="sm:w-60">
                <label htmlFor="criticality" className={label}>{strings.criticality}</label>
                <select id="criticality" value={form.data.criticality} onChange={set('criticality')} className={input}>
                    <option value="">—</option>
                    {criticalities.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                {err('criticality')}
            </div>

            <div>
                <label htmlFor="description" className={label}>{strings.description}</label>
                <textarea id="description" rows={5} value={form.data.description} onChange={set('description')} className={input}></textarea>
                {err('description')}
            </div>

            <div>
                <label htmlFor="acc" className={label}>{strings.acceptanceCriteria}</label>
                <textarea id="acc" rows={4} value={form.data.description_acceptance_criteria} onChange={set('description_acceptance_criteria')} className={input}></textarea>
                {err('description_acceptance_criteria')}
            </div>

            <div>
                <label htmlFor="ta" className={label}>{strings.targetActual}</label>
                <textarea id="ta" rows={4} value={form.data.description_target_actual} onChange={set('description_target_actual')} placeholder={strings.targetActualPlaceholder} className={input}></textarea>
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.targetActualHint}</p>
                {err('description_target_actual')}
            </div>

            <div>
                <label htmlFor="tc" className={label}>{strings.testCases}</label>
                <textarea id="tc" rows={4} value={form.data.description_test_cases} onChange={set('description_test_cases')} placeholder={strings.testCasesPlaceholder} className={input}></textarea>
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.testCasesHint}</p>
                {err('description_test_cases')}
            </div>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label htmlFor="phase_id" className={label}>{strings.phase}</label>
                    <select id="phase_id" value={form.data.phase_id} onChange={set('phase_id')} className={input}>
                        <option value="">—</option>
                        {phases.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                    {err('phase_id')}
                </div>
                <div>
                    <label htmlFor="md" className={label}>{strings.manDays}</label>
                    <input id="md" type="number" min="0" step="0.1" value={form.data.effort_man_days} onChange={set('effort_man_days')} className={input} />
                    {err('effort_man_days')}
                </div>
                <div>
                    <label htmlFor="sp" className={label}>{strings.storyPoints}</label>
                    <input id="sp" type="number" min="0" value={form.data.effort_story_points} onChange={set('effort_story_points')} className={input} />
                    {err('effort_story_points')}
                </div>
                <div>
                    <label htmlFor="tok" className={label}>{strings.tokens}</label>
                    <input id="tok" type="number" min="0" value={form.data.effort_tokens} onChange={set('effort_tokens')} className={input} />
                    {err('effort_tokens')}
                </div>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <label htmlFor="af" className={label}>{strings.affectedFiles}</label>
                    <input id="af" type="number" min="0" value={form.data.affected_files} onChange={set('affected_files')} className={input + ' sm:w-40'} />
                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{strings.affectedFilesHint}</p>
                    {err('affected_files')}
                </div>
                <div>
                    <label htmlFor="pr" className={label}>{strings.prNumber}</label>
                    <input id="pr" type="number" min="1" value={form.data.pr_number} onChange={set('pr_number')} className={input + ' sm:w-40'} />
                    {err('pr_number')}
                </div>
            </div>

            <div>
                <label htmlFor="rev" className={label}>{strings.reviewedBy}</label>
                <select id="rev" value={form.data.reviewed_by} onChange={set('reviewed_by')} className={input}>
                    <option value="">—</option>
                    {reviewers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                </select>
                {err('reviewed_by')}
            </div>

            {showReview && (
                <div className="rounded-md border border-purple-100 dark:border-purple-900/50 bg-purple-50/40 dark:bg-purple-900/30 p-4 space-y-4">
                    <p className="text-sm font-semibold text-purple-800 dark:text-purple-300">{strings.reviewResult}</p>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label htmlFor="rec" className={label}>{strings.recommendation}</label>
                            <select id="rec" value={form.data.last_review_recommendation} onChange={set('last_review_recommendation')} className={input}>
                                <option value="">—</option>
                                {recommendations.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                            </select>
                            {err('last_review_recommendation')}
                        </div>
                        <div>
                            <label htmlFor="rat" className={label}>{strings.lastReviewedOn}</label>
                            <input id="rat" type="datetime-local" value={form.data.last_reviewed_at} onChange={set('last_reviewed_at')} className={input} />
                            {err('last_reviewed_at')}
                        </div>
                    </div>
                    <div>
                        <label htmlFor="rs" className={label}>{strings.reviewSummary}</label>
                        <textarea id="rs" rows={10} value={form.data.last_review_summary} onChange={set('last_review_summary')} placeholder={strings.reviewSummaryPlaceholder} className={input + ' font-mono text-xs'}></textarea>
                        {err('last_review_summary')}
                    </div>
                </div>
            )}

            {candidates.length > 0 && (
                <div>
                    <label className={label}>{strings.prerequisites}</label>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2 max-h-56 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 p-3">
                        {candidates.map((c) => (
                            <label key={c.id} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={(form.data.prerequisites || []).includes(c.id)} onChange={() => togglePre(c.id)} className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 dark:text-indigo-400" />
                                <span className="font-mono text-indigo-700 dark:text-indigo-400">{c.name}</span>
                                <span className="text-gray-500 dark:text-gray-400 truncate">{c.summary}</span>
                            </label>
                        ))}
                    </div>
                    {err('prerequisites')}
                </div>
            )}
        </div>
    );
}
