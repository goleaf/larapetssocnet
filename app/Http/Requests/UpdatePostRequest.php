<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'string', 'in:'.Post::visibilityOptions()->implode(',')],
            'location' => ['nullable', 'string', 'max:255'],
            'tagged_pets' => ['nullable', 'array', 'max:10'],
            'tagged_pets.*' => ['integer', 'exists:pets,id'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200'],
            'remove_photos' => ['nullable', 'boolean'],
            'remove_video' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('photos') && $this->hasFile('video')) {
                $validator->errors()->add('photos', 'Choose photos or a single video, not both.');
            }
        });
    }
}
