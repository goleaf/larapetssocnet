<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(?User $viewer, Post $post): bool
    {
        if (! $post->author->canBeViewedBy($viewer)) {
            return false;
        }

        return match ($post->visibility) {
            Post::VISIBILITY_PRIVATE => $viewer?->id === $post->user_id,
            Post::VISIBILITY_FOLLOWERS => $viewer?->id === $post->user_id
                || ($viewer !== null && $viewer->isFollowing($post->author)),
            default => true,
        };
    }

    public function create(User $user): bool
    {
        return ! (bool) $user->is_banned;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function pin(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function report(User $user, Post $post): bool
    {
        return $user->id !== $post->user_id;
    }
}
