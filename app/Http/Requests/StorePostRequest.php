<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'pet_id' => ['nullable', 'exists:pets,id'],
            'visibility' => ['nullable', 'string', 'in:public,followers,private'],
            'location' => ['nullable', 'string', 'max:100'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
        ];
    }
}
