<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnerInvitation;
use Illuminate\Foundation\Http\FormRequest;

class RespondPetOwnerInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pet = $this->route('pet');
        $invitation = $this->route('invitation');

        return $pet instanceof Pet
            && $invitation instanceof PetOwnerInvitation
            && (int) $invitation->pet_id === (int) $pet->getKey()
            && (int) $invitation->invited_user_id === (int) ($this->user()?->getKey() ?? 0);
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
