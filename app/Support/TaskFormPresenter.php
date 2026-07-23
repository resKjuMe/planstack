<?php

namespace App\Support;

use App\Enums\Criticality;
use App\Enums\ReviewRecommendation;
use App\Enums\TaskStatus;
use App\Models\Project;

/**
 * Gemeinsame Optionen + Labels für das Task-Formular (Anlegen/Bearbeiten) als
 * Inertia-Props. Pendant zum früheren tasks/partials/form.blade.php.
 */
class TaskFormPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function shared(Project $project): array
    {
        return [
            'statuses' => collect(TaskStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'criticalities' => collect(Criticality::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
            'recommendations' => collect(ReviewRecommendation::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            'phases' => $project->phases->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'reviewers' => $project->accessUsers()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'strings' => [
                'name' => __('tasks.short_code_e_g_c23'),
                'status' => __('common.status'),
                'summary' => __('common.summary_2'),
                'criticality' => __('tasks.criticality'),
                'description' => __('common.description'),
                'acceptanceCriteria' => __('common.acceptance_criteria'),
                'targetActual' => __('tasks.actual_target_comparison'),
                'targetActualPlaceholder' => __('tasks.actual_behavior_before_the_task_target'),
                'targetActualHint' => __('tasks.an_easy_to_understand_before_after'),
                'testCases' => __('tasks.test_cases_test_instructions'),
                'testCasesPlaceholder' => __('tasks.step_by_step_instructions_for_how_the'),
                'testCasesHint' => __('tasks.for_humans_how_can_the_result_of_the_pr'),
                'phase' => __('tasks.phase'),
                'manDays' => __('tasks.person_days'),
                'storyPoints' => __('common.story_points'),
                'tokens' => __('tasks.tokens_estimated'),
                'affectedFiles' => __('tasks.affected_files_estimated'),
                'affectedFilesHint' => __('tasks.always_provide_this_an_estimate_is'),
                'prNumber' => __('tasks.pr_number'),
                'reviewedBy' => __('tasks.reviewed_by'),
                'reviewResult' => __('tasks.review_result'),
                'recommendation' => __('tasks.recommendation'),
                'lastReviewedOn' => __('tasks.last_reviewed_on'),
                'reviewSummary' => __('tasks.review_analysis_tldr_first_then_detailed'),
                'reviewSummaryPlaceholder' => __('tasks.tldr_2')."\n\n".__('tasks.detailed_analysis'),
                'prerequisites' => __('tasks.prerequisites_requirements'),
                'cancel' => __('common.cancel'),
                'save' => __('common.save'),
            ],
        ];
    }
}
