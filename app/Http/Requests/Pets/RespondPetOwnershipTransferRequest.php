<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnershipTransfer;
use Illuminate\Foundation\Http\FormRequest;

class RespondPetOwnershipTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pet = $this->route('pet');
        $transfer = $this->route('transfer');

        return $pet instanceof Pet
            && $transfer instanceof PetOwnershipTransfer
            && (int) $transfer->pet_id === (int) $pet->getKey()
            && (int) $transfer->proposed_owner_user_id === (int) ($this->user()?->getKey() ?? 0);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
