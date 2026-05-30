<?php

namespace App\Support\Profiles;

use App\Models\Identity\User;

class ProfileUpdateResult
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function __construct(
        public readonly User $user,
        public readonly array $validated,
    ) {}
}
