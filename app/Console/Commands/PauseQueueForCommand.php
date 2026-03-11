<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Factory as QueueManager;
use Illuminate\Queue\Console\Concerns\ParsesQueue;

class PauseQueueForCommand extends Command
{
    use ParsesQueue;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:pause-for
                            {queue : The name of the queue to pause (connection:queue)}
                            {--seconds=60 : Number of seconds to pause the queue for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pause job processing for a specific queue for a limited time';

    /**
     * Execute the console command.
     */
    public function handle(QueueManager $manager): int
    {
        $seconds = (int) $this->option('seconds');

        if ($seconds < 1) {
            $this->components->error('The [--seconds] option must be at least 1.');

            return self::FAILURE;
        }

        [$connection, $queue] = $this->parseQueue((string) $this->argument('queue'));

        $manager->pauseFor($connection, $queue, $seconds);

        $this->components->info(
            "Job processing on queue [{$connection}:{$queue}] has been paused for {$seconds} seconds."
        );

        return self::SUCCESS;
    }
}
