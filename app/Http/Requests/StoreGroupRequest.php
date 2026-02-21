<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['required', Rule::in(['public', 'private', 'secret'])],
            'location' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:200'],
        ];
    }
}
