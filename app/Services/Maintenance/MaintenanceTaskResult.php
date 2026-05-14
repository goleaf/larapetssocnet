<?php

namespace App\Services\Maintenance;

final readonly class MaintenanceTaskResult
{
    /**
     * @param  array<string, int|string|bool|null>  $metrics
     */
    public function __construct(
        public string $task,
        public string $message,
        public array $metrics = [],
    ) {}

    /**
     * @param  array<string, int|string|bool|null>  $metrics
     */
    public static function make(string $task, string $message, array $metrics = []): self
    {
        return new self($task, $message, $metrics);
    }

    /**
     * @return array{task: string, message: string, metrics: array<string, int|string|bool|null>}
     */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'message' => $this->message,
            'metrics' => $this->metrics,
        ];
    }
}
