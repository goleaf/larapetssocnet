<?php

namespace App\Events;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserFollowed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Follow $follow,
        public readonly User $follower,
        public readonly User $target,
    ) {}
}
