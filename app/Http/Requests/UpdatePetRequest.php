<?php

namespace App\Http\Requests;

use App\Models\Pet;

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
