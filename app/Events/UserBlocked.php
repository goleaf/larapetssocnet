<?php

namespace App\Events;

use App\Models\User;

class UserBlocked
{
    public function __construct(
        public readonly User $actor,
        public readonly User $target,
    ) {}
}
