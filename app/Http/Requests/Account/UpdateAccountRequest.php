<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_private' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ];
    }
}
