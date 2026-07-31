<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:50'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_acceptance_criteria' => ['nullable', 'string'],
            'description_target_actual' => ['nullable', 'string'],
            'description_test_cases' => ['nullable', 'string'],
            'criticality' => ['nullable', Rule::enum(\App\Enums\Criticality::class)],
            'phase_id' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $project->id)],
            'effort_man_days' => ['nullable', 'numeric', 'min:0'],
            'effort_story_points' => ['nullable', 'integer', 'min:0'],
            'effort_tokens' => ['nullable', 'integer', 'min:0'],
            'affected_files' => ['nullable', 'integer', 'min:0'],
            'pr_number' => ['nullable', 'integer', 'min:1'],
            'claimed_by_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'reviewed_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
            // Status = KEY eines Status DIESER Organisation (nicht das Alt-Enum
            // TaskStatus): nur so sind REVIEWBAR/APPROVED und eigene Status
            // setzbar. `status_id` ist die Autorität, das Modell löst den Key auf.
            'status' => [
                'nullable', 'string',
                Rule::exists('task_statuses', 'key')->where('organization_id', $project->organization_id),
            ],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => [Rule::exists('tasks', 'id')->where('project_id', $project->id)],
        ];
    }
}
