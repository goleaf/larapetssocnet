<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'prize' => ['nullable', 'string', 'max:255'],
            'species' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_entries' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:40'],
        ];
    }
}
