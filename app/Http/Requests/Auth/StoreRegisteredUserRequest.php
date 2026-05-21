<?php

namespace App\Http\Requests\Auth;

use App\Models\Identity\User;
use App\Rules\PasswordStrength;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreRegisteredUserRequest extends FormRequest
{
    private const int MAX_REGISTRATION_ATTEMPTS = 5;

    private const int REGISTRATION_DECAY_SECONDS = 3600;

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
            'name' => Str::squish((string) $this->input('name')),
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
            'name' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\pL\pM\pN\s\'.-]+$/u'],
            'username' => UsernameRules::requiredRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'max:128', 'not_regex:/^\s+$/', 'confirmed', Password::defaults(), new PasswordStrength],
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
            'name.regex' => 'Use only letters, numbers, spaces, apostrophes, periods, and hyphens for your display name.',
            'password.not_regex' => 'Password cannot be only spaces.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $password = Str::lower((string) $this->input('password'));
                $identityValues = [
                    'email' => Str::lower((string) $this->input('email')),
                    'username' => Str::lower((string) $this->input('username')),
                    'display name' => Str::lower((string) $this->input('name')),
                ];

                foreach ($identityValues as $label => $identityValue) {
                    if ($identityValue === '') {
                        continue;
                    }

                    if ($password === $identityValue) {
                        $validator->errors()->add('password', "Password cannot be the same as your {$label}.");

                        return;
                    }
                }
            },
        ];
    }

    public function trippedHoneypot(): bool
    {
        return trim((string) $this->input('company_name')) !== '';
    }

    public function trippedTimingTrap(): bool
    {
        $startedAt = $this->session()->get('registration_form_started_at');

        if (! is_numeric($startedAt)) {
            return false;
        }

        return now()->timestamp - (int) $startedAt < 2;
    }

    public function hasSuspiciousUserAgent(): bool
    {
        $userAgent = Str::lower((string) $this->userAgent());

        if ($userAgent === '') {
            return false;
        }

        return Str::contains($userAgent, [
            'curl',
            'python-requests',
            'scrapy',
            'wget',
            'masscan',
            'nikto',
        ]);
    }

    public function trippedBotProtection(): bool
    {
        return $this->trippedHoneypot()
            || $this->trippedTimingTrap()
            || $this->hasSuspiciousUserAgent();
    }

    public function ensureRegistrationIsNotRateLimited(): void
    {
        foreach ($this->registrationThrottleKeys() as $key) {
            if (! RateLimiter::tooManyAttempts($key, self::MAX_REGISTRATION_ATTEMPTS)) {
                continue;
            }

            throw ValidationException::withMessages([
                'email' => 'Too many registration attempts. Please try again later.',
            ])->status(429);
        }
    }

    public function hitRegistrationRateLimiter(): void
    {
        foreach ($this->registrationThrottleKeys() as $key) {
            RateLimiter::hit($key, self::REGISTRATION_DECAY_SECONDS);
        }
    }

    /**
     * @return list<string>
     */
    public function registrationThrottleKeys(): array
    {
        return collect([
            'registration:ip:'.sha1((string) $this->ip()),
            $this->filled('email') ? 'registration:email:'.sha1((string) $this->input('email')) : null,
            $this->filled('username') ? 'registration:username:'.sha1((string) $this->input('username')) : null,
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
