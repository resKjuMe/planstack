<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only the team creator may add members.
        return $this->user()->can('manageMembers', $this->route('team'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Mitglieder werden aus der Liste der Organisations-User gewählt.
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
