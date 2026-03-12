<?php

namespace App\Http\Requests;

use App\Models\Reaction;
use Illuminate\Foundation\Http\FormRequest;

class ReactToPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', Reaction::allowedTypes())],
        ];
    }
}
