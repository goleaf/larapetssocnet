<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SchedulePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schedule', $this->route('post'));
    }

    public function rules(): array
    {
        return [
            'published_at' => ['required', 'date', 'after:now'],
        ];
    }
}
