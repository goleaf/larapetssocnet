<?php

namespace App\Http\Requests\Pets;

use App\Models\Pets\Pet;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadPetGalleryPhotosRequest extends FormRequest
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
        $maxUpload = (int) config('pets.gallery.max_upload', 5);
        $maxFileSize = (int) config('pets.gallery.max_file_size_kb', 5120);
        $allowedMimes = config('pets.gallery.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        $mimeRule = 'mimes:'.implode(',', (array) $allowedMimes);

        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.$maxUpload],
            'photos.*' => ['image', $mimeRule, 'max:'.$maxFileSize],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pet = $this->route('pet');

            if (! $pet instanceof Pet) {
                return;
            }

            $existingCount = $pet->galleryMedia()->count();
            $incomingCount = count($this->file('photos', []));
            $maxTotal = (int) config('pets.gallery.max_photos', 30);

            if ($existingCount + $incomingCount > $maxTotal) {
                $validator->errors()->add('photos', __('pets.validation.gallery_max', ['max' => $maxTotal]));
            }
        });
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function photos(): array
    {
        return array_values($this->file('photos', []));
    }

    public function messages(): array
    {
        return [
            'photos.max' => __('pets.validation.gallery_upload_max'),
            'photos.*.image' => __('pets.validation.gallery_image'),
            'photos.*.mimes' => __('pets.validation.gallery_image'),
            'photos.*.max' => __('pets.validation.gallery_file_size'),
        ];
    }
}
