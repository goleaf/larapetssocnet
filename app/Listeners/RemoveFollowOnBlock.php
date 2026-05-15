<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserBlocked;
use Illuminate\Support\Facades\Cache;

class RemoveFollowOnBlock
{
    public function handle(UserBlocked $event): void
    {
        Cache::forget('feed:'.$event->actor->getKey());
        Cache::forget('feed:'.$event->target->getKey());
    }
}
