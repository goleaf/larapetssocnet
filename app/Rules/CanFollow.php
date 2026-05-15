<?php

namespace App\Rules;

use App\Models\Identity\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CanFollow implements ValidationRule
{
    public function __construct(private readonly User $actor) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $target = User::query()->find($value);

        if (! $target || $this->actor->is($target)) {
            $fail('You cannot follow this user.');

            return;
        }

        if ($this->actor->hasBlocked($target) || $target->hasBlocked($this->actor)) {
            $fail('Unable to perform this action.');
        }
    }
}
