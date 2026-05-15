<?php

namespace App\Http\Requests\Posts;

use App\Models\Content\Comment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $comment = $this->route('comment');

        if (! $user || ! $comment instanceof Comment) {
            return false;
        }

        return $user->can('update', $comment);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
        ]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Please write something before submitting.',
            'body.max' => 'Comments may not be longer than 1000 characters.',
        ];
    }
}
