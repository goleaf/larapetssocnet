<?php

namespace App\Services;

use App\Models\Content\Post;

class SyncPostCountersService
{
    public function sync(Post $post): Post
    {
        $post->loadMissing(['comments', 'savedBy', 'reactions', 'shares']);

        $likesCount = (int) $post->reactions()->count();
        $commentsCount = (int) $post->comments()->count();
        $saveCount = (int) $post->savedBy()->count();
        $sharesCount = (int) $post->shares()->count();

        $post->updateQuietly([
            'likes_count' => $likesCount,
            'comments_count' => $commentsCount,
            'save_count' => $saveCount,
            'reactions_count' => $likesCount,
            'shares_count' => $sharesCount,
        ]);

        return $post->refresh() ?? $post;
    }
}
