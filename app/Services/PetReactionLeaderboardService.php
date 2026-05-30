<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Collection;

class PetReactionLeaderboardService
{
    /**
     * @return Collection<int, Post>
     */
    public function mostLovedPosts(Pet $pet, int $limit = 3): Collection
    {
        return $pet->taggedPosts()
            ->with(['user', 'user.media', 'media'])
            ->where('posts.status', PostStatus::Published->value)
            ->whereNull('posts.deleted_at')
            ->where('posts.reactions_count', '>', 0)
            ->orderByDesc('posts.reactions_count')
            ->orderByDesc('posts.created_at')
            ->limit($limit)
            ->get();
    }
}
