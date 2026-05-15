<?php

declare(strict_types=1);

namespace App\Http\Requests\Moderation;

use Illuminate\Foundation\Http\FormRequest;

class StorePostReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
