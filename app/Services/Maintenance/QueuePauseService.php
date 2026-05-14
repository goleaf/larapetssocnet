<?php

namespace App\Services\Maintenance;

use Illuminate\Contracts\Queue\Factory as QueueManager;
use Illuminate\Queue\Console\Concerns\ParsesQueue;

class QueuePauseService
{
    use ParsesQueue;

    public function __construct(private readonly QueueManager $queues) {}

    public function pause(string $queue): MaintenanceTaskResult
    {
        [$connection, $queueName] = $this->parseQueue($queue);

        $this->queues->pause($connection, $queueName);

        return MaintenanceTaskResult::make('pause-queue', "Paused {$connection}:{$queueName}.", [
            'connection' => $connection,
            'queue' => $queueName,
        ]);
    }

    public function pauseFor(string $queue, int $seconds): MaintenanceTaskResult
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Pause duration must be at least 1 second.');
        }

        [$connection, $queueName] = $this->parseQueue($queue);

        $this->queues->pauseFor($connection, $queueName, $seconds);

        return MaintenanceTaskResult::make('pause-queue-for', "Paused {$connection}:{$queueName} for {$seconds} seconds.", [
            'connection' => $connection,
            'queue' => $queueName,
            'seconds' => $seconds,
        ]);
    }

    public function resume(string $queue): MaintenanceTaskResult
    {
        [$connection, $queueName] = $this->parseQueue($queue);

        $this->queues->resume($connection, $queueName);

        return MaintenanceTaskResult::make('resume-queue', "Resumed {$connection}:{$queueName}.", [
            'connection' => $connection,
            'queue' => $queueName,
        ]);
    }
}
