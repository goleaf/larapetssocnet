<?php

namespace App\Http\Requests\Groups;

use App\Models\Groups\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            && ($this->user()?->can('invite', $group) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $message = trim((string) $this->input('message', ''));

        $this->merge([
            'message' => $message === '' ? null : $message,
        ]);
    }
}
