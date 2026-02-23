<?php

namespace App\Http\Requests;

use App\Models\Reaction;
use Illuminate\Foundation\Http\FormRequest;

class ReactToCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', Reaction::TYPES)],
        ];
    }
}
