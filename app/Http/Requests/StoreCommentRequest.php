<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Post;
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
                ->whereNull('parent_id')
                ->whereNull('deleted_at');
        }

        return [
            'body' => ['required', 'string', 'min:1', 'max:1000'],
            'parent_id' => ['nullable', 'integer', $parentRule],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Please write something before submitting.',
            'body.max' => 'Comments may not be longer than 1000 characters.',
            'parent_id.exists' => 'Reply target must be a top-level comment on this post.',
        ];
    }
}
