<?php

declare(strict_types=1);

namespace App\Http\Requests\Moderation;

use App\Models\Moderation\Report;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'in:'.implode(',', Report::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
