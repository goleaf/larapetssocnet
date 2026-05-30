<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Social\FeedItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class FeedFanOutChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<array{user_id:int, source_type:string, source_id:int}>  $items
     */
    public function __construct(
        public readonly int $postId,
        public readonly array $items,
        public readonly ?string $postCreatedAt = null,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $post = Post::query()
            ->select(['id', 'status', 'created_at'])
            ->find($this->postId);

        if (! $post instanceof Post || ! $post->status->isPubliclyReachable()) {
            return;
        }

        $now = now()->toDateTimeString();
        $postCreatedAt = $this->postCreatedAt ?? $post->created_at?->toDateTimeString() ?? $now;
        $rows = [];

        foreach ($this->items as $item) {
            if (! $this->isValidItem($item)) {
                continue;
            }

            $rows[] = [
                'user_id' => (int) $item['user_id'],
                'post_id' => $this->postId,
                'source_type' => $item['source_type'],
                'source_id' => (int) $item['source_id'],
                'post_created_at' => $postCreatedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('feed_items')->insertOrIgnore($rows);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isValidItem(array $item): bool
    {
        return isset($item['user_id'], $item['source_type'], $item['source_id'])
            && (int) $item['user_id'] > 0
            && (int) $item['source_id'] > 0
            && in_array($item['source_type'], [
                FeedItem::SOURCE_SELF,
                FeedItem::SOURCE_USER,
                FeedItem::SOURCE_PET,
            ], true);
    }
}
