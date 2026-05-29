<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Social\Follow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedFanOutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $postId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $post = Post::query()
            ->select(['id', 'user_id', 'status', 'visibility'])
            ->with(['pets:id'])
            ->find($this->postId);

        if (! $post instanceof Post || ! $post->status->isPubliclyReachable()) {
            return;
        }

        $recipientIds = [];

        Follow::query()
            ->select(['id', 'follower_id'])
            ->where('following_id', $post->user_id)
            ->where('status', 'accepted')
            ->orderBy('id')
            ->chunkById(500, function ($follows) use (&$recipientIds): void {
                foreach ($follows as $follow) {
                    $recipientIds[(int) $follow->follower_id] = true;
                }
            });

        $petIds = $post->pets->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($petIds !== []) {
            DB::table('pet_followers')
                ->select(['id', 'user_id'])
                ->whereIn('pet_id', $petIds)
                ->orderBy('id')
                ->chunkById(500, function ($petFollowers) use (&$recipientIds): void {
                    foreach ($petFollowers as $petFollower) {
                        $recipientIds[(int) $petFollower->user_id] = true;
                    }
                });
        }

        unset($recipientIds[(int) $post->user_id]);

        Cache::put('posts:fanout:last:'.$post->getKey(), [
            'post_id' => (int) $post->getKey(),
            'recipient_count' => count($recipientIds),
        ], now()->addDay());
    }
}
