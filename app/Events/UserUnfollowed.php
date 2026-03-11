<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUnfollowed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $follower,
        public readonly User $target,
        public readonly bool $wasFollowing,
    ) {}
}
