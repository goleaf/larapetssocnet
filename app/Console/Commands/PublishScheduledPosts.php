<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled posts that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $published = 0;

        Post::query()
            ->select(['posts.id', 'posts.published_at'])
            ->where('status', PostStatus::Scheduled->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($posts) use (&$published): void {
                $posts->each(function (Post $post) use (&$published): void {
                    $post->update([
                        'status' => PostStatus::Published->value,
                    ]);
                    $published++;
                });
            });

        $this->info("Published {$published} scheduled post(s).");

        return self::SUCCESS;
    }
}
