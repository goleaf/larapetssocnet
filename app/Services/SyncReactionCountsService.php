<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use Illuminate\Support\Facades\DB;

class SyncReactionCountsService
{
    public function sync(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $count = (int) $post->reactions()->count();
            $typeCounts = $this->reactionTypeCounts($post);

            $post->updateQuietly([
                'likes_count' => $count,
                'reactions_count' => $count,
                'love_count' => $typeCounts[Reaction::TYPE_LOVE],
                'cute_count' => $typeCounts[Reaction::TYPE_CUTE],
                'funny_count' => $typeCounts[Reaction::TYPE_FUNNY],
                'wow_count' => $typeCounts[Reaction::TYPE_WOW],
                'sad_count' => $typeCounts[Reaction::TYPE_SAD],
                'support_count' => $typeCounts[Reaction::TYPE_SUPPORT],
            ]);

            return $post->refresh() ?? $post;
        });
    }

    /**
     * @return array<string, int>
     */
    private function reactionTypeCounts(Post $post): array
    {
        $counts = array_fill_keys(Reaction::TYPES, 0);

        foreach ($post->reactions()->selectRaw('type, count(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type') as $type => $count) {
            $type = Reaction::normalizeType((string) $type);

            if (array_key_exists($type, $counts) && is_numeric($count)) {
                $counts[$type] += (int) $count;
            }
        }

        return $counts;
    }
}
