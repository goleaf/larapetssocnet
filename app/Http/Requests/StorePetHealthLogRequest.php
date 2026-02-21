<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePetHealthLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['weight', 'medication', 'vaccination', 'vaccine', 'vet_visit'])],
            'title' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'logged_at' => ['required', 'date'],
            'next_due_at' => ['nullable', 'date', 'after_or_equal:logged_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Type must be one of: weight, medication, vaccination, vet visit.',
        ];
    }
}
