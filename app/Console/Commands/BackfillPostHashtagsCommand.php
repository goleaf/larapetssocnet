<?php

namespace App\Console\Commands;

use App\Actions\Hashtags\RecalculateHashtagUsageCountsAction;
use App\Actions\Hashtags\SyncPostHashtagsAction;
use App\Models\Post;
use Illuminate\Console\Command;

class BackfillPostHashtagsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hashtags:backfill-posts {--chunk=200 : Number of posts to process per batch} {--recount : Recalculate hashtag usage counts after syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill hashtag relations for existing posts.';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncPostHashtagsAction $syncHashtags,
        RecalculateHashtagUsageCountsAction $recalculate
    ): int {
        $chunkSize = max(50, (int) $this->option('chunk'));
        $total = Post::query()->count();

        $this->info("Backfilling hashtags for {$total} posts...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Post::query()
            ->select(['posts.id', 'posts.body', 'posts.status', 'posts.published_at'])
            ->chunkById($chunkSize, function ($posts) use ($syncHashtags, $bar): void {
                foreach ($posts as $post) {
                    $syncHashtags->handle($post, false);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        if ($this->option('recount')) {
            $this->info('Recalculating hashtag usage counts...');
            $recalculate->handle();
        }

        $this->info('Hashtag backfill complete.');

        return self::SUCCESS;
    }
}
