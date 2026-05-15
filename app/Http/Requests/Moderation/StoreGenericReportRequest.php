<?php

declare(strict_types=1);

namespace App\Http\Requests\Moderation;

use App\Models\Moderation\Report;
use Illuminate\Foundation\Http\FormRequest;

class StoreGenericReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reportable_type' => ['required', 'string', 'in:post,comment,user'],
            'reportable_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:'.implode(',', Report::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
