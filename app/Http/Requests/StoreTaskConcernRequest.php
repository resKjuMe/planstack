<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskConcernRequest extends FormRequest
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
        return [
            'summary' => ['required', 'string', 'max:255'],
            'description_context' => ['nullable', 'string'],
            'description_blocker' => ['nullable', 'string'],
            'description_misconception' => ['nullable', 'string'],
            'description_decisions' => ['nullable', 'string'],
        ];
    }
}
