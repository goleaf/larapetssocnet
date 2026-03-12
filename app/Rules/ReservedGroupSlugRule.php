<?php

namespace App\Rules;

use App\Services\GroupSlugService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ReservedGroupSlugRule implements ValidationRule
{
    public function __construct(private readonly GroupSlugService $slugs) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = $this->slugs->normalize((string) $value);

        if ($this->slugs->isReserved($slug)) {
            $fail('This slug is reserved. Please choose another.');
        }
    }
}
