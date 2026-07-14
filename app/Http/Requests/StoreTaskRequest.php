<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
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
            'phase_id' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $project->id)],
            'effort_man_days' => ['nullable', 'numeric', 'min:0'],
            'effort_story_points' => ['nullable', 'integer', 'min:0'],
            'effort_tokens' => ['nullable', 'integer', 'min:0'],
            'affected_files' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => [Rule::exists('tasks', 'id')->where('project_id', $project->id)],
        ];
    }
}
