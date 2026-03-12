<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Console\Command;

class PublishScheduledPostsCommand extends Command
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
    protected $description = 'Publish scheduled posts whose publish time has arrived';

    /**
     * Execute the console command.
     */
    public function handle(PostService $posts): int
    {
        $now = now();
        $published = 0;

        Post::query()
            ->scheduled()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$published, $posts): void {
                foreach ($chunk as $post) {
                    $posts->publish($post, $post->published_at);
                    $published++;
                }
            });

        $this->info("Published {$published} scheduled post(s).");

        return self::SUCCESS;
    }
}
