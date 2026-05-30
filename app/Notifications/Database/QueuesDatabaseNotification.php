<?php

declare(strict_types=1);

namespace App\Notifications\Database;

use App\Enums\Support\Queue\QueueName;
use App\Support\Queue\HasDefaultQueueRuntime;
use Illuminate\Bus\Queueable;

trait QueuesDatabaseNotification
{
    use HasDefaultQueueRuntime;
    use Queueable;

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'database' => QueueName::Notifications->routingName(),
        ];
    }
}
