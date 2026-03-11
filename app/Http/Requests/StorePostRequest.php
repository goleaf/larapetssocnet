<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'pet_id' => [
                'nullable',
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
            'tagged_pets' => ['nullable', 'array'],
            'tagged_pets.*' => [
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
            'visibility' => ['nullable', 'string', 'in:public,followers,private'],
            'location' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'])->max('20mb'),
            ],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => [
                'file',
                File::image()->max('20mb'),
            ],
            'video' => [
                'nullable',
                'file',
                File::types(['mp4', 'mov'])->max('20mb'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mediaFiles = collect($this->mediaFiles());

            if ($mediaFiles->isEmpty()) {
                return;
            }

            $videoFiles = $mediaFiles->filter(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'video/')
            );

            $imageFiles = $mediaFiles->filter(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'image/')
            );

            $errorKey = ($this->hasFile('photos') || $this->hasFile('video')) ? 'video' : 'media';

            if ($videoFiles->hasMany()) {
                $validator->errors()->add($errorKey, 'Only one video can be uploaded.');
            }

            if ($videoFiles->isNotEmpty() && $imageFiles->isNotEmpty()) {
                $validator->errors()->add($errorKey, 'Video cannot be uploaded together with photos.');
            }
        });
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function mediaFiles(): array
    {
        $mediaFiles = collect($this->file('media', []))
            ->merge($this->file('photos', []));

        $videoFile = $this->file('video');

        if ($videoFile instanceof UploadedFile) {
            $mediaFiles->push($videoFile);
        }

        return $mediaFiles
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->values()
            ->all();
    }
}
