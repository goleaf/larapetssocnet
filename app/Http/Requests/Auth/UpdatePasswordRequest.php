<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\Auth\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    protected $errorBag = 'updatePassword';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => PasswordPolicy::validationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PasswordPolicy::validationMessages();
    }
}
