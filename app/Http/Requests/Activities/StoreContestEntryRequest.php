<?php

declare(strict_types=1);

namespace App\Http\Requests\Activities;

use Illuminate\Foundation\Http\FormRequest;

class StoreContestEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
