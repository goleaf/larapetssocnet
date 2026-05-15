<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use Illuminate\Foundation\Http\FormRequest;

class DeletePetGalleryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        if (! $pet instanceof Pet) {
            return false;
        }

        return $this->user()?->can('manageGallery', $pet) ?? false;
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}
