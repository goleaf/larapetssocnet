<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly User $sender,
        public readonly User $receiver,
    ) {}
}
