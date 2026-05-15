<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;

class CreatePetRequest extends StorePetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pet::class) ?? false;
    }
}
