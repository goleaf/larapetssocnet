<?php

namespace App\Rules;

use App\Models\ReservedUsername;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotReservedUsername implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (ReservedUsername::isReserved((string) $value)) {
            $fail('This username is reserved and cannot be used.');
        }
    }
}

