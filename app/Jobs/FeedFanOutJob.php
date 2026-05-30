<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Social\FeedItem;
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
        $lock = Cache::lock("posts:fanout:{$this->postId}", 300);

        if (! $lock->get()) {
            return;
        }

        try {
            $post = Post::query()
                ->select(['id', 'user_id', 'pet_id', 'status', 'visibility', 'is_fanned_out', 'created_at'])
                ->with(['pets:id'])
                ->find($this->postId);

            if (! $post instanceof Post || ! $post->status->isPubliclyReachable() || (bool) $post->is_fanned_out) {
                return;
            }

            $recipientIds = [];
            $batchCount = 0;
            $feedItemCount = 0;
            $dispatchChunk = function (array $items) use ($post, &$batchCount, &$feedItemCount): void {
                if ($items === []) {
                    return;
                }

                $batchCount++;
                $feedItemCount += count($items);

                FeedFanOutChunkJob::dispatch(
                    (int) $post->getKey(),
                    $items,
                    $post->created_at?->toDateTimeString(),
                )->afterCommit();
            };

            $dispatchChunk([[
                'user_id' => (int) $post->user_id,
                'source_type' => FeedItem::SOURCE_SELF,
                'source_id' => (int) $post->user_id,
            ]]);

            if ($post->visibility === Post::VISIBILITY_PRIVATE) {
                Cache::put('posts:fanout:last:'.$post->getKey(), [
                    'post_id' => (int) $post->getKey(),
                    'recipient_count' => 0,
                    'feed_item_count' => $feedItemCount,
                    'batch_count' => $batchCount,
                ], now()->addDay());

                $post->forceFill(['is_fanned_out' => true])->saveQuietly();

                return;
            }

            Follow::query()
                ->select(['id', 'follower_id', 'following_id'])
                ->where('following_id', $post->user_id)
                ->where('status', 'accepted')
                ->orderBy('id')
                ->chunkById(500, function ($follows) use ($post, $dispatchChunk, &$recipientIds): void {
                    $items = [];

                    foreach ($follows as $follow) {
                        $recipientId = (int) $follow->follower_id;

                        if ($recipientId === (int) $post->user_id) {
                            continue;
                        }

                        $recipientIds[$recipientId] = true;
                        $items[] = [
                            'user_id' => $recipientId,
                            'source_type' => FeedItem::SOURCE_USER,
                            'source_id' => (int) $post->user_id,
                        ];
                    }

                    $dispatchChunk($items);
                });

            $petIds = $post->pets
                ->pluck('id')
                ->when($post->pet_id !== null, fn ($ids) => $ids->push((int) $post->pet_id))
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($petIds !== []) {
                DB::table('pet_followers')
                    ->select(['id', 'user_id', 'pet_id'])
                    ->whereIn('pet_id', $petIds)
                    ->orderBy('id')
                    ->chunkById(500, function ($petFollowers) use ($post, $dispatchChunk, &$recipientIds): void {
                        $items = [];

                        foreach ($petFollowers as $petFollower) {
                            $recipientId = (int) $petFollower->user_id;

                            if ($recipientId === (int) $post->user_id) {
                                continue;
                            }

                            $recipientIds[$recipientId] = true;
                            $items[] = [
                                'user_id' => $recipientId,
                                'source_type' => FeedItem::SOURCE_PET,
                                'source_id' => (int) $petFollower->pet_id,
                            ];
                        }

                        $dispatchChunk($items);
                    });
            }

            unset($recipientIds[(int) $post->user_id]);

            Cache::put('posts:fanout:last:'.$post->getKey(), [
                'post_id' => (int) $post->getKey(),
                'recipient_count' => count($recipientIds),
                'feed_item_count' => $feedItemCount,
                'batch_count' => $batchCount,
            ], now()->addDay());

            $post->forceFill(['is_fanned_out' => true])->saveQuietly();
        } finally {
            $lock->release();
        }
    }
}
