<?php

namespace App\Http\Requests\Auth;

use App\Models\Identity\User;
use App\Rules\PasswordStrength;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreRegisteredUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $birthDay = (int) $this->input('birth_day');
        $birthMonth = (int) $this->input('birth_month');
        $birthYear = (int) $this->input('birth_year');

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'username' => UsernameNormalizer::normalize((string) $this->input('username')),
            'birth_date' => checkdate($birthMonth, $birthDay, $birthYear)
                ? CarbonImmutable::create($birthYear, $birthMonth, $birthDay)->toDateString()
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentYear = now()->year;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => UsernameRules::optionalRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults(), new PasswordStrength],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:'.($currentYear - 100).','.$currentYear],
            'birth_date' => ['required', 'date', 'before_or_equal:'.now()->subYears(13)->toDateString()],
            'terms' => ['accepted'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.required' => 'Enter a valid date of birth.',
            'birth_date.before_or_equal' => 'You must be at least 13 years old to create an account.',
            'terms.accepted' => 'You must accept the terms and privacy policy to create an account.',
        ];
    }

    public function trippedHoneypot(): bool
    {
        return trim((string) $this->input('company_name')) !== '';
    }
}
