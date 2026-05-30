<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\Reaction;

class SyncPostCountersService
{
    public function sync(Post $post): Post
    {
        $post->loadMissing(['comments', 'savedBy', 'reactions', 'shares']);

        $likesCount = (int) $post->reactions()->count();
        $commentsCount = (int) $post->comments()->count();
        $saveCount = (int) $post->savedBy()->count();
        $sharesCount = (int) $post->shares()->count();
        $typeCounts = $this->reactionTypeCounts($post);

        $post->updateQuietly([
            'likes_count' => $likesCount,
            'comments_count' => $commentsCount,
            'save_count' => $saveCount,
            'reactions_count' => $likesCount,
            ...$this->counterColumnUpdates($typeCounts),
            'shares_count' => $sharesCount,
        ]);

        return $post->refresh() ?? $post;
    }

    /**
     * @return array<string, int>
     */
    private function reactionTypeCounts(Post $post): array
    {
        $counts = array_fill_keys(Reaction::types(), 0);

        foreach ($post->reactions()->selectRaw('type, count(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type') as $type => $count) {
            $type = Reaction::normalizeType((string) $type);

            if (array_key_exists($type, $counts) && is_numeric($count)) {
                $counts[$type] += (int) $count;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $typeCounts
     * @return array<string, int>
     */
    private function counterColumnUpdates(array $typeCounts): array
    {
        $updates = [];

        foreach (Reaction::types() as $type) {
            $updates[Reaction::counterColumn($type)] = $typeCounts[$type] ?? 0;
        }

        return $updates;
    }
}
