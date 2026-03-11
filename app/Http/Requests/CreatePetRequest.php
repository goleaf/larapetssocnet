<?php

namespace App\Http\Requests;

use App\Models\Pet;

class CreatePetRequest extends StorePetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pet::class) ?? false;
    }
}
