<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'pet_id' => [
                'nullable',
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
            'tagged_pets' => ['nullable', 'array'],
            'tagged_pets.*' => [
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
        ];
    }
}
