<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class PasswordStrength implements ValidationRule
{
    private const int MIN_FAIR_SCORE = 3;

    private const string SPECIAL_CHARACTERS_PATTERN = '/[!@#$%^&*()_\-+\=\[\]{};\':"\\\\|,.<>\/?`~]/';

    public static function score(string $password): int
    {
        if ($password === '') {
            return 0;
        }

        $score = 0;
        $length = strlen($password);

        if ($length >= 8) {
            $score++;
        }

        if ($length >= 12) {
            $score += 2;
        }

        if (preg_match('/[A-Z]/', $password) === 1) {
            $score++;
        }

        if (preg_match('/\d/', $password) === 1) {
            $score++;
        }

        if (preg_match(self::SPECIAL_CHARACTERS_PATTERN, $password) === 1) {
            $score++;
        }

        if (self::isCommon($password)) {
            $score -= 2;
        }

        return max(0, $score);
    }

    public static function isFairOrBetter(string $password): bool
    {
        return self::score($password) >= self::MIN_FAIR_SCORE;
    }

    public static function isCommon(string $password): bool
    {
        $normalized = Str::lower($password);
        $commonPasswords = array_map(
            static fn (string $commonPassword): string => Str::lower($commonPassword),
            config('common_passwords.passwords', [])
        );

        return in_array($normalized, $commonPasswords, true);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (self::isCommon($password)) {
            $fail('This password is too common. Choose a more unique password.');

            return;
        }

        if (! self::isFairOrBetter($password)) {
            $fail('Use a stronger password. Password strength must be at least Fair.');
        }
    }
}
