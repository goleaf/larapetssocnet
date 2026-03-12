<?php

namespace App\Http\Requests;

use App\Models\Report;
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
