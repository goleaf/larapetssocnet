<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledPostJob;
use App\Models\Content\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('posts:publish-scheduled')]
#[Description('Publish due scheduled posts.')]
class PublishScheduledPostsCommand extends Command
{
    public function handle(): int
    {
        $lock = Cache::store('database')->lock('posts:publish-scheduled-command', 70);

        if (! $lock->get()) {
            Log::info('Scheduled post publication skipped because the lock is already held.');
            $this->components->info('Scheduled post publication is already running.');

            return self::SUCCESS;
        }

        Log::info('Scheduled post publication lock acquired.');

        try {
            $postIds = Post::query()
                ->dueForPublication()
                ->orderBy('posts.scheduled_publish_at')
                ->orderBy('posts.id')
                ->limit(100)
                ->pluck('posts.id');

            foreach ($postIds as $postId) {
                PublishScheduledPostJob::dispatch((int) $postId);
            }

            $this->components->info("Dispatched {$postIds->count()} scheduled post publication job(s).");

            return self::SUCCESS;
        } finally {
            $lock->release();
            Log::info('Scheduled post publication lock released.');
        }
    }
}
