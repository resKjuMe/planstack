<?php

namespace App\Http\Requests;

use App\Enums\ReviewRecommendation;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Task $task */
        $task = $this->route('task');
        $projectId = $task->project_id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_acceptance_criteria' => ['nullable', 'string'],
            'description_target_actual' => ['nullable', 'string'],
            'description_test_cases' => ['nullable', 'string'],
            'criticality' => ['nullable', Rule::enum(\App\Enums\Criticality::class)],
            'phase_id' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $projectId)],
            'effort_man_days' => ['nullable', 'numeric', 'min:0'],
            'effort_story_points' => ['nullable', 'integer', 'min:0'],
            'effort_tokens' => ['nullable', 'integer', 'min:0'],
            'affected_files' => ['nullable', 'integer', 'min:0'],
            'pr_number' => ['nullable', 'integer', 'min:1'],
            'claimed_by_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'reviewed_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'last_reviewed_at' => ['nullable', 'date'],
            'last_review_recommendation' => ['nullable', Rule::enum(ReviewRecommendation::class)],
            'last_review_summary' => ['nullable', 'string'],
            // Status = KEY eines Status DIESER Organisation (nicht das Alt-Enum
            // TaskStatus): nur so sind REVIEWBAR/APPROVED und eigene Status
            // setzbar. `status_id` ist die Autorität, das Modell löst den Key auf.
            'status' => [
                'required', 'string',
                Rule::exists('task_statuses', 'key')
                    ->where('organization_id', $task->project->organization_id),
            ],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => [
                'different:'.$task->id,
                Rule::exists('tasks', 'id')->where('project_id', $projectId),
            ],
        ];
    }
}
