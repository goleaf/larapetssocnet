<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetOwnershipTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        return $pet instanceof Pet
            && ($this->user()?->can('transferOwnership', $pet) ?? false);
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
        ];
    }
}
