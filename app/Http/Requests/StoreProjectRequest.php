<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Project::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:projects,alias'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'github_repo' => ['nullable', 'string', 'max:255', 'regex:#^[\w.-]+/[\w.-]+$#'],
            'skill_description' => ['nullable', 'string'],
        ];
    }
}
