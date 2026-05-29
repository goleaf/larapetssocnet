<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:32', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:64', 'required_without:code'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_without' => 'Enter an authentication code or a recovery code.',
            'recovery_code.required_without' => 'Enter an authentication code or a recovery code.',
        ];
    }
}
