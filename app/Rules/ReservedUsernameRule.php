<?php

namespace App\Rules;

use App\Support\Usernames\UsernameRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ReservedUsernameRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (UsernameRules::isReserved((string) $value)) {
            $fail('This username is reserved and cannot be used.');
        }
    }
}
