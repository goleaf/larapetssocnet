<?php

namespace App\Services;

use App\Models\Content\Post;
use Illuminate\Support\Facades\DB;

class SyncReactionCountsService
{
    public function sync(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $count = (int) $post->reactions()->count();

            $post->updateQuietly([
                'likes_count' => $count,
                'reactions_count' => $count,
            ]);

            return $post->refresh() ?? $post;
        });
    }
}
