<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
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
            'phase_id' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $projectId)],
            'effort_man_days' => ['nullable', 'numeric', 'min:0'],
            'effort_story_points' => ['nullable', 'integer', 'min:0'],
            'effort_tokens' => ['nullable', 'integer', 'min:0'],
            'affected_files' => ['nullable', 'integer', 'min:0'],
            'pr_number' => ['nullable', 'integer', 'min:1'],
            'reviewed_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => [
                'different:'.$task->id,
                Rule::exists('tasks', 'id')->where('project_id', $projectId),
            ],
        ];
    }
}
