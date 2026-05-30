<?php

namespace App\Console\Commands;

use App\Models\Content\Post;
use App\Services\ScheduledPostPublisherService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('posts:publish-scheduled')]
#[Description('Publish due scheduled posts.')]
class PublishScheduledPostsCommand extends Command
{
    public function handle(ScheduledPostPublisherService $publisher): int
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

            $published = 0;

            foreach ($postIds as $postId) {
                if ($publisher->publish((int) $postId)) {
                    $published++;
                }
            }

            $this->components->info("Published {$published} scheduled post(s).");

            return self::SUCCESS;
        } finally {
            $lock->release();
            Log::info('Scheduled post publication lock released.');
        }
    }
}
