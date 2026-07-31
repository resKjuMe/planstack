<?php

namespace App\Support;

use App\Enums\Criticality;
use App\Enums\ReviewRecommendation;
use App\Enums\StatusRole;
use App\Models\OrgStatus;
use App\Models\Project;
use App\Models\Task;

/**
 * Gemeinsame Optionen + Labels für das Task-Formular (Anlegen/Bearbeiten) als
 * Inertia-Props. Pendant zum früheren tasks/partials/form.blade.php.
 */
class TaskFormPresenter
{
    /**
     * Options + Labels des Formulars. `$task` ist beim Bearbeiten gesetzt: die
     * Personen-Liste nimmt dann auch die aktuell eingetragenen Personen auf, selbst
     * wenn sie den Projektzugang inzwischen verloren haben — sonst zeigte das
     * Formular ein leeres Feld und würde den Eintrag beim Speichern stillschweigend
     * verwerfen.
     *
     * @return array<string, mixed>
     */
    public function shared(Project $project, ?Task $task = null): array
    {
        $statuses = $project->organization->statuses()->get();

        return [
            // Die Status-Auswahl kommt aus der ORG-Konfiguration in Board-Reihenfolge,
            // nicht aus dem Alt-Enum App\Enums\TaskStatus: sonst fehlen genau die
            // Status ohne kanonisches Enum-Gegenstück (REVIEWBAR, APPROVED) sowie
            // alle eigenen Status der Organisation — und umbenannte Status stünden
            // unter ihrem alten Namen. `status_id` ist die Autorität, der Wert ist
            // deshalb der Status-KEY.
            'statuses' => $statuses
                ->map(fn (OrgStatus $s) => ['value' => $s->key, 'label' => $s->localizedLabel()])
                ->values(),
            // Vorbelegung beim Anlegen: der pickbare Status (Rolle PICKABLE) — das
            // Formular kann sie nicht raten, seit es kein UNKNOWN mehr gibt.
            'defaultStatus' => ($statuses->first(fn (OrgStatus $s) => $s->role === StatusRole::PICKABLE)
                ?? $statuses->first())?->key ?? '',
            'criticalities' => collect(Criticality::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
            'recommendations' => collect(ReviewRecommendation::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            'phases' => $project->phases->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            // EINE Personen-Liste für beide Zuordnungen (Claim und Review): wer
            // Zugang zum Projekt hat, kann beides sein.
            'members' => $this->members($project, $task),
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
                'claimedBy' => __('tasks.claimed_by'),
                'claimedByHint' => __('tasks.claimed_by_hint'),
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

    /**
     * Projektzugang + die am Task eingetragenen Personen (siehe {@see shared()}),
     * nach Namen sortiert.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function members(Project $project, ?Task $task): array
    {
        $users = $project->accessUsers();

        foreach ([$task?->claimer, $task?->reviewer] as $attached) {
            if ($attached !== null && ! $users->contains('id', $attached->id)) {
                $users->push($attached);
            }
        }

        return $users
            ->sortBy('name')
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /**
     * Vorbelegte Formularwerte eines bestehenden Tasks (Bearbeiten). Leere Strings
     * statt null, weil die Felder im Formular kontrollierte Eingaben sind.
     *
     * @return array<string, mixed>
     */
    public function values(Task $task): array
    {
        return [
            'name' => $task->name,
            // Der KEY des Org-Status, nicht das Alt-Enum: ein Task in einem Status
            // ohne Enum-Gegenstück (REVIEWBAR, APPROVED, eigene Status) hat
            // `status === null` und stand hier vorher als „UNKNOWN" — Speichern
            // hätte ihn stillschweigend auf pickbar zurückgesetzt.
            'status' => $task->orgStatus?->key ?? '',
            'summary' => $task->summary ?? '',
            'criticality' => $task->criticality?->value ?? '',
            'description' => $task->description ?? '',
            'description_acceptance_criteria' => $task->description_acceptance_criteria ?? '',
            'description_target_actual' => $task->description_target_actual ?? '',
            'description_test_cases' => $task->description_test_cases ?? '',
            'phase_id' => $task->phase_id ?? '',
            'effort_man_days' => $task->effort_man_days ?? '',
            'effort_story_points' => $task->effort_story_points ?? '',
            'effort_tokens' => $task->effort_tokens ?? '',
            'affected_files' => $task->affected_files ?? '',
            'pr_number' => $task->pr_number ?? '',
            'claimed_by_id' => $task->claimed_by_id ?? '',
            'reviewed_by' => $task->reviewed_by ?? '',
            'last_review_recommendation' => $task->last_review_recommendation?->value ?? '',
            'last_reviewed_at' => $task->last_reviewed_at?->format('Y-m-d\TH:i') ?? '',
            'last_review_summary' => $task->last_review_summary ?? '',
            'prerequisites' => $task->prerequisites()->pluck('tasks.id')->all(),
        ];
    }
}
