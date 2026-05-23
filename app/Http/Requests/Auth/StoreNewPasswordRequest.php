<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNewPasswordRequest extends FormRequest
{
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
            'token' => ['required'],
            'email' => ['required', 'email'],
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
