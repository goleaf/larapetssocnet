<?php

namespace App\Http\Requests;

use App\Models\Pet;
use Illuminate\Foundation\Http\FormRequest;

class ReorderPetGalleryPhotosRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<int, int|string>
     */
    public function orderedIds(): array
    {
        return (array) $this->input('order', []);
    }
}
