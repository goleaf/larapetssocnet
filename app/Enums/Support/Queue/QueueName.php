<?php

declare(strict_types=1);

namespace App\Enums\Support\Queue;

enum QueueName: string
{
    case Mail = 'mail';
    case Notifications = 'notifications';
    case Comments = 'comments';
    case Default = 'default';

    public function priority(): int
    {
        return match ($this) {
            self::Mail => 10,
            self::Notifications => 20,
            self::Comments => 30,
            self::Default => 100,
        };
    }

    public function purpose(): string
    {
        return match ($this) {
            self::Mail => 'Latency-sensitive auth, reset, magic-link, and security mailables.',
            self::Notifications => 'User-visible database and domain notifications.',
            self::Comments => 'Comment fan-out, mention delivery, and eventual comment counter maintenance.',
            self::Default => 'Framework, package, and fallback jobs that have not opted into an application queue.',
        };
    }

    /**
     * @return list<self>
     */
    public static function prioritized(): array
    {
        $queues = self::cases();

        usort($queues, fn (self $left, self $right): int => $left->priority() <=> $right->priority());

        return $queues;
    }

    public function routingName(?string $connection = null): string
    {
        $connection ??= (string) config('queue.default', 'database');

        if ($connection === 'redis') {
            return sprintf('{%s}', $this->value);
        }

        return $this->value;
    }

    /**
     * @return list<string>
     */
    public static function workerOrder(?string $connection = null): array
    {
        return array_map(
            fn (self $queue): string => $queue->routingName($connection),
            self::prioritized(),
        );
    }

    public static function workerQueueOption(?string $connection = null): string
    {
        return implode(',', self::workerOrder($connection));
    }

    public static function monitorQueueOption(string $connection = 'database'): string
    {
        return implode(
            ',',
            array_map(
                fn (string $queue): string => $connection.':'.$queue,
                self::workerOrder($connection),
            ),
        );
    }
}
