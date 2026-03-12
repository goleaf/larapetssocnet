<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_visibility' => ['required', 'string', 'in:public,followers_only,private'],
            'messaging_permission' => ['required', 'string', 'in:everyone,followers_only'],
            'pets_visibility' => ['required', 'string', 'in:everyone,followers_only'],
            'groups_visibility' => ['required', 'string', 'in:everyone,followers_only'],
            'show_in_explore' => ['boolean'],
            'open_following' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_visibility.in' => 'Select a valid profile visibility setting.',
            'messaging_permission.in' => 'Select a valid messaging preference.',
            'pets_visibility.in' => 'Select a valid pets visibility setting.',
            'groups_visibility.in' => 'Select a valid groups visibility setting.',
        ];
    }
}
