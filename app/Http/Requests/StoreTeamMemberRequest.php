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
        // Adding to a team happens strictly by e-mail; the address must belong
        // to a registered user (open registration).
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }
}
