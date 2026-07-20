<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alias' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('projects', 'alias')->ignore($this->route('project')),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'github_repo' => ['nullable', 'string', 'max:255', 'regex:#^[\w.-]+/[\w.-]+$#'],
            'archived' => ['boolean'],
            // skill_description wird jetzt auf der „Claude"-Unterseite gepflegt.
        ];
    }
}
