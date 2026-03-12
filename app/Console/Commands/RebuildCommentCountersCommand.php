<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use App\Services\ContentService;
use Illuminate\Console\Command;

class RebuildCommentCountersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rebuild-comment-counters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild comment and reply counters and backfill comment HTML.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Rebuilding post comments_count...');

        Post::query()
            ->select(['id'])
            ->withCount(['comments as computed_comments'])
            ->chunkById(200, function ($posts): void {
                foreach ($posts as $post) {
                    $post->updateQuietly([
                        'comments_count' => (int) $post->computed_comments,
                    ]);
                }
            });

        $this->info('Rebuilding comment replies_count...');

        Comment::query()
            ->select(['id'])
            ->withCount(['replies as computed_replies'])
            ->chunkById(200, function ($comments): void {
                foreach ($comments as $comment) {
                    $comment->updateQuietly([
                        'replies_count' => (int) $comment->computed_replies,
                    ]);
                }
            });

        $this->info('Backfilling comment body_html...');
        $content = app(ContentService::class);

        Comment::query()
            ->select(['id', 'body'])
            ->whereNull('body_html')
            ->chunkById(200, function ($comments) use ($content): void {
                foreach ($comments as $comment) {
                    $comment->updateQuietly([
                        'body_html' => $content->process((string) $comment->body),
                    ]);
                }
            });

        $this->info('Comment counters rebuilt.');

        return self::SUCCESS;
    }
}
