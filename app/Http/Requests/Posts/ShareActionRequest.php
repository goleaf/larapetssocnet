<?php

declare(strict_types=1);

namespace App\Http\Requests\Posts;

use Illuminate\Foundation\Http\FormRequest;

class ShareActionRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const METHODS = ['copy_link', 'native', 'external', 'internal'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'method' => ['nullable', 'string', 'in:'.implode(',', self::METHODS)],
        ];
    }
}
