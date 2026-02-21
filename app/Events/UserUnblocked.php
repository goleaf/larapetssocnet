<?php

namespace App\Events;

use App\Models\User;

class UserUnblocked
{
    public function __construct(
        public readonly User $actor,
        public readonly User $target,
    ) {}
}
