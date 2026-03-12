<?php

namespace App\Http\Requests;

use App\Models\Pet;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePetGalleryPhotoMetaRequest extends FormRequest
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
        $captionMax = (int) config('pets.gallery.caption_max', 200);
        $altMax = (int) config('pets.gallery.alt_text_max', 140);

        return [
            'caption' => ['nullable', 'string', 'max:'.$captionMax],
            'alt_text' => ['nullable', 'string', 'max:'.$altMax],
        ];
    }

    public function messages(): array
    {
        return [
            'caption.max' => __('pets.validation.gallery_caption_max'),
            'alt_text.max' => __('pets.validation.gallery_alt_max'),
        ];
    }
}
