<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'species' => ['required', 'string', 'max:80'],
            'breed' => ['nullable', 'string', 'max:120'],
            'sex' => ['nullable', 'in:male,female,unknown'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
