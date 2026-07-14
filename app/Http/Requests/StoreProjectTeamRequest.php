<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A team can only be assigned by someone who belongs to it.
        return [
            'team_id' => [
                'required', 'integer',
                Rule::exists('users_to_teams', 'team_id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.exists' => 'Du kannst nur Teams zuweisen, in denen du Mitglied bist.',
        ];
    }
}
