<?php

use App\Actions\Comments\FinalizeDeletedComment;
use App\Enums\Support\Queue\QueueName;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;

it('logs structured failed job context for queued application work', function (): void {
    $records = new ArrayObject;
    $logger = new class($records) extends AbstractLogger
    {
        /**
         * @param  ArrayObject<int, array{level: mixed, message: string, context: array<string, mixed>}>  $records
         */
        public function __construct(private readonly ArrayObject $records) {}

        /**
         * @param  array<string, mixed>  $context
         */
        public function log($level, Stringable|string $message, array $context = []): void
        {
            $this->records->append([
                'level' => $level,
                'message' => (string) $message,
                'context' => $context,
            ]);
        }
    };

    Log::swap($logger);

    $job = new FinalizeDeletedComment(commentId: 123);
    $exception = new RuntimeException('Counter cache update failed.');

    $job->failed($exception);

    /** @var array{level: string, message: string, context: array<string, mixed>} $record */
    $record = $records[0];

    expect($records)->toHaveCount(1)
        ->and($record['level'])->toBe('error')
        ->and($record['message'])->toBe('Queued job failed after all retry attempts.')
        ->and($record['context']['job'])->toBe(FinalizeDeletedComment::class)
        ->and($record['context']['queue'])->toBe(QueueName::Comments->value)
        ->and($record['context']['connection'])->toBeNull()
        ->and($record['context']['unique_id'])->toBe('123')
        ->and($record['context']['tries'])->toBe(3)
        ->and($record['context']['timeout'])->toBe(30)
        ->and($record['context']['fail_on_timeout'])->toBeTrue()
        ->and($record['context']['exception_class'])->toBe(RuntimeException::class)
        ->and($record['context']['exception_message'])->toBe('Counter cache update failed.');
});
