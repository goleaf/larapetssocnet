<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['nullable', 'string', 'in:public,followers,private'],
            'location' => ['nullable', 'string', 'max:100'],
            'pet_id' => ['nullable', 'exists:pets,id'],
            'tagged_pets' => ['nullable', 'array'],
            'tagged_pets.*' => ['integer', 'exists:pets,id'],
        ];
    }
}
