<?php

namespace App\Http\Requests\Posts;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $post = $this->route('post');

        if (! $user || ! $post instanceof Post) {
            return false;
        }

        return $user->can('create', [Comment::class, $post]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
        ]);
    }

    public function rules(): array
    {
        $post = $this->route('post');

        $parentRule = Rule::exists('comments', 'id');

        if ($post instanceof Post) {
            $parentRule = $parentRule
                ->where('post_id', $post->getKey())
                ->whereNull('deleted_at');
        }

        return [
            'body' => ['required', 'string', 'min:1', 'max:500'],
            'parent_id' => ['nullable', 'integer', $parentRule],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Please write something before submitting.',
            'body.max' => 'Comments may not be longer than 500 characters.',
            'parent_id.exists' => 'Reply target must be a visible comment on this post.',
        ];
    }
}
