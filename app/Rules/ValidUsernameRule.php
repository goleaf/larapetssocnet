<?php

namespace App\Rules;

use App\Support\Usernames\UsernameRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidUsernameRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $username = (string) $value;

        if ($username === '') {
            return;
        }

        if (UsernameRules::disallowNumericOnly() && preg_match('/^[0-9]+$/', $username)) {
            $fail('Username cannot be numbers only.');
        }
    }
}
