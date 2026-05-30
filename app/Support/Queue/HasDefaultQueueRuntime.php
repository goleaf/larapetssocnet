<?php

declare(strict_types=1);

namespace App\Support\Queue;

use Illuminate\Support\Facades\Log;
use Throwable;

trait HasDefaultQueueRuntime
{
    public int $tries = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Queued job failed after all retry attempts.', $this->queueFailureContext($exception));
    }

    /**
     * @return array<string, mixed>
     */
    protected function queueFailureContext(?Throwable $exception): array
    {
        return [
            'job' => static::class,
            'connection' => $this->queueFailureProperty('connection'),
            'queue' => $this->queueFailureProperty('queue'),
            'unique_id' => method_exists($this, 'uniqueId') ? $this->uniqueId() : null,
            'tries' => $this->tries,
            'timeout' => $this->timeout,
            'fail_on_timeout' => $this->failOnTimeout,
            'exception_class' => $exception instanceof Throwable ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
        ];
    }

    private function queueFailureProperty(string $property): mixed
    {
        return property_exists($this, $property) ? $this->{$property} : null;
    }
}
