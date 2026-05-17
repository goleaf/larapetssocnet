<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class PasswordStrength implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;
        $normalized = Str::lower($password);
        $commonPasswords = array_map(
            static fn (string $commonPassword): string => Str::lower($commonPassword),
            config('common_passwords.passwords', [])
        );

        if (in_array($normalized, $commonPasswords, true)) {
            $fail('This password is too common. Choose a more unique password.');

            return;
        }

        $score = 0;
        $score += strlen($password) >= 10 ? 1 : 0;
        $score += preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password) ? 1 : 0;
        $score += preg_match('/\d/', $password) ? 1 : 0;
        $score += preg_match('/[^A-Za-z0-9]/', $password) ? 1 : 0;

        if ($score < 2) {
            $fail('Use a stronger password with at least two of: 10+ characters, mixed case, numbers, or symbols.');
        }
    }
}
