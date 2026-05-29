<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MaintenanceTaskService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('posts:publish-scheduled')]
#[Description('Publish due scheduled posts.')]
class PublishScheduledPostsCommand extends Command
{
    public function handle(MaintenanceTaskService $tasks): int
    {
        $lock = Cache::lock('posts:publish-scheduled', 300);

        if (! $lock->get()) {
            Log::info('Scheduled post publication skipped because the lock is already held.');
            $this->components->info('Scheduled post publication is already running.');

            return self::SUCCESS;
        }

        Log::info('Scheduled post publication lock acquired.');

        try {
            $result = $tasks->publishScheduledPosts();
            $this->components->info($result->message);

            return self::SUCCESS;
        } finally {
            $lock->release();
            Log::info('Scheduled post publication lock released.');
        }
    }
}
