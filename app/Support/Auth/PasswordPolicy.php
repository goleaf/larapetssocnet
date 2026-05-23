<?php

namespace App\Support\Auth;

use App\Rules\PasswordStrength;
use Illuminate\Contracts\Validation\Rule as LegacyValidationRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public const int MIN_LENGTH = 8;

    public const int MAX_LENGTH = 128;

    public static function rule(): Password
    {
        return Password::min(self::MIN_LENGTH)->max(self::MAX_LENGTH);
    }

    public static function htmlRules(): string
    {
        $rule = Password::defaults();

        return $rule instanceof Password
            ? $rule->toPasswordRulesString()
            : self::rule()->toPasswordRulesString();
    }

    /**
     * @return list<string|LegacyValidationRule|ValidationRule>
     */
    public static function validationRules(): array
    {
        $rule = Password::defaults();

        return [
            'required',
            'confirmed',
            'not_regex:/^\s+$/',
            $rule instanceof Password ? $rule : self::rule(),
            new PasswordStrength,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'password.not_regex' => 'Password cannot be only spaces.',
        ];
    }
}
