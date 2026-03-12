<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('post'));
    }

    public function rules(): array
    {
        return [
            'published_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
