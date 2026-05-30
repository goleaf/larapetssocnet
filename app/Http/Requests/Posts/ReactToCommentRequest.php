<?php

declare(strict_types=1);

namespace App\Http\Requests\Posts;

use App\Models\Content\Reaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Reaction::allowedCommentTypes())],
        ];
    }
}
