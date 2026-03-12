<?php

namespace App\Console\Commands;

use App\Models\Like;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Reaction;
use App\Services\SyncPostCountersService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RebuildEngagementCountersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:rebuild-engagement-counters {--import-legacy : Import legacy likes/post_reactions into reactions before syncing}';

    /**
     * @var string
     */
    protected $description = 'Rebuild engagement counters for posts and optionally import legacy reactions.';

    public function handle(SyncPostCountersService $sync): int
    {
        $importLegacy = (bool) $this->option('import-legacy');

        if ($importLegacy) {
            $this->info('Importing legacy likes and post reactions into reactions...');
            $this->importLegacyReactions();
        }

        $this->info('Rebuilding post engagement counters...');

        Post::query()
            ->select(['posts.id'])
            ->chunkById(300, function ($posts) use ($sync): void {
                $posts->each(function (Post $post) use ($sync): void {
                    $sync->sync($post);
                });
            });

        $this->info('Engagement counters rebuilt.');

        return self::SUCCESS;
    }

    private function importLegacyReactions(): void
    {
        $hasLikes = Schema::hasTable('likes');
        $hasPostReactions = Schema::hasTable('post_reactions');

        if (! $hasLikes && ! $hasPostReactions) {
            return;
        }

        Post::query()
            ->select(['posts.id'])
            ->chunkById(300, function ($posts) use ($hasLikes, $hasPostReactions): void {
                $posts->each(function (Post $post) use ($hasLikes, $hasPostReactions): void {
                    if ($hasPostReactions) {
                        PostReaction::query()
                            ->where('post_id', $post->id)
                            ->get(['user_id', 'type'])
                            ->each(function (PostReaction $legacy): void {
                                Reaction::query()->firstOrCreate([
                                    'user_id' => $legacy->user_id,
                                    'reactable_type' => Post::class,
                                    'reactable_id' => $legacy->post_id,
                                ], [
                                    'type' => Reaction::normalizeType($legacy->type),
                                ]);
                            });
                    }

                    if ($hasLikes) {
                        Like::query()
                            ->where('post_id', $post->id)
                            ->get(['user_id', 'post_id'])
                            ->each(function (Like $legacy): void {
                                Reaction::query()->firstOrCreate([
                                    'user_id' => $legacy->user_id,
                                    'reactable_type' => Post::class,
                                    'reactable_id' => $legacy->post_id,
                                ], [
                                    'type' => Reaction::TYPE_LOVE,
                                ]);
                            });
                    }
                });
            });
    }
}
