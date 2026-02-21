<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! (bool) $this->user()->is_banned;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'visibility' => $this->input('visibility', Post::VISIBILITY_PUBLIC),
            'pet_id' => $this->filled('pet_id') ? $this->input('pet_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000', 'required_without_all:photos,video'],
            'pet_id' => ['nullable', Rule::exists('pets', 'id')->where('user_id', $this->user()->id)],
            'visibility' => ['required', Rule::in([Post::VISIBILITY_PUBLIC, Post::VISIBILITY_FOLLOWERS, Post::VISIBILITY_PRIVATE])],
            'location' => ['nullable', 'string', 'max:100'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'video' => [
                'nullable',
                'file',
                'mimes:mp4,mov,webm',
                'max:51200',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $photos = $this->file('photos', []);
                    if ($value !== null && is_array($photos) && count($photos) > 0) {
                        $fail('You cannot upload both photos and a video.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without_all' => 'Please write something or add a photo or video.',
            'photos.max' => 'You can upload up to 5 photos.',
            'video.max' => 'Video must be under 50MB.',
            'pet_id.exists' => 'That pet does not belong to you.',
        ];
    }
}
