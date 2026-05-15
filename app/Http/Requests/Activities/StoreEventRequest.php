<?php

declare(strict_types=1);

namespace App\Http\Requests\Activities;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
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
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_attendees' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
