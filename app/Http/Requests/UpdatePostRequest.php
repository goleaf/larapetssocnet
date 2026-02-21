<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post && $this->user()->can('update', $post);
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in([Post::VISIBILITY_PUBLIC, Post::VISIBILITY_FOLLOWERS, Post::VISIBILITY_PRIVATE])],
            'location' => ['nullable', 'string', 'max:100'],
            'pet_id' => ['nullable', Rule::exists('pets', 'id')->where('user_id', $this->user()->id)],
            'tagged_pets' => ['nullable', 'array', 'max:10'],
            'tagged_pets.*' => ['integer', Rule::exists('pets', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $taggedPets = $this->input('tagged_pets', []);
        if (! is_array($taggedPets)) {
            $taggedPets = [];
        }

        $this->merge([
            'tagged_pets' => collect($taggedPets)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);
    }
}
