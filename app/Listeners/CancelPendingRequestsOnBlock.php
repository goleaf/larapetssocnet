<?php

namespace App\Listeners;

use App\Events\UserBlocked;
use Illuminate\Support\Facades\Schema;

class CancelPendingRequestsOnBlock
{
    public function handle(UserBlocked $event): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        activity()
            ->causedBy($event->actor)
            ->performedOn($event->target)
            ->log('blocked');
    }
}
