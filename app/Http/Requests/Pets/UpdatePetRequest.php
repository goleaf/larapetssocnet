<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;

class UpdatePetRequest extends CreatePetRequest
{
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        if (! $pet instanceof Pet) {
            return false;
        }

        return $this->user()?->can('update', $pet) ?? false;
    }
}
