<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'pet_id' => ['nullable', 'integer', 'exists:pets,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'listing_type' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'location_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
