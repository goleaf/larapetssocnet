<?php

namespace App\Http\Requests\Pets;

use App\Enums\Pets\PetOwnerRole;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetOwnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        if (! $pet instanceof Pet) {
            return false;
        }

        return $this->user()?->can('manageOwners', $pet) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pet = $this->route('pet');
        $ownerId = $pet instanceof Pet ? (int) $pet->user_id : 0;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([$ownerId]),
            ],
            'role' => ['nullable', 'string', Rule::in([
                PetOwnerRole::Admin->value,
                PetOwnerRole::Poster->value,
                PetOwnerRole::Viewer->value,
            ])],
            'can_post' => ['nullable', 'boolean'],
            'can_edit' => ['nullable', 'boolean'],
            'can_manage_health' => ['nullable', 'boolean'],
            'can_manage_gallery' => ['nullable', 'boolean'],
            'can_manage_adoption' => ['nullable', 'boolean'],
            'can_delete' => ['nullable', 'boolean'],
        ];
    }
}
