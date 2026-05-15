<?php

declare(strict_types=1);

namespace App\Http\Requests\Pets;

use Illuminate\Foundation\Http\FormRequest;

class StorePetCareTipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'species' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'content' => ['required', 'string', 'max:10000'],
        ];
    }
}
