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
        ];
    }
}
